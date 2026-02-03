<?php

namespace App\Jobs;

use App\Models\DeathNotice;
use App\Services\VisionOcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractImageParteJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Number of seconds before timing out.
     */
    public int $timeout = 300;

    /**
     * Seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeathNotice $deathNotice
    ) {
        $this->onQueue('extraction');
    }

    /**
     * Execute the job - extract name and funeral date from PS BK image.
     */
    public function handle(VisionOcrService $visionOcrService): void
    {
        // Get image path from MediaLibrary 'original_image' collection
        $media = $this->deathNotice->getFirstMedia('original_image');
        $imagePath = $media?->getPath();

        Log::info("Starting image extraction (name + funeral_date) for DeathNotice {$this->deathNotice->hash}", [
            'id' => $this->deathNotice->id,
            'hash' => $this->deathNotice->hash,
            'source' => $this->deathNotice->source,
            'image_path' => $imagePath,
            'media_exists' => $media !== null,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (! $imagePath || ! file_exists($imagePath)) {
                Log::error('Image not found in MediaLibrary', [
                    'hash' => $this->deathNotice->hash,
                    'media_exists' => $media !== null,
                ]);
                throw new \Exception("Image not found in MediaLibrary for {$this->deathNotice->hash}");
            }

            // Extract name and funeral_date (NOT death_date, no known name yet)
            $ocrData = $visionOcrService->extractFromImage($imagePath, extractDeathDate: false, knownName: null);

            if (! $ocrData || ! isset($ocrData['full_name']) || ! $ocrData['full_name']) {
                Log::warning("Image extraction returned no valid name for DeathNotice {$this->deathNotice->hash}", [
                    'ocr_data' => $ocrData,
                    'attempt' => $this->attempts(),
                ]);

                throw new \Exception('Image extraction returned no valid name');
            }

            // Update full_name, funeral_date, and announcement_text
            $this->deathNotice->update([
                'full_name' => $ocrData['full_name'],
                'funeral_date' => $ocrData['funeral_date'] ?? $this->deathNotice->funeral_date,
                'announcement_text' => $ocrData['announcement_text'] ?? null,
            ]);

            Log::info("Successfully extracted name, funeral_date, and announcement_text for DeathNotice {$this->deathNotice->hash}", [
                'full_name' => $ocrData['full_name'],
                'funeral_date' => $ocrData['funeral_date'],
                'announcement_text' => isset($ocrData['announcement_text']) ? substr($ocrData['announcement_text'], 0, 100).'...' : null,
            ]);

            // Dispatch death_date extraction job (step 2)
            ExtractDeathDateAndAnnouncementJob::dispatch($this->deathNotice);

            Log::info("Dispatched ExtractDeathDateAndAnnouncementJob for DeathNotice {$this->deathNotice->hash}");
        } catch (\Exception $e) {
            Log::error("Image extraction failed for DeathNotice {$this->deathNotice->hash}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $media = $this->deathNotice->getFirstMedia('original_image');

        Log::error("ExtractImageParteJob permanently failed for DeathNotice {$this->deathNotice->hash}", [
            'error' => $exception->getMessage(),
            'media_path' => $media?->getPath(),
        ]);
    }
}
