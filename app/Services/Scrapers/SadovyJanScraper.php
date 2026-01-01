<?php

namespace App\Services\Scrapers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SadovyJanScraper extends AbstractScraper
{
    protected string $source = 'Sadový Jan';
    protected string $url = 'https://www.sadovyjan.cz/parte/';

    public function scrape(): array
    {
        $notices = [];
        $crawler = $this->fetchContent($this->url);

        if (!$crawler) {
            return $notices;
        }

        try {
            // Find all death notices on the page
            $crawler->filter('.parte-item, .obituary-item, article')->each(function ($node) use (&$notices) {
                try {
                    // Extract name - adjust selector based on actual HTML structure
                    $nameElement = $node->filter('h2, h3, .name, .parte-name')->first();
                    if ($nameElement->count() === 0) {
                        return;
                    }

                    $fullName = trim($nameElement->text());
                    $nameParts = $this->parseName($fullName);

                    // Extract funeral date - adjust selector based on actual HTML structure
                    $dateText = '';
                    $dateElement = $node->filter('.date, .funeral-date, time')->first();
                    if ($dateElement->count() > 0) {
                        $dateText = trim($dateElement->text());
                    }

                    // Parse date
                    $funeralDate = $this->parseDate($dateText);

                    // Extract link/URL
                    $sourceUrl = $this->url;
                    $linkElement = $node->filter('a')->first();
                    if ($linkElement->count() > 0) {
                        $href = $linkElement->attr('href');
                        $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://www.sadovyjan.cz' . $href;
                    }

                    $noticeData = [
                        'first_name' => $nameParts['first_name'],
                        'last_name' => $nameParts['last_name'],
                        'funeral_date' => $funeralDate,
                        'source' => $this->source,
                        'source_url' => $sourceUrl,
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
            // Try to parse Czech date format
            $czechMonths = [
                'ledna' => '01', 'února' => '02', 'března' => '03', 'dubna' => '04',
                'května' => '05', 'června' => '06', 'července' => '07', 'srpna' => '08',
                'září' => '09', 'října' => '10', 'listopadu' => '11', 'prosince' => '12',
            ];

            foreach ($czechMonths as $czech => $month) {
                $dateText = str_ireplace($czech, $month, $dateText);
            }

            // Try to parse date
            $parsed = Carbon::parse($dateText);
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
