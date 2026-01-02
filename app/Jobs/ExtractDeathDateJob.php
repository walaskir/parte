<?php

namespace App\Jobs;

use App\Models\DeathNotice;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractDeathDateJob implements ShouldQueue
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
     * Seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeathNotice $deathNotice,
        public string $imagePath
    ) {}

    /**
     * Execute the job - extract death_date from parte image using OCR.
     */
    public function handle(GeminiService $geminiService): void
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
            $ocrData = $geminiService->extractFromImage($this->imagePath, extractDeathDate: true);

            // Update only death_date (keep existing full_name and funeral_date)
            $updateData = [];
            if (isset($ocrData['death_date']) && $ocrData['death_date']) {
                $updateData['death_date'] = $ocrData['death_date'];
            }

            if (! empty($updateData)) {
                $this->deathNotice->update($updateData);

                Log::info("Successfully extracted death_date for DeathNotice {$this->deathNotice->hash}", [
                    'death_date' => $ocrData['death_date'] ?? null,
                ]);
            } else {
                Log::warning("No death_date found in OCR data for DeathNotice {$this->deathNotice->hash}", [
                    'attempt' => $this->attempts(),
                ]);
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
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExtractDeathDateJob permanently failed for DeathNotice {$this->deathNotice->hash}", [
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
