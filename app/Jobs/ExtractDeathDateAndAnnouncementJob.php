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
     * Available fields for extraction.
     */
    public const FIELD_FULL_NAME = 'full_name';

    public const FIELD_OPENING_QUOTE = 'opening_quote';

    public const FIELD_DEATH_DATE = 'death_date';

    public const FIELD_ANNOUNCEMENT_TEXT = 'announcement_text';

    public const FIELD_PORTRAIT = 'portrait';

    public const FIELD_ALL = 'all';

    /**
     * Create a new job instance.
     *
     * @param  array<string>  $fieldsToExtract  Fields to extract: 'all', 'full_name', 'opening_quote', 'death_date', 'announcement_text', 'portrait'
     */
    public function __construct(
        public DeathNotice $deathNotice,
        public string $imagePath,
        public array $fieldsToExtract = [self::FIELD_ALL]
    ) {
        $this->onQueue('extraction');
    }

    /**
     * Check if a specific field should be extracted.
     */
    private function shouldExtractField(string $field): bool
    {
        return in_array(self::FIELD_ALL, $this->fieldsToExtract) || in_array($field, $this->fieldsToExtract);
    }

    /**
     * Check if any text field should be extracted.
     */
    private function shouldExtractTextFields(): bool
    {
        return $this->shouldExtractField(self::FIELD_FULL_NAME)
            || $this->shouldExtractField(self::FIELD_OPENING_QUOTE)
            || $this->shouldExtractField(self::FIELD_DEATH_DATE)
            || $this->shouldExtractField(self::FIELD_ANNOUNCEMENT_TEXT);
    }

    /**
     * Execute the job - extract data from parte image using OCR.
     */
    public function handle(VisionOcrService $visionOcrService): void
    {
        Log::info("Starting extraction for DeathNotice {$this->deathNotice->hash}", [
            'id' => $this->deathNotice->id,
            'hash' => $this->deathNotice->hash,
            'source' => $this->deathNotice->source,
            'fields_to_extract' => $this->fieldsToExtract,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (! file_exists($this->imagePath)) {
                Log::error("Image file not found for extraction: {$this->imagePath}");
                throw new \Exception("Image file not found: {$this->imagePath}");
            }

            // Extract text data with known name context
            $ocrData = $visionOcrService->extractTextFromImage(
                $this->imagePath,
                $this->deathNotice->full_name
            );

            // Build update data based on selected fields (always force rewrite selected fields)
            $updateData = [];

            if ($this->shouldExtractField(self::FIELD_FULL_NAME)) {
                $updateData['full_name'] = $ocrData['full_name'] ?? $this->deathNotice->full_name;
            }

            if ($this->shouldExtractField(self::FIELD_OPENING_QUOTE)) {
                $updateData['opening_quote'] = $ocrData['opening_quote'] ?? null;
            }

            if ($this->shouldExtractField(self::FIELD_DEATH_DATE)) {
                $updateData['death_date'] = $ocrData['death_date'] ?? null;
            }

            if ($this->shouldExtractField(self::FIELD_ANNOUNCEMENT_TEXT)) {
                $updateData['announcement_text'] = $ocrData['announcement_text'] ?? null;
            }

            // Always update has_photo if any text field is being extracted
            if ($this->shouldExtractTextFields() && isset($ocrData['has_photo'])) {
                $updateData['has_photo'] = (bool) $ocrData['has_photo'];
            }

            if (! empty($updateData)) {
                $this->deathNotice->update($updateData);

                Log::info("Successfully extracted data for DeathNotice {$this->deathNotice->hash}", [
                    'fields_extracted' => array_keys($updateData),
                    'death_date' => $ocrData['death_date'] ?? null,
                    'announcement_text' => isset($ocrData['announcement_text']) ? substr($ocrData['announcement_text'], 0, 100).'...' : null,
                    'has_photo' => $ocrData['has_photo'] ?? false,
                    'full_name' => $ocrData['full_name'] ?? null,
                    'opening_quote' => isset($ocrData['opening_quote']) ? substr($ocrData['opening_quote'], 0, 50).'...' : null,
                ]);
            } else {
                Log::warning("No fields to update for DeathNotice {$this->deathNotice->hash}", [
                    'attempt' => $this->attempts(),
                ]);
            }

            // Extract portrait if requested and photo detected
            $shouldExtractPortrait = $this->shouldExtractField(self::FIELD_PORTRAIT);
            if ($shouldExtractPortrait && config('services.parte.extract_portraits', true) && ! empty($ocrData['has_photo']) && ! empty($ocrData['photo_bbox'])) {
                $this->extractAndSavePortrait($ocrData['photo_bbox'], $ocrData['photo_description'] ?? null);
            }

            // Clean up temporary image file after successful extraction
            if (file_exists($this->imagePath)) {
                unlink($this->imagePath);
                Log::debug("Cleaned up temporary image after extraction: {$this->imagePath}");
            }
        } catch (\Exception $e) {
            Log::error("Extraction failed for DeathNotice {$this->deathNotice->hash}", [
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
