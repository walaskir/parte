<?php

namespace App\Console\Commands;

use App\Jobs\ExtractDeathDateAndAnnouncementJob;
use App\Models\DeathNotice;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class ProcessExistingPartesCommand extends Command
{
    protected $signature = 'parte:process-existing';

    protected $description = 'Interaktivni pruvodce pro re-extrakci dat z parte';

    private const RECORDS_PER_PAGE = 15;

    /**
     * Field options for extraction selection.
     *
     * @var array<string, string>
     */
    private array $fieldOptions = [
        ExtractDeathDateAndAnnouncementJob::FIELD_ALL => 'Vse (kompletni re-extrakce)',
        ExtractDeathDateAndAnnouncementJob::FIELD_FULL_NAME => 'Jmeno',
        ExtractDeathDateAndAnnouncementJob::FIELD_OPENING_QUOTE => 'Citat',
        ExtractDeathDateAndAnnouncementJob::FIELD_DEATH_DATE => 'Datum umrti',
        ExtractDeathDateAndAnnouncementJob::FIELD_ANNOUNCEMENT_TEXT => 'Text oznameni',
        ExtractDeathDateAndAnnouncementJob::FIELD_PORTRAIT => 'Portret (fotografie)',
    ];

    public function handle(): int
    {
        $totalRecords = DeathNotice::count();

        if ($totalRecords === 0) {
            $this->info('Zadne parte zaznamy nenalezeny v databazi.');

            return CommandAlias::SUCCESS;
        }

        $this->info("Interaktivni pruvodce pro re-extrakci dat - {$totalRecords} celkem zaznamu");
        $this->info('Navigujte stranky a vyberte zaznamy ke zpracovani.');
        $this->newLine();

        // Step 1: Record selection
        $selectedIds = $this->selectRecords($totalRecords);

        if (empty($selectedIds)) {
            $this->info('Operace zrusena.');

            return CommandAlias::SUCCESS;
        }

        // Step 2: Field selection
        $selectedFields = $this->selectFields();

        if (empty($selectedFields)) {
            $this->info('Operace zrusena.');

            return CommandAlias::SUCCESS;
        }

        // Step 3: Process selected records with selected fields
        return $this->processRecords($selectedIds, $selectedFields);
    }

    /**
     * Step 1: Interactive record selection with pagination.
     *
     * @return array<int>
     */
    private function selectRecords(int $totalRecords): array
    {
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
            $this->info("Strana {$currentPage}/{$totalPages} | Vybrano: ".count($selectedIds).' zaznamu');
            $this->line('ID   | Hash     | Jmeno                     | Umrti      | Zdroj');
            $this->line(str_repeat('-', 80));

            // Get already selected IDs on this page for defaults
            $pageDefaults = array_intersect($selectedIds, $records->pluck('id')->toArray());

            // Multiselect for current page
            $pageSelection = multiselect(
                label: 'Vyberte zaznamy ke zpracovani (Mezera pro prepnuti, Enter pro potvrzeni):',
                options: $options,
                default: $pageDefaults,
                scroll: self::RECORDS_PER_PAGE,
                hint: 'Sipky pro navigaci, Mezera pro oznaceni/odznaceni'
            );

            // Update selected IDs - remove deselected from this page, add newly selected
            $pageIds = $records->pluck('id')->toArray();
            $selectedIds = array_diff($selectedIds, $pageIds); // Remove all from this page
            $selectedIds = array_merge($selectedIds, $pageSelection); // Add current selection
            $selectedIds = array_unique($selectedIds);

            // Navigation menu
            $navOptions = [];

            if ($currentPage > 1) {
                $navOptions['prev'] = 'Predchozi strana';
            }

            if ($currentPage < $totalPages) {
                $navOptions['next'] = 'Dalsi strana';
            }

            $navOptions['process'] = 'Zpracovat '.count($selectedIds).' vybranych zaznamu';
            $navOptions['cancel'] = 'Zrusit';

            $action = select(
                label: 'Co chcete udelat?',
                options: $navOptions,
                hint: count($selectedIds).' zaznamu vybrano pres vsechny stranky'
            );

            match ($action) {
                'prev' => $currentPage--,
                'next' => $currentPage++,
                'process' => null,
                'cancel' => null,
            };

            if ($action === 'cancel') {
                return [];
            }

            if ($action === 'process') {
                if (empty($selectedIds)) {
                    $this->warn('Zadne zaznamy nevybrany.');

                    continue;
                }

                break;
            }
        }

        return $selectedIds;
    }

    /**
     * Step 2: Field selection for extraction.
     *
     * @return array<string>
     */
    private function selectFields(): array
    {
        $this->newLine();
        $this->info('Nyni vyberte pole, ktera chcete extrahovat:');

        $selectedFields = multiselect(
            label: 'Ktera pole chcete extrahovat?',
            options: $this->fieldOptions,
            default: [ExtractDeathDateAndAnnouncementJob::FIELD_ALL],
            scroll: count($this->fieldOptions),
            hint: 'Vybrana pole budou vzdy prepsana novymi hodnotami'
        );

        if (empty($selectedFields)) {
            return [];
        }

        // If 'all' is selected, return only 'all'
        if (in_array(ExtractDeathDateAndAnnouncementJob::FIELD_ALL, $selectedFields)) {
            return [ExtractDeathDateAndAnnouncementJob::FIELD_ALL];
        }

        return $selectedFields;
    }

    /**
     * Step 3: Process selected records with selected fields.
     *
     * @param  array<int>  $selectedIds
     * @param  array<string>  $selectedFields
     */
    private function processRecords(array $selectedIds, array $selectedFields): int
    {
        $notices = DeathNotice::whereIn('id', $selectedIds)->get();

        // Show summary
        $this->newLine();
        $this->info("Zpracovani {$notices->count()} zaznamu...");
        $this->info('Pole k extrakci: '.implode(', ', array_map(fn ($f) => $this->fieldOptions[$f] ?? $f, $selectedFields)));
        $this->newLine();

        $bar = $this->output->createProgressBar($notices->count());

        $processed = 0;
        $skipped = 0;

        foreach ($notices as $notice) {
            $media = $notice->getFirstMedia('pdf');

            if (! $media) {
                $this->warn("Parte {$notice->hash} nema PDF, preskakuji.");
                $skipped++;
                $bar->advance();

                continue;
            }

            $pdfPath = $media->getPath();

            if (! file_exists($pdfPath)) {
                $this->warn("PDF soubor nenalezen pro parte {$notice->hash}, preskakuji.");
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
                    $this->warn("Nepodarilo se konvertovat PDF na JPG pro {$notice->full_name}");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                ExtractDeathDateAndAnnouncementJob::dispatch(
                    $notice,
                    $tempImagePath,
                    $selectedFields
                );
                $processed++;
            } catch (\Exception $e) {
                $this->error("Chyba pri zpracovani parte {$notice->hash}: {$e->getMessage()}");
                if (file_exists($tempImagePath)) {
                    unlink($tempImagePath);
                }
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Zpracovani dokonceno!');
        $this->info("- Odeslano do fronty: {$processed}");
        $this->info("- Preskoceno: {$skipped}");
        $this->info('Vybrana pole budou prepsana novymi hodnotami z OCR.');
        $this->info("Spustte 'php artisan queue:work --queue=extraction' nebo 'php artisan horizon' pro zpracovani jobu.");

        return CommandAlias::SUCCESS;
    }
}
