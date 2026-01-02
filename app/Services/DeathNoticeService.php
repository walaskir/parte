<?php

namespace App\Services;

use App\Models\DeathNotice;
use App\Services\Scrapers\PSBKScraper;
use App\Services\Scrapers\PSHajdukovaScraper;
use App\Services\Scrapers\SadovyJanScraper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Spatie\Browsershot\Browsershot;

class DeathNoticeService
{
    private array $scrapers = [
        'sadovy-jan' => SadovyJanScraper::class,
        'pshajdukova' => PSHajdukovaScraper::class,
        'psbk' => PSBKScraper::class,
    ];

    /**
     * Download notices from specified sources
     */
    public function downloadNotices(?array $sources = null): array
    {
        // Load active funeral services from database
        $funeralServices = \App\Models\FuneralService::where('active', true)->get();

        $availableSources = $funeralServices->mapWithKeys(function ($service) {
            $scraperClass = match ($service->slug) {
                'sadovy-jan' => SadovyJanScraper::class,
                'pshajdukova' => PSHajdukovaScraper::class,
                'psbk' => PSBKScraper::class,
                default => null,
            };

            return $scraperClass ? [$service->slug => $scraperClass] : [];
        })->toArray();

        if ($sources === null) {
            $sources = array_keys($availableSources);
        }

        $results = [
            'total' => 0,
            'new' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];

        foreach ($sources as $sourceKey) {
            if (! isset($availableSources[$sourceKey])) {
                Log::warning("Unknown source: {$sourceKey}");

                continue;
            }

            $scraperClass = $availableSources[$sourceKey];
            $scraper = new $scraperClass;

            try {
                $notices = $scraper->scrape();

                $results['total'] += count($notices);

                foreach ($notices as $noticeData) {
                    try {
                        if (DeathNotice::where('hash', $noticeData['hash'])->exists()) {
                            $results['duplicates']++;

                            continue;
                        }

                        $notice = $this->createNotice($noticeData);

                        if ($notice) {
                            $results['new']++;
                            Log::info("Created notice: {$notice->full_name} (hash: {$notice->hash})");
                        } else {
                            $results['errors']++;
                        }
                    } catch (\Exception $e) {
                        $results['errors']++;
                        Log::error("Error creating notice: {$e->getMessage()}");
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error scraping {$source}: {$e->getMessage()}");
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Create a death notice record
     */
    private function createNotice(array $data): ?DeathNotice
    {
        try {
            return DB::transaction(function () use ($data) {
                $notice = DeathNotice::create([
                    'hash' => $data['hash'],
                    'full_name' => $data['full_name'],
                    'funeral_date' => $data['funeral_date'] ?? null,
                    'source' => $data['source'],
                    'source_url' => $data['source_url'],
                ]);

                $pdfPath = $this->generatePdf($data, $notice);

                if ($pdfPath && file_exists($pdfPath)) {
                    // Extract original filename from PDF URL if available
                    $originalName = isset($data['pdf_url'])
                        ? basename(parse_url($data['pdf_url'], PHP_URL_PATH))
                        : null;

                    $mediaAdder = $notice->addMedia($pdfPath);

                    if ($originalName) {
                        $mediaAdder->usingFileName($originalName);
                    }

                    $mediaAdder->toMediaCollection('pdf');
                }

                return $notice;
            });
        } catch (\Exception $e) {
            Log::error("Error creating notice: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Generate PDF from notice data
     */
    private function generatePdf(array $data, DeathNotice $notice): ?string
    {
        try {
            // Use temp directory for initial PDF generation
            $tempPath = 'temp/'.uniqid('parte_').'.pdf';
            $fullPath = Storage::disk('local')->path($tempPath);

            // Ensure temp directory exists
            Storage::disk('local')->makeDirectory('temp');

            // Pokud máme přímo PDF odkaz (např. Sadový Jan), stáhneme originální PDF
            if (isset($data['pdf_url'])) {
                return $this->downloadOriginalPdf($data['pdf_url'], $fullPath, $notice) ? $fullPath : null;
            }

            // Pokud máme obrázek (např. PS BK), převedeme jej na PDF
            if (isset($data['image_url'])) {
                return $this->convertImageToPdf($data['image_url'], $fullPath, $notice) ? $fullPath : null;
            }

            // Jinak vygenerujeme PDF z HTML šablony
            return $this->generateHtmlPdf($data, $fullPath) ? $fullPath : null;
        } catch (\Exception $e) {
            Log::error("Error generating PDF: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Convert image to PDF using Browsershot
     */
    private function convertImageToPdf(string $imageUrl, string $outputPath, ?DeathNotice $notice = null): bool
    {
        try {
            $response = Http::timeout(30)->get($imageUrl);

            if (! $response->successful()) {
                Log::warning("Failed to download image: {$imageUrl}");

                return false;
            }

            $imageContent = $response->body();

            // Determine image type from URL or content
            $imageType = 'image/jpeg';
            if (str_contains($imageUrl, '.png')) {
                $imageType = 'image/png';
            } elseif (str_contains($imageUrl, '.gif')) {
                $imageType = 'image/gif';
            }

            // Save temporary image for OCR processing via queue
            if ($notice) {
                $tempImagePath = Storage::disk('local')->path('temp/'.uniqid('ocr_image_').'.jpg');
                file_put_contents($tempImagePath, $imageContent);

                // Dispatch ExtractImageParteJob for PS BK (name + funeral_date)
                \App\Jobs\ExtractImageParteJob::dispatch($notice, $tempImagePath);

                Log::info("Dispatched ExtractImageParteJob for PS BK notice {$notice->hash}");
            }

            // Encode image as base64
            $base64Image = base64_encode($imageContent);
            $dataUri = "data:{$imageType};base64,{$base64Image}";

            $html = "
                <html>
                <head>
                    <style>
                        body { margin: 0; padding: 0; }
                        img { width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <img src='{$dataUri}' />
                </body>
                </html>
            ";

            Browsershot::html($html)
                ->format('A4')
                ->margins(0, 0, 0, 0)
                ->save($outputPath);

            return true;
        } catch (\Exception $e) {
            Log::error("Error converting image to PDF: {$e->getMessage()}");

            return false;
        }
    }

    private function downloadOriginalPdf(string $pdfUrl, string $outputPath, ?DeathNotice $notice = null): bool
    {
        try {
            $response = Http::timeout(30)->get($pdfUrl);

            if (! $response->successful()) {
                Log::warning("Failed to download PDF: {$pdfUrl}");

                return false;
            }

            file_put_contents($outputPath, $response->body());

            // Dispatch death_date extraction job for PDF-based parte (Sadovy Jan, PS Hajdukova)
            if ($notice) {
                $this->dispatchPdfOcrJob($notice, $outputPath);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error downloading original PDF: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Dispatch OCR extraction job for PDF file
     */
    private function dispatchPdfOcrJob(DeathNotice $notice, string $pdfPath): void
    {
        try {
            // Convert PDF first page to image for OCR using Imagick
            $tempImagePath = Storage::disk('local')->path('temp/'.uniqid('ocr_pdf_').'.jpg');
            $tempDir = dirname($tempImagePath);

            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $imagick = new Imagick;
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdfPath.'[0]'); // Read first page only
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(90);
            $imagick->writeImage($tempImagePath);
            $imagick->clear();
            $imagick->destroy();

            if (! file_exists($tempImagePath)) {
                Log::warning('Failed to convert PDF to JPG for OCR', [
                    'notice_hash' => $notice->hash,
                    'pdf' => $pdfPath,
                ]);

                return;
            }

            // Dispatch death_date extraction job to queue (asynchronous with retry)
            \App\Jobs\ExtractDeathDateAndAnnouncementJob::dispatch($notice, $tempImagePath);

            Log::info("Dispatched death_date extraction job for PDF-based notice {$notice->hash}");
        } catch (\Exception $e) {
            Log::warning("Failed to dispatch PDF OCR job: {$e->getMessage()}");
        }
    }

    /**
     * Generate HTML-based PDF
     */
    private function generateHtmlPdf(array $data, string $outputPath): bool
    {
        try {
            $html = view('pdf.death-notice', $data)->render();

            Browsershot::html($html)
                ->format('A4')
                ->margins(20, 20, 20, 20)
                ->save($outputPath);

            return true;
        } catch (\Exception $e) {
            Log::error("Error generating HTML PDF: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Get available sources
     */
    public function getAvailableSources(): array
    {
        return array_keys($this->scrapers);
    }
}
