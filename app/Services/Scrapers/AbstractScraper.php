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

    /**
     * Fetch HTML content from URL
     */
    protected function fetchContent(string $url): ?Crawler
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get($url);

            if ($response->successful()) {
                return new Crawler($response->body());
            }

            Log::warning("Failed to fetch {$url}: {$response->status()}");
            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Generate hash from notice data
     */
    protected function generateHash(array $data): string
    {
        $hashString = implode('|', [
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['funeral_date'] ?? '',
            $data['source_url'] ?? '',
        ]);

        return substr(hash('sha256', $hashString), 0, 12);
    }

    /**
     * Check if notice already exists
     */
    protected function noticeExists(string $hash): bool
    {
        return DeathNotice::where('hash', $hash)->exists();
    }

    /**
     * Parse name into first name and last name
     */
    protected function parseName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? $parts[0] ?? '',
        ];
    }

    /**
     * Get the source name
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Get the source URL
     */
    public function getUrl(): string
    {
        return $this->url;
    }
}
