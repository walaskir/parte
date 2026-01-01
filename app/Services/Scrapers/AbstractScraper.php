<?php

namespace App\Services\Scrapers;

use App\Models\DeathNotice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

abstract class AbstractScraper
{
    protected string $source;

    protected string $url;

    abstract public function scrape(): array;

    protected function fetchContent(string $url): ?Crawler
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning("Failed to fetch {$url}: {$response->status()}");

                return null;
            }

            return new Crawler($response->body());
        } catch (\Exception $e) {
            Log::error("Error fetching {$url}: {$e->getMessage()}");

            return null;
        }
    }

    protected function generateHash(array $data): string
    {
        $hashString = implode('|', [
            $data['full_name'] ?? '',
            $data['funeral_date'] ?? '',
            $data['source_url'] ?? '',
        ]);

        return substr(hash('sha256', $hashString), 0, 12);
    }

    protected function parseName(string $fullName): array
    {
        return ['full_name' => trim($fullName)];
    }

    protected function noticeExists(string $hash): bool
    {
        return DeathNotice::where('hash', $hash)->exists();
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Extract text content from PDF URL
     */
    protected function extractPdfText(string $pdfUrl): ?string
    {
        try {
            // Add headers to avoid 403 Forbidden from servers
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => parse_url($pdfUrl, PHP_URL_SCHEME).'://'.parse_url($pdfUrl, PHP_URL_HOST).'/',
                ])
                ->get($pdfUrl);

            if (! $response->successful()) {
                Log::warning("Failed to download PDF from {$pdfUrl}: {$response->status()}");

                return null;
            }

            $tempFile = storage_path('app/temp/pdf_'.uniqid().'.pdf');
            $tempDir = dirname($tempFile);

            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            file_put_contents($tempFile, $response->body());

            $parser = new \Smalot\PdfParser\Parser;
            $pdf = $parser->parseFile($tempFile);
            $text = $pdf->getText();

            // Cleanup temp file
            unlink($tempFile);

            return $text;
        } catch (\Exception $e) {
            Log::warning("Error extracting text from PDF {$pdfUrl}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Parse death date and funeral date from Czech/Polish parte text
     * Returns ['death_date' => Y-m-d|null, 'funeral_date' => Y-m-d|null]
     */
    protected function parseDatesFromParteText(string $text): array
    {
        $deathDate = null;
        $funeralDate = null;

        try {
            // Look for death date patterns (Czech: "zemřel/a dne", "†", Polish: "zmarł/a dnia", "data śmierci")
            if (preg_match('/(?:zemřel[a]?\s+(?:dne\s+)?|†\s*|zmarł[a]?\s+(?:dnia\s+)?|data\s+śmierci[:\s]+)(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/iu', $text, $matches)) {
                $deathDate = \Carbon\Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
            }

            // Look for funeral date patterns (Czech: "pohřeb", "rozloučení", Polish: "pogrzeb", "pożegnanie")
            if (preg_match('/(?:pohřeb|rozloučení|pogrzeb|pożegnanie)[^\d]*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/iu', $text, $matches)) {
                $funeralDate = \Carbon\Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
            }

            // If we didn't find specific patterns, try to extract all dates and use heuristics
            if (! $deathDate && ! $funeralDate) {
                preg_match_all('/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/', $text, $allMatches, PREG_SET_ORDER);

                if (count($allMatches) >= 2) {
                    // First date is typically death date, second is funeral date
                    $deathDate = \Carbon\Carbon::createFromFormat('j.n.Y', "{$allMatches[0][1]}.{$allMatches[0][2]}.{$allMatches[0][3]}")->format('Y-m-d');
                    $funeralDate = \Carbon\Carbon::createFromFormat('j.n.Y', "{$allMatches[1][1]}.{$allMatches[1][2]}.{$allMatches[1][3]}")->format('Y-m-d');
                } elseif (count($allMatches) === 1) {
                    // Only one date - assume it's funeral date
                    $funeralDate = \Carbon\Carbon::createFromFormat('j.n.Y', "{$allMatches[0][1]}.{$allMatches[0][2]}.{$allMatches[0][3]}")->format('Y-m-d');
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error parsing dates from parte text: {$e->getMessage()}");
        }

        return [
            'death_date' => $deathDate,
            'funeral_date' => $funeralDate,
        ];
    }
}
