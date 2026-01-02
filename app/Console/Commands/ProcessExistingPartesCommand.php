<?php

namespace App\Console\Commands;

use App\Jobs\ExtractDeathDateAndAnnouncementJob;
use App\Models\DeathNotice;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ProcessExistingPartesCommand extends Command
{
    protected $signature = 'parte:process-existing
        {--missing-death-date : Only process records missing death_date}
        {--missing-announcement-text : Only process records missing announcement_text}';

    protected $description = 'Process existing parte records to extract death date and announcement text from PDFs';

    public function handle(): int
    {
        $query = DeathNotice::query();

        if ($this->option('missing-death-date') || $this->option('missing-announcement-text')) {
            $query->where(function ($q) {
                if ($this->option('missing-death-date')) {
                    $q->orWhereNull('death_date');
                }
                if ($this->option('missing-announcement-text')) {
                    $q->orWhereNull('announcement_text');
                }
            });
        } else {
            // No options = process records missing either death_date OR announcement_text
            $query->where(function ($q) {
                $q->whereNull('death_date')->orWhereNull('announcement_text');
            });
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
                ExtractDeathDateAndAnnouncementJob::dispatch($notice, $tempImagePath);
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
