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
        $sources = $sources ?? array_keys($this->scrapers);
        $results = [
            'total' => 0,
            'new' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];

        foreach ($sources as $source) {
            if (!isset($this->scrapers[$source])) {
                Log::warning("Unknown source: {$source}");
                continue;
            }

            try {
                $scraper = new $this->scrapers[$source]();
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
                // Create the notice record
                $notice = DeathNotice::create([
                    'hash' => $data['hash'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'funeral_date' => $data['funeral_date'] ?? null,
                    'source' => $data['source'],
                    'source_url' => $data['source_url'],
                ]);

                // Generate and attach PDF
                $pdfPath = $this->generatePdf($data);

                if ($pdfPath) {
                    $notice->addMedia($pdfPath)
                        ->toMediaCollection('pdf');

                    // Clean up temporary file
                    if (Storage::disk('local')->exists($pdfPath)) {
                        Storage::disk('local')->delete($pdfPath);
                    }
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
    private function generatePdf(array $data): ?string
    {
        try {
            $tempPath = 'temp/' . uniqid('parte_') . '.pdf';
            $fullPath = Storage::disk('local')->path($tempPath);

            // Ensure temp directory exists
            Storage::disk('local')->makeDirectory('temp');

            // If this is PS BK with an image URL, download and convert to PDF
            if (isset($data['image_url'])) {
                return $this->convertImageToPdf($data['image_url'], $fullPath);
            }

            // Otherwise, generate HTML-based PDF
            return $this->generateHtmlPdf($data, $fullPath);
        } catch (\Exception $e) {
            Log::error("Error generating PDF: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Convert image to PDF using Browsershot
     */
    private function convertImageToPdf(string $imageUrl, string $outputPath): ?string
    {
        try {
            // Download the image first
            $response = Http::timeout(30)->get($imageUrl);

            if (!$response->successful()) {
                Log::warning("Failed to download image: {$imageUrl}");
                return null;
            }

            $imageContent = $response->body();
            $imagePath = Storage::disk('local')->path('temp/' . uniqid('img_') . '.jpg');
            file_put_contents($imagePath, $imageContent);

            // Create HTML with the image
            $html = "
                <html>
                <head>
                    <style>
                        body { margin: 0; padding: 0; }
                        img { width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    <img src='file://{$imagePath}' />
                </body>
                </html>
            ";

            Browsershot::html($html)
                ->format('A4')
                ->margins(0, 0, 0, 0)
                ->save($outputPath);

            // Clean up temporary image
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            return basename($outputPath);
        } catch (\Exception $e) {
            Log::error("Error converting image to PDF: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Generate HTML-based PDF
     */
    private function generateHtmlPdf(array $data, string $outputPath): ?string
    {
        try {
            $html = view('pdf.death-notice', $data)->render();

            Browsershot::html($html)
                ->format('A4')
                ->margins(20, 20, 20, 20)
                ->save($outputPath);

            return basename($outputPath);
        } catch (\Exception $e) {
            Log::error("Error generating HTML PDF: {$e->getMessage()}");
            return null;
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
