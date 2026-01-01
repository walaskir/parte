<?php

namespace App\Console\Commands;

use App\Jobs\ExtractParteDataJob;
use App\Models\DeathNotice;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ProcessExistingPartesCommand extends Command
{
    protected $signature = 'parte:process-existing 
                            {--source= : Process only notices from specific source}
                            {--missing-death-date : Process only notices missing death_date}';

    protected $description = 'Process existing parte notices with OCR to extract missing death_date';

    public function handle(): int
    {
        $query = DeathNotice::query();

        if ($source = $this->option('source')) {
            $query->where('source', $source);
        }

        if ($this->option('missing-death-date')) {
            $query->whereNull('death_date');
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
                $imagick = new \Imagick;
                $imagick->setResolution(300, 300);
                $imagick->readImage($pdfPath.'[0]');
                $imagick->setImageFormat('jpg');
                $imagick->writeImage($tempImagePath);
                $imagick->clear();

                // Dispatch OCR job
                ExtractParteDataJob::dispatch($notice, $tempImagePath);
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
