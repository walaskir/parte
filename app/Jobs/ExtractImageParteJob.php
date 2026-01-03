<?php

namespace App\Jobs;

use App\Models\DeathNotice;
use App\Services\PortraitExtractionService;
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
        public DeathNotice $deathNotice,
        public string $imagePath
    ) {
        $this->onQueue('extraction');
    }

    /**
     * Execute the job - extract name and funeral date from PS BK image.
     */
    public function handle(VisionOcrService $visionOcrService): void
    {
        Log::info("Starting image extraction (name + funeral_date) for DeathNotice {$this->deathNotice->hash}", [
            'id' => $this->deathNotice->id,
            'hash' => $this->deathNotice->hash,
            'source' => $this->deathNotice->source,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (! file_exists($this->imagePath)) {
                Log::error("Image file not found for extraction: {$this->imagePath}");
                throw new \Exception("Image file not found: {$this->imagePath}");
            }

            // Extract name and funeral_date (NOT death_date)
            $ocrData = $visionOcrService->extractFromImage($this->imagePath, extractDeathDate: false);

            if (! $ocrData || ! isset($ocrData['full_name']) || ! $ocrData['full_name']) {
                Log::warning("Image extraction returned no valid name for DeathNotice {$this->deathNotice->hash}", [
                    'ocr_data' => $ocrData,
                    'attempt' => $this->attempts(),
                ]);

                throw new \Exception('Image extraction returned no valid name');
            }

            // Update full_name, funeral_date, announcement_text, and has_photo
            $this->deathNotice->update([
                'full_name' => $ocrData['full_name'],
                'funeral_date' => $ocrData['funeral_date'] ?? $this->deathNotice->funeral_date,
                'announcement_text' => $ocrData['announcement_text'] ?? null,
                'has_photo' => isset($ocrData['has_photo']) ? (bool) $ocrData['has_photo'] : false,
            ]);

            Log::info("Successfully extracted name, funeral_date, and announcement_text for DeathNotice {$this->deathNotice->hash}", [
                'full_name' => $ocrData['full_name'],
                'funeral_date' => $ocrData['funeral_date'],
                'announcement_text' => isset($ocrData['announcement_text']) ? substr($ocrData['announcement_text'], 0, 100).'...' : null,
                'has_photo' => $ocrData['has_photo'] ?? false,
            ]);

            // Extract portrait if photo detected
            if (! empty($ocrData['has_photo']) && ! empty($ocrData['photo_bbox'])) {
                $this->extractAndSavePortrait($ocrData['photo_bbox'], $ocrData['photo_description'] ?? null);
            }

            // Dispatch death_date extraction job (step 2)
            ExtractDeathDateAndAnnouncementJob::dispatch($this->deathNotice, $this->imagePath, false);

            Log::info("Dispatched ExtractDeathDateAndAnnouncementJob for DeathNotice {$this->deathNotice->hash}");
        } catch (\Exception $e) {
            Log::error("Image extraction failed for DeathNotice {$this->deathNotice->hash}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            // Clean up temp file only if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                if (file_exists($this->imagePath)) {
                    unlink($this->imagePath);
                    Log::debug("Cleaned up temporary image after final failure: {$this->imagePath}");
                }
            }

            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Extract and save portrait photograph from parte image.
     */
    private function extractAndSavePortrait(array $bbox, ?string $description): void
    {
        try {
            $portraitService = app(PortraitExtractionService::class);

            // Extract portrait from parte image
            $portraitPath = $portraitService->extractPortrait($this->imagePath, $bbox);

            if ($portraitPath && file_exists($portraitPath)) {
                // Check if portrait already exists and delete it
                $existingPortrait = $this->deathNotice->getFirstMedia('portrait');
                if ($existingPortrait) {
                    Log::info("Deleting existing portrait before adding new one for DeathNotice {$this->deathNotice->hash}", [
                        'existing_portrait_id' => $existingPortrait->id,
                        'existing_portrait_path' => $existingPortrait->getPath(),
                    ]);
                    $existingPortrait->delete();
                }

                // Save to media library
                $this->deathNotice
                    ->addMedia($portraitPath)
                    ->withCustomProperties([
                        'description' => $description,
                        'extracted_at' => now()->toIso8601String(),
                    ])
                    ->toMediaCollection('portrait');

                // Cleanup temp file
                @unlink($portraitPath);

                Log::info("Portrait extracted successfully for DeathNotice {$this->deathNotice->hash}", [
                    'bbox' => $bbox,
                    'description' => $description,
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail the entire job if portrait extraction fails (non-critical)
            Log::warning("Portrait extraction failed (non-critical) for DeathNotice {$this->deathNotice->hash}", [
                'error' => $e->getMessage(),
                'bbox' => $bbox,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExtractImageParteJob permanently failed for DeathNotice {$this->deathNotice->hash}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Clean up temporary image file on final failure
        if (file_exists($this->imagePath)) {
            unlink($this->imagePath);
            Log::debug("Cleaned up temporary image on job failure: {$this->imagePath}");
        }
    }
}
