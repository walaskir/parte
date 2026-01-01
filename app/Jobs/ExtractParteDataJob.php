<?php

namespace App\Jobs;

use App\Models\DeathNotice;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractParteDataJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 3;

    /**
     * Number of seconds before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeathNotice $deathNotice,
        public string $imagePath
    ) {}

    /**
     * Execute the job - extract data from parte image using OCR.
     */
    public function handle(GeminiService $geminiService): void
    {
        Log::info("Starting OCR extraction for DeathNotice {$this->deathNotice->hash}", [
            'id' => $this->deathNotice->id,
            'hash' => $this->deathNotice->hash,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (! file_exists($this->imagePath)) {
                Log::error("Image file not found for OCR extraction: {$this->imagePath}");
                throw new \Exception("Image file not found: {$this->imagePath}");
            }

            // Extract data using OCR
            $ocrData = $geminiService->extractFromImage($this->imagePath);

            if (! $ocrData || ! isset($ocrData['full_name']) || ! $ocrData['full_name']) {
                Log::warning("OCR extraction returned no valid data for DeathNotice {$this->deathNotice->hash}", [
                    'ocr_data' => $ocrData,
                    'attempt' => $this->attempts(),
                ]);

                // Throw exception to trigger retry
                throw new \Exception('OCR extraction returned no valid data');
            }

            // Update the death notice with extracted data
            $this->deathNotice->update([
                'full_name' => $ocrData['full_name'],
                'funeral_date' => $ocrData['funeral_date'] ?? $this->deathNotice->funeral_date,
            ]);

            Log::info("Successfully extracted data for DeathNotice {$this->deathNotice->hash}", [
                'full_name' => $ocrData['full_name'],
                'funeral_date' => $ocrData['funeral_date'],
            ]);
            // Clean up temporary image file after successful extraction
            if (file_exists($this->imagePath)) {
                unlink($this->imagePath);
                Log::debug("Cleaned up temporary image after successful extraction: {$this->imagePath}");
            }
        } catch (\Exception $e) {
            Log::error("OCR extraction failed for DeathNotice {$this->deathNotice->hash}", [
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
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExtractParteDataJob permanently failed for DeathNotice {$this->deathNotice->hash}", [
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
