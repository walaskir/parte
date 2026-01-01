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
}
