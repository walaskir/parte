<?php

namespace App\Jobs;

use App\Models\DeathNotice;
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

    public const FIELD_ALL = 'all';

    /**
     * Create a new job instance.
     *
     * @param  string|null  $tempImagePath  Optional temp image path for PDF-based notices (overrides model's image_path)
     * @param  array<string>  $fieldsToExtract  Fields to extract: 'all', 'full_name', 'opening_quote', 'death_date', 'announcement_text'
     */
    public function __construct(
        public DeathNotice $deathNotice,
        public ?string $tempImagePath = null,
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
        // Priority: 1. temp path (PDF-based), 2. MediaLibrary original_image (PS BK)
        $imagePath = $this->tempImagePath;
        $isTemporary = $this->tempImagePath !== null;

        if (! $imagePath) {
            // Try original_image from MediaLibrary (PS BK records)
            $media = $this->deathNotice->getFirstMedia('original_image');
            $imagePath = $media?->getPath();
        }

        Log::info("Starting extraction for DeathNotice {$this->deathNotice->hash}", [
            'id' => $this->deathNotice->id,
            'hash' => $this->deathNotice->hash,
            'source' => $this->deathNotice->source,
            'image_path' => $imagePath,
            'fields_to_extract' => $this->fieldsToExtract,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (! $imagePath || ! file_exists($imagePath)) {
                Log::error('Image file not found for extraction', [
                    'image_path' => $imagePath,
                    'temp_path' => $this->tempImagePath,
                    'has_original_image_media' => $this->deathNotice->getFirstMedia('original_image') !== null,
                ]);
                throw new \Exception("Image file not found: {$imagePath}");
            }

            // Extract text data with known name context
            $ocrData = $visionOcrService->extractTextFromImage(
                $imagePath,
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

            if (! empty($updateData)) {
                $this->deathNotice->update($updateData);

                Log::info("Successfully extracted data for DeathNotice {$this->deathNotice->hash}", [
                    'fields_extracted' => array_keys($updateData),
                    'death_date' => $ocrData['death_date'] ?? null,
                    'announcement_text' => isset($ocrData['announcement_text']) ? substr($ocrData['announcement_text'], 0, 100).'...' : null,
                    'full_name' => $ocrData['full_name'] ?? null,
                    'opening_quote' => isset($ocrData['opening_quote']) ? substr($ocrData['opening_quote'], 0, 50).'...' : null,
                ]);
            } else {
                Log::warning("No fields to update for DeathNotice {$this->deathNotice->hash}", [
                    'attempt' => $this->attempts(),
                ]);
            }

            // Clean up temporary image file (only for PDF-based notices with temp paths)
            if ($isTemporary && $imagePath && file_exists($imagePath)) {
                unlink($imagePath);
                Log::debug("Cleaned up temporary image after extraction: {$imagePath}");
            }
        } catch (\Exception $e) {
            Log::error("Extraction failed for DeathNotice {$this->deathNotice->hash}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            // Clean up temp file only if we've exhausted retries
            if ($isTemporary && $this->attempts() >= $this->tries && $imagePath && file_exists($imagePath)) {
                unlink($imagePath);
                Log::debug("Cleaned up temporary image after final failure: {$imagePath}");
            }

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

        Log::error("ExtractDeathDateAndAnnouncementJob permanently failed for DeathNotice {$this->deathNotice->hash}", [
            'error' => $exception->getMessage(),
            'media_path' => $media?->getPath(),
            'temp_image_path' => $this->tempImagePath,
        ]);

        // Clean up temporary image file on final failure (only for PDF-based notices)
        if ($this->tempImagePath && file_exists($this->tempImagePath)) {
            unlink($this->tempImagePath);
            Log::debug("Cleaned up temporary image on job failure: {$this->tempImagePath}");
        }
    }
}
