<?php

namespace App\Services\Scrapers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PSHajdukovaScraper extends AbstractScraper
{
    protected string $source = 'PS Hajdukova';
    protected string $url = 'https://pshajdukova.cz/smutecni-obrady-parte/';

    public function scrape(): array
    {
        $notices = [];
        $crawler = $this->fetchContent($this->url);

        if (!$crawler) {
            return $notices;
        }

        try {
            // Find all death notices on the page
            $crawler->filter('.parte-item, .obituary-item, article, .notice')->each(function ($node) use (&$notices) {
                try {
                    // Extract name
                    $nameElement = $node->filter('h2, h3, .name, .parte-name, .title')->first();
                    if ($nameElement->count() === 0) {
                        return;
                    }

                    $fullName = trim($nameElement->text());
                    $nameParts = $this->parseName($fullName);

                    // Extract funeral date
                    $dateText = '';
                    $dateElement = $node->filter('.date, .funeral-date, time, .obrad-date')->first();
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
                        $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://pshajdukova.cz' . $href;
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
