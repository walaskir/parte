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

                    // Try to extract name from alt or title attribute
                    $fullName = $imgNode->attr('alt') ?? $imgNode->attr('title') ?? '';

                    // If we still don't have a name, generate one from the URL
                    if (empty($fullName)) {
                        $basename = basename($imageUrl, '.png');
                        $basename = basename($basename, '.jpg');
                        $fullName = 'Parte '.$basename;
                    }

                    $nameParts = $this->parseName($fullName);
                    $fullName = $nameParts['full_name'];

                    // PS BK doesn't provide funeral dates on the main page
                    $funeralDate = null;

                    $noticeData = [
                        'full_name' => $fullName,
                        'funeral_date' => $funeralDate,
                        'source' => $this->source,
                        'source_url' => $this->url,
                        'image_url' => $imageUrl,
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
