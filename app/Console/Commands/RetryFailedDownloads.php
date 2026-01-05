<?php

namespace App\Console\Commands;

use App\Models\DeathNotice;
use App\Services\DeathNoticeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RetryFailedDownloads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parte:retry-failed {--id=* : Specific notice IDs to retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry downloading and processing failed death notices (with missing death_date or announcement_text)';

    /**
     * Execute the console command.
     */
    public function handle(DeathNoticeService $service): int
    {
        $specificIds = $this->option('id');

        if (! empty($specificIds)) {
            $notices = DeathNotice::whereIn('id', $specificIds)->get();
            $this->info("Retrying {$notices->count()} specific notice(s)...");
        } else {
            // Find notices with missing data (death_date OR announcement_text is null)
            $notices = DeathNotice::whereNull('death_date')
                ->orWhereNull('announcement_text')
                ->get();

            $this->info("Found {$notices->count()} notice(s) with missing data.");
        }

        if ($notices->isEmpty()) {
            $this->info('No notices to retry.');

            return self::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($notices as $notice) {
            $this->info("Processing: {$notice->full_name} (ID: {$notice->id}, Hash: {$notice->hash})");

            try {
                // Generate output path using notice hash
                $directory = 'parte/'.$notice->hash;
                $fullPath = Storage::disk('public')->path($directory);

                if (! file_exists($fullPath)) {
                    mkdir($fullPath, 0755, true);
                }

                // Determine filename from source URL
                $fileName = basename(parse_url($notice->source_url, PHP_URL_PATH));
                $outputPath = $fullPath.'/'.$fileName;

                // Use reflection to call private downloadOriginalPdf method
                $reflection = new \ReflectionClass($service);
                $method = $reflection->getMethod('downloadOriginalPdf');
                $method->setAccessible(true);

                $result = $method->invoke($service, $notice->source_url, $outputPath, $notice);

                if ($result) {
                    $successCount++;
                    $this->line('  ✓ Successfully downloaded and queued for processing');
                } else {
                    $failCount++;
                    $this->error('  ✗ Failed to download');
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error("  ✗ Error: {$e->getMessage()}");
                Log::error("Failed to retry notice {$notice->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Results: {$successCount} succeeded, {$failCount} failed");

        return self::SUCCESS;
    }
}
