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

        if (! $crawler) {
            return $notices;
        }

        try {
            // Stránka PS Hajdukova má každé parte jako h3 (jméno)
            // následované odstavcem s datem a odkazem "smuteční oznámení" na PDF
            $crawler->filter('h3')->each(function ($headingNode) use (&$notices) {
                try {
                    $fullName = trim($headingNode->text());
                    if ($fullName === '') {
                        return;
                    }

                    $nameParts = $this->parseName($fullName);

                    // Datum pohřbu je v následujícím <p> za h3
                    $dateText = '';
                    $nextParagraph = $headingNode->nextAll()->filter('p')->first();
                    if ($nextParagraph->count() > 0) {
                        $dateText = trim($nextParagraph->text());
                    }

                    $funeralDate = $this->parseDate($dateText);

                    // Najdi odkaz s textem "smuteční oznámení" (PDF)
                    $pdfLink = $headingNode->nextAll()->filter('a')->reduce(function ($node) {
                        $text = trim($node->text());

                        return str_contains(mb_strtolower($text), 'smuteční oznámení')
                            || str_contains(mb_strtolower($text), 'smutecni oznameni');
                    })->first();

                    if ($pdfLink->count() === 0) {
                        // Fallback: jakýkoli odkaz na .pdf v blízkosti
                        $pdfLink = $headingNode->nextAll()->filter('a')->reduce(function ($node) {
                            $href = $node->attr('href') ?? '';

                            return str_ends_with(strtolower($href), '.pdf');
                        })->first();
                    }

                    if ($pdfLink->count() === 0) {
                        return;
                    }

                    $href = $pdfLink->attr('href');
                    if (empty($href)) {
                        return;
                    }

                    $pdfUrl = str_starts_with($href, 'http') ? $href : 'https://pshajdukova.cz'.$href;

                    // Extract death_date and funeral_date from PDF
                    $deathDate = null;
                    $pdfText = $this->extractPdfText($pdfUrl);
                    if ($pdfText) {
                        $dates = $this->parseDatesFromParteText($pdfText);
                        $deathDate = $dates['death_date'];
                        // Override funeral_date if found in PDF
                        if ($dates['funeral_date']) {
                            $funeralDate = $dates['funeral_date'];
                        }
                    }

                    $noticeData = [
                        'full_name' => $nameParts['full_name'],
                        'death_date' => $deathDate,
                        'funeral_date' => $funeralDate,
                        'source' => $this->source,
                        'source_url' => $pdfUrl,
                        'pdf_url' => $pdfUrl,
                    ];

                    $noticeData['hash'] = $this->generateHash($noticeData);

                    if (! $this->noticeExists($noticeData['hash'])) {
                        $notices[] = $noticeData;
                    }
                } catch (\Exception $e) {
                    Log::warning("Error parsing PS Hajdukova notice: {$e->getMessage()}");
                }
            });
        } catch (\Exception $e) {
            Log::error("Error scraping {$this->source}: {$e->getMessage()}");
        }

        return $notices;
    }

    /**
     * Parse date from Czech text using Carbon
     */
    private function parseDate(string $dateText): ?string
    {
        if (empty($dateText)) {
            return null;
        }

        try {
            // Extract numeric date format: d.m.Y or dd.mm.YYYY (e.g., "2. 1. 2026")
            if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/', $dateText, $matches)) {
                return Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
            }

            // Try Carbon's intelligent parsing with Czech locale
            Carbon::setLocale('cs');
            $parsed = Carbon::parse($dateText);

            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Failed to parse date from text: {$dateText}");

            return null;
        }
    }
}
