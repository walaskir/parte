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

class ExtractDeathDateAndAnnouncementJob implements ShouldQueue
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
        public string $imagePath,
        public bool $portraitsOnly = false
    ) {
        $this->onQueue('extraction');
    }

    /**
     * Execute the job - extract death_date from parte image using OCR.
     */
    public function handle(VisionOcrService $visionOcrService): void
    {
        Log::info("Starting death_date extraction for DeathNotice {$this->deathNotice->hash}", [
            'id' => $this->deathNotice->id,
            'hash' => $this->deathNotice->hash,
            'source' => $this->deathNotice->source,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (! file_exists($this->imagePath)) {
                Log::error("Image file not found for death_date extraction: {$this->imagePath}");
                throw new \Exception("Image file not found: {$this->imagePath}");
            }

            // Extract ONLY death_date
            $ocrData = $visionOcrService->extractFromImage($this->imagePath, extractDeathDate: true);

            // Extract text data ONLY if NOT portraits-only mode
            if (! $this->portraitsOnly) {
                // Update death_date, announcement_text, and has_photo (keep existing full_name and funeral_date)
                $updateData = [];
                if (isset($ocrData['death_date']) && $ocrData['death_date']) {
                    $updateData['death_date'] = $ocrData['death_date'];
                }
                if (isset($ocrData['announcement_text']) && $ocrData['announcement_text']) {
                    $updateData['announcement_text'] = $ocrData['announcement_text'];
                }
                if (isset($ocrData['has_photo'])) {
                    $updateData['has_photo'] = (bool) $ocrData['has_photo'];
                }

                if (! empty($updateData)) {
                    $this->deathNotice->update($updateData);

                    Log::info("Successfully extracted death_date and announcement_text for DeathNotice {$this->deathNotice->hash}", [
                        'death_date' => $ocrData['death_date'] ?? null,
                        'announcement_text' => isset($ocrData['announcement_text']) ? substr($ocrData['announcement_text'], 0, 100).'...' : null,
                        'has_photo' => $ocrData['has_photo'] ?? false,
                    ]);
                } else {
                    Log::warning("No death_date found in OCR data for DeathNotice {$this->deathNotice->hash}", [
                        'attempt' => $this->attempts(),
                    ]);
                }
            } else {
                // Portraits-only mode: only update has_photo flag
                $this->deathNotice->update([
                    'has_photo' => (bool) ($ocrData['has_photo'] ?? false),
                ]);

                Log::info("Portraits-only mode: skipped text extraction for DeathNotice {$this->deathNotice->hash}", [
                    'has_photo' => $ocrData['has_photo'] ?? false,
                ]);
            }

            // Extract portrait if photo detected and portrait extraction is enabled
            if (config('services.parte.extract_portraits', true) && ! empty($ocrData['has_photo']) && ! empty($ocrData['photo_bbox'])) {
                $this->extractAndSavePortrait($ocrData['photo_bbox'], $ocrData['photo_description'] ?? null);
            }

            // Clean up temporary image file after successful extraction
            if (file_exists($this->imagePath)) {
                unlink($this->imagePath);
                Log::debug("Cleaned up temporary image after death_date extraction: {$this->imagePath}");
            }
        } catch (\Exception $e) {
            Log::error("Death_date extraction failed for DeathNotice {$this->deathNotice->hash}", [
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
            // Check if portrait already exists
            $existingPortrait = $this->deathNotice->getFirstMedia('portrait');

            if ($existingPortrait) {
                Log::info('Replacing existing portrait', [
                    'notice_hash' => $this->deathNotice->hash,
                    'old_portrait' => $existingPortrait->file_name,
                ]);

                // Delete old portrait (Spatie will handle this automatically with singleFile)
                $existingPortrait->delete();
            }

            $portraitService = app(PortraitExtractionService::class);

            // Extract portrait from parte image
            $portraitPath = $portraitService->extractPortrait($this->imagePath, $bbox);

            if ($portraitPath && file_exists($portraitPath)) {
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
        Log::error("ExtractDeathDateAndAnnouncementJob permanently failed for DeathNotice {$this->deathNotice->hash}", [
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
