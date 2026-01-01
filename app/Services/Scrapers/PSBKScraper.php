<?php

namespace App\Services\Scrapers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class PSBKScraper extends AbstractScraper
{
    protected string $source = 'PS BK';
    protected string $url = 'https://psbk.cz/parte/';

    public function scrape(): array
    {
        $notices = [];
        $crawler = $this->fetchContent($this->url);

        if (!$crawler) {
            return $notices;
        }

        try {
            // Find all death notices (images) on the page
            $crawler->filter('.parte-item, .obituary-item, article, .notice, img.parte')->each(function ($node) use (&$notices) {
                try {
                    // Check if this is an image element
                    $imgElement = $node->nodeName() === 'img' ? $node : $node->filter('img')->first();

                    if ($imgElement->count() === 0) {
                        return;
                    }

                    $imageUrl = $imgElement->attr('src');
                    if (empty($imageUrl)) {
                        return;
                    }

                    // Make absolute URL
                    if (!str_starts_with($imageUrl, 'http')) {
                        $imageUrl = 'https://psbk.cz' . $imageUrl;
                    }

                    // Try to extract name from alt or title attribute
                    $fullName = $imgElement->attr('alt') ?? $imgElement->attr('title') ?? '';

                    // Also check for text nearby
                    if (empty($fullName)) {
                        $textElement = $node->filter('h2, h3, .name, .parte-name')->first();
                        if ($textElement->count() > 0) {
                            $fullName = trim($textElement->text());
                        }
                    }

                    // If we still don't have a name, generate one from the URL
                    if (empty($fullName)) {
                        $fullName = 'Oznámení ' . basename($imageUrl);
                    }

                    $nameParts = $this->parseName($fullName);

                    // Extract funeral date if available
                    $dateText = '';
                    $dateElement = $node->filter('.date, .funeral-date, time')->first();
                    if ($dateElement->count() > 0) {
                        $dateText = trim($dateElement->text());
                    }

                    $funeralDate = $this->parseDate($dateText);

                    // Extract link/URL
                    $sourceUrl = $this->url;
                    $linkElement = $node->filter('a')->first();
                    if ($linkElement->count() > 0) {
                        $href = $linkElement->attr('href');
                        $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://psbk.cz' . $href;
                    }

                    $noticeData = [
                        'first_name' => $nameParts['first_name'],
                        'last_name' => $nameParts['last_name'],
                        'funeral_date' => $funeralDate,
                        'source' => $this->source,
                        'source_url' => $sourceUrl,
                        'image_url' => $imageUrl,
                    ];

                    $noticeData['hash'] = $this->generateHash($noticeData);

                    if (!$this->noticeExists($noticeData['hash'])) {
                        $notices[] = $noticeData;
                    }
                } catch (\Exception $e) {
                    Log::warning("Error parsing notice item: {$e->getMessage()}");
                }
            });
        } catch (\Exception $e) {
            Log::error("Error scraping {$this->source}: {$e->getMessage()}");
        }

        return $notices;
    }

    /**
     * Parse date from Czech text
     */
    private function parseDate(string $dateText): ?string
    {
        if (empty($dateText)) {
            return null;
        }

        try {
            $czechMonths = [
                'ledna' => '01', 'února' => '02', 'března' => '03', 'dubna' => '04',
                'května' => '05', 'června' => '06', 'července' => '07', 'srpna' => '08',
                'září' => '09', 'října' => '10', 'listopadu' => '11', 'prosince' => '12',
            ];

            foreach ($czechMonths as $czech => $month) {
                $dateText = str_ireplace($czech, $month, $dateText);
            }

            $parsed = Carbon::parse($dateText);
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
