<?php

namespace App\Console\Commands;

use App\Jobs\ExtractDeathDateAndAnnouncementJob;
use App\Models\DeathNotice;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Command\Command as CommandAlias;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class ProcessExistingPartesCommand extends Command
{
    protected $signature = 'parte:process-existing
        {--select : Interactive selection of specific records to process}
        {--missing-name : Only process records missing full_name}
        {--missing-death-date : Only process records missing death_date}
        {--missing-announcement-text : Only process records missing announcement_text}
        {--missing-opening-quote : Only process records missing opening_quote}
        {--extract-portraits : Extract portraits from all parte without photos}
        {--force : Force re-extraction even if data already exists}';

    protected $description = 'Process existing parte records to extract death date, announcement text, or portraits from PDFs';

    private const RECORDS_PER_PAGE = 15;

    public function handle(): int
    {
        // Interactive selection mode
        if ($this->option('select')) {
            return $this->handleInteractiveSelection();
        }

        $query = DeathNotice::query();

        if ($this->option('extract-portraits')) {
            if ($this->option('force')) {
                // FORCE: Re-extract ALL portraits
                $query->whereNotNull('id'); // All records
                $this->warn('⚠️  FORCE mode: Re-extracting portraits from ALL parte (including existing)...');
            } else {
                // NORMAL: Only parte without photos
                $query->where(function ($query) {
                    $query->where('has_photo', false)
                        ->orWhereNull('has_photo');
                });
                $this->info('Extracting portraits from parte without photos...');
            }
        } elseif ($this->option('missing-name') || $this->option('missing-death-date') || $this->option('missing-announcement-text') || $this->option('missing-opening-quote')) {
            $query->where(function ($q) {
                if ($this->option('missing-name')) {
                    $q->orWhereNull('full_name')->orWhere('full_name', '');
                }
                if ($this->option('missing-death-date')) {
                    $q->orWhereNull('death_date');
                }
                if ($this->option('missing-announcement-text')) {
                    $q->orWhereNull('announcement_text');
                }
                if ($this->option('missing-opening-quote')) {
                    $q->orWhereNull('opening_quote');
                }
            });

            if ($this->option('force')) {
                $this->warn('⚠️  FORCE mode: Re-extracting from ALL parte (ignoring existing data)...');
                $query = DeathNotice::query()->whereNotNull('id');
            }
        } else {
            // No options = process records missing either full_name, death_date, announcement_text, or opening_quote
            if ($this->option('force')) {
                $query->whereNotNull('id'); // All records
                $this->warn('⚠️  FORCE mode: Re-extracting ALL data from ALL parte...');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('full_name')
                        ->orWhere('full_name', '')
                        ->orWhereNull('death_date')
                        ->orWhereNull('announcement_text')
                        ->orWhereNull('opening_quote');
                });
            }
        }

        $notices = $query->get();

        if ($notices->isEmpty()) {
            $this->info('No notices found to process.');

            return CommandAlias::SUCCESS;
        }

        $this->info("Found {$notices->count()} notices to process.");
        $bar = $this->output->createProgressBar($notices->count());

        $processed = 0;
        $skipped = 0;

        foreach ($notices as $notice) {
            $media = $notice->getFirstMedia('pdf');

            if (! $media) {
                $this->warn("Notice {$notice->hash} has no PDF media, skipping.");
                $skipped++;
                $bar->advance();

                continue;
            }

            $pdfPath = $media->getPath();

            if (! file_exists($pdfPath)) {
                $this->warn("PDF file not found for notice {$notice->hash}, skipping.");
                $skipped++;
                $bar->advance();

                continue;
            }

            // Convert PDF to image for OCR
            $tempImagePath = storage_path('app/temp/process_existing_'.uniqid().'.jpg');
            $tempDir = dirname($tempImagePath);

            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            try {
                // Convert PDF to JPG using Imagick
                $imagick = new \Imagick;
                $imagick->setResolution(300, 300);
                $imagick->readImage($pdfPath.'[0]'); // Read first page only
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $imagick->writeImage($tempImagePath);
                $imagick->clear();
                $imagick->destroy();

                if (! file_exists($tempImagePath)) {
                    $this->warn("Failed to convert PDF to JPG for {$notice->full_name}");
                    $skipped++;

                    continue;
                }

                // Dispatch OCR job
                ExtractDeathDateAndAnnouncementJob::dispatch(
                    $notice,
                    $tempImagePath,
                    $this->option('extract-portraits') // Pass portraitsOnly flag
                );
                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to process notice {$notice->hash}: {$e->getMessage()}");
                if (file_exists($tempImagePath)) {
                    unlink($tempImagePath);
                }
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Processing complete!');
        $this->info("- Dispatched to queue: {$processed}");
        $this->info("- Skipped: {$skipped}");
        $this->info("Run 'php artisan queue:work' or 'php artisan horizon' to process jobs.");

        return CommandAlias::SUCCESS;
    }

    /**
     * Handle interactive selection of records to process
     */
    private function handleInteractiveSelection(): int
    {
        $totalRecords = DeathNotice::count();

        if ($totalRecords === 0) {
            $this->info('No parte records found in database.');

            return CommandAlias::SUCCESS;
        }

        $this->info("Interactive selection mode - {$totalRecords} total records");
        $this->info('Navigate pages and select records to process.');
        $this->newLine();

        $selectedIds = [];
        $currentPage = 1;
        $totalPages = (int) ceil($totalRecords / self::RECORDS_PER_PAGE);

        while (true) {
            // Fetch current page records
            $records = DeathNotice::query()
                ->orderByDesc('death_date')
                ->orderByDesc('created_at')
                ->skip(($currentPage - 1) * self::RECORDS_PER_PAGE)
                ->take(self::RECORDS_PER_PAGE)
                ->get();

            // Build options for multiselect
            $options = [];
            foreach ($records as $record) {
                $deathDate = $record->death_date?->format('d.m.Y') ?? '---';
                $name = mb_substr($record->full_name ?? '---', 0, 25);
                $source = mb_substr($record->source ?? '---', 0, 15);
                $hash = mb_substr($record->hash, 0, 8);

                // Mark already selected records
                $selected = in_array($record->id, $selectedIds) ? ' [*]' : '';

                $options[$record->id] = sprintf(
                    '%-4d | %-8s | %-25s | %-10s | %-15s%s',
                    $record->id,
                    $hash,
                    $name,
                    $deathDate,
                    $source,
                    $selected
                );
            }

            // Show page header
            $this->info("Page {$currentPage}/{$totalPages} | Selected: ".count($selectedIds).' records');
            $this->line('ID   | Hash     | Name                      | Death Date | Source');
            $this->line(str_repeat('-', 80));

            // Get already selected IDs on this page for defaults
            $pageDefaults = array_intersect($selectedIds, $records->pluck('id')->toArray());

            // Multiselect for current page
            $pageSelection = multiselect(
                label: 'Select records to process (Space to toggle, Enter to confirm page):',
                options: $options,
                default: $pageDefaults,
                scroll: self::RECORDS_PER_PAGE,
                hint: 'Use arrow keys to navigate, Space to select/deselect'
            );

            // Update selected IDs - remove deselected from this page, add newly selected
            $pageIds = $records->pluck('id')->toArray();
            $selectedIds = array_diff($selectedIds, $pageIds); // Remove all from this page
            $selectedIds = array_merge($selectedIds, $pageSelection); // Add current selection
            $selectedIds = array_unique($selectedIds);

            // Navigation menu
            $navOptions = [];

            if ($currentPage > 1) {
                $navOptions['prev'] = 'Previous page';
            }

            if ($currentPage < $totalPages) {
                $navOptions['next'] = 'Next page';
            }

            $navOptions['process'] = 'Process '.count($selectedIds).' selected records';
            $navOptions['cancel'] = 'Cancel';

            $action = select(
                label: 'What would you like to do?',
                options: $navOptions,
                hint: count($selectedIds).' records selected across all pages'
            );

            match ($action) {
                'prev' => $currentPage--,
                'next' => $currentPage++,
                'process' => null,
                'cancel' => null,
            };

            if ($action === 'cancel') {
                $this->info('Operation cancelled.');

                return CommandAlias::SUCCESS;
            }

            if ($action === 'process') {
                break;
            }
        }

        if (empty($selectedIds)) {
            $this->warn('No records selected.');

            return CommandAlias::SUCCESS;
        }

        // Process selected records
        $notices = DeathNotice::whereIn('id', $selectedIds)->get();

        $this->info("Processing {$notices->count()} selected records...");
        $bar = $this->output->createProgressBar($notices->count());

        $processed = 0;
        $skipped = 0;

        foreach ($notices as $notice) {
            $media = $notice->getFirstMedia('pdf');

            if (! $media) {
                $this->warn("Notice {$notice->hash} has no PDF media, skipping.");
                $skipped++;
                $bar->advance();

                continue;
            }

            $pdfPath = $media->getPath();

            if (! file_exists($pdfPath)) {
                $this->warn("PDF file not found for notice {$notice->hash}, skipping.");
                $skipped++;
                $bar->advance();

                continue;
            }

            // Convert PDF to image for OCR
            $tempImagePath = storage_path('app/temp/process_existing_'.uniqid().'.jpg');
            $tempDir = dirname($tempImagePath);

            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            try {
                $imagick = new \Imagick;
                $imagick->setResolution(300, 300);
                $imagick->readImage($pdfPath.'[0]');
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $imagick->writeImage($tempImagePath);
                $imagick->clear();
                $imagick->destroy();

                if (! file_exists($tempImagePath)) {
                    $this->warn("Failed to convert PDF to JPG for {$notice->full_name}");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                ExtractDeathDateAndAnnouncementJob::dispatch(
                    $notice,
                    $tempImagePath,
                    $this->option('extract-portraits')
                );
                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to process notice {$notice->hash}: {$e->getMessage()}");
                if (file_exists($tempImagePath)) {
                    unlink($tempImagePath);
                }
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Processing complete!');
        $this->info("- Dispatched to queue: {$processed}");
        $this->info("- Skipped: {$skipped}");
        $this->info("Run 'php artisan queue:work' or 'php artisan horizon' to process jobs.");

        return CommandAlias::SUCCESS;
    }
}
