<?php

namespace App\Console\Commands;

use App\Models\DeathNotice;
use App\Services\VisionOcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ExtractOpeningQuotesCommand extends Command
{
    protected $signature = 'parte:extract-opening-quotes
        {--limit=10 : Number of records to process}
        {--force : Re-extract even if opening_quote exists}';

    protected $description = 'Extract opening_quote from existing death notice PDFs using AI vision';

    public function handle(VisionOcrService $visionService): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $query = DeathNotice::query()->has('media');

        if ($force) {
            $this->warn('⚠️  FORCE mode: Re-extracting opening_quote from ALL parte...');
            $query->whereNotNull('id'); // All records with media
        } else {
            $this->info('Extracting opening_quote from parte without opening_quote...');
            $query->whereNull('opening_quote');
        }

        $notices = $query->limit($limit)->get();

        if ($notices->isEmpty()) {
            $this->info('No notices found to process.');

            return CommandAlias::SUCCESS;
        }

        $this->info("Found {$notices->count()} notices to process (limit: {$limit}).");
        $bar = $this->output->createProgressBar($notices->count());

        $processed = 0;
        $skipped = 0;
        $failed = 0;

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

            try {
                // Convert PDF to JPG
                $tempImagePath = $this->convertPdfToJpg($pdfPath);

                if (! $tempImagePath) {
                    $this->warn("Failed to convert PDF to JPG for {$notice->full_name}");
                    $failed++;
                    $bar->advance();

                    continue;
                }

                // Extract text using VisionOcrService
                $extractedData = $visionService->extractTextFromImage($tempImagePath, $notice->full_name);

                // Cleanup temp file
                if (file_exists($tempImagePath)) {
                    unlink($tempImagePath);
                }

                if (! $extractedData) {
                    $this->warn("AI extraction failed for {$notice->full_name}");
                    $failed++;
                    $bar->advance();

                    continue;
                }

                // Update record with new data
                $updateData = [];

                // Debug log what we got from extraction
                Log::info('ExtractOpeningQuotesCommand: Extraction result', [
                    'hash' => $notice->hash,
                    'has_opening_quote' => isset($extractedData['opening_quote']),
                    'opening_quote_value' => $extractedData['opening_quote'] ?? 'NOT_SET',
                    'opening_quote_length' => isset($extractedData['opening_quote']) && $extractedData['opening_quote'] ? strlen($extractedData['opening_quote']) : 0,
                    'ann_text_preview' => isset($extractedData['announcement_text']) ? substr($extractedData['announcement_text'], 0, 80) : 'NOT_SET',
                ]);

                if (isset($extractedData['opening_quote']) && $extractedData['opening_quote'] !== null) {
                    $updateData['opening_quote'] = $extractedData['opening_quote'];
                }

                if (isset($extractedData['full_name']) && $extractedData['full_name']) {
                    $updateData['full_name'] = $extractedData['full_name'];
                }

                // Update announcement_text if it was re-extracted (cleaned from opening_quote)
                if (isset($extractedData['announcement_text'])) {
                    $updateData['announcement_text'] = $extractedData['announcement_text'];
                }

                // Update death_date if missing
                if (empty($notice->death_date) && isset($extractedData['death_date'])) {
                    $updateData['death_date'] = $extractedData['death_date'];
                }

                if (! empty($updateData)) {
                    $notice->update($updateData);
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to process notice {$notice->hash}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Processing complete!');
        $this->info("- Successfully processed: {$processed}");
        $this->info("- Skipped: {$skipped}");
        $this->info("- Failed: {$failed}");

        return CommandAlias::SUCCESS;
    }

    /**
     * Convert PDF to JPG using ImageMagick
     */
    private function convertPdfToJpg(string $pdfPath): ?string
    {
        $tempImagePath = storage_path('app/temp/extract_opening_quote_'.uniqid().'.jpg');
        $tempDir = dirname($tempImagePath);

        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            $imagick = new \Imagick;
            $imagick->setResolution(300, 300); // DPI 300 for OCR quality
            $imagick->readImage($pdfPath.'[0]'); // Read first page only
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(90);
            $imagick->writeImage($tempImagePath);
            $imagick->clear();
            $imagick->destroy();

            if (! file_exists($tempImagePath)) {
                return null;
            }

            return $tempImagePath;
        } catch (\Exception $e) {
            if (file_exists($tempImagePath)) {
                unlink($tempImagePath);
            }

            throw $e;
        }
    }
}
