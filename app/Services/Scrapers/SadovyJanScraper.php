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

        if (! $crawler) {
            return $notices;
        }

        try {
            // Stránka Sadový Jan má každé parte jako blok s h2 (jméno)
            // a následným odkazem "parte : ..." vedoucím na PDF.
            $crawler->filter('main')->each(function ($mainNode) use (&$notices) {
                try {
                    // Najdi všechny nadpisy h2 = jednotlivá parte
                    $mainNode->filter('h2')->each(function ($headingNode) use (&$notices, $mainNode) {
                        try {
                            $fullName = trim($headingNode->text());
                            if ($fullName === '') {
                                return;
                            }

                            $nameParts = $this->parseName($fullName);

                            // Datum pohřbu je typicky v nejbližším <p> po h2
                            $dateText = '';
                            $nextParagraph = $headingNode->nextAll()->filter('p')->first();
                            if ($nextParagraph->count() > 0) {
                                $dateText = trim($nextParagraph->text());
                            }

                            $funeralDate = $this->parseDate($dateText);

                            // Odpovídající odkaz na PDF: text obsahuje "parte" a jméno
                            $pdfLink = $mainNode->filter('a')->reduce(function ($node) use ($fullName) {
                                $text = trim($node->text());

                                return str_contains(mb_strtolower($text), 'parte')
                                    && str_contains(mb_strtolower($text), mb_strtolower($fullName));
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

                            $pdfUrl = str_starts_with($href, 'http') ? $href : 'https://www.sadovyjan.cz'.$href;

                            $noticeData = [
                                'full_name' => $nameParts['full_name'],
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
                            Log::warning("Error parsing Sadovy Jan notice: {$e->getMessage()}");
                        }
                    });
                } catch (\Exception $e) {
                    Log::warning("Error parsing Sadovy Jan block: {$e->getMessage()}");
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
            // Extract numeric date format: d.m.Y or dd.mm.YYYY (e.g., "31.12.2025" or "2.1.2026")
            if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $dateText, $matches)) {
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
