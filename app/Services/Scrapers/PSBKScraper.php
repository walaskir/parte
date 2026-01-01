<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;

class PSBKScraper extends AbstractScraper
{
    protected string $source = 'PS BK';

    protected string $url = 'https://psbk.cz/parte/';

    public function scrape(): array
    {
        $notices = [];
        $crawler = $this->fetchContent($this->url);

        if (! $crawler) {
            return $notices;
        }

        try {
            // Find all parte images (skip logos)
            $crawler->filter('img')->each(function ($imgNode) use (&$notices) {
                try {
                    $imageUrl = $imgNode->attr('src');

                    if (empty($imageUrl)) {
                        return;
                    }

                    // Skip logo images
                    if (str_contains($imageUrl, 'logo')) {
                        return;
                    }

                    // Make absolute URL
                    if (! str_starts_with($imageUrl, 'http')) {
                        $imageUrl = 'https://psbk.cz'.$imageUrl;
                    }

                    // Try to extract name from alt or title attribute first
                    $fullName = $imgNode->attr('alt') ?? $imgNode->attr('title') ?? '';

                    // If we still don't have a name, generate placeholder from URL
                    // (OCR will extract proper name later in DeathNoticeService)
                    if (empty($fullName)) {
                        $basename = basename($imageUrl, '.png');
                        $basename = basename($basename, '.jpg');
                        $fullName = 'Parte '.$basename;
                    }

                    $nameParts = $this->parseName($fullName);
                    $fullName = $nameParts['full_name'];

                    // PS BK doesn't provide dates on the main page
                    // OCR will extract death_date and funeral_date from the image later
                    $deathDate = null;
                    $funeralDate = null;

                    $noticeData = [
                        'full_name' => $fullName,
                        'funeral_date' => $funeralDate,
                        'source' => $this->source,
                        'source_url' => $imageUrl, // Direct image URL for source tracking
                        'image_url' => $imageUrl,
                        'requires_ocr' => true, // Flag for DeathNoticeService
                        'death_date' => null, // OCR will extract this from image
                    ];

                    $noticeData['hash'] = $this->generateHash($noticeData);

                    if (! $this->noticeExists($noticeData['hash'])) {
                        $notices[] = $noticeData;
                    }
                } catch (\Exception $e) {
                    Log::warning("Error parsing {$this->source} notice item: {$e->getMessage()}");
                }
            });
        } catch (\Exception $e) {
            Log::error("Error scraping {$this->source}: {$e->getMessage()}");
        }

        return $notices;
    }
}
