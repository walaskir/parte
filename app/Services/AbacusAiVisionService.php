<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abacus.AI Vision OCR Service
 * Unified API wrapper for multiple vision models via Abacus.AI
 */
class AbacusAiVisionService
{
    private string $apiKey;

    private string $baseUrl;

    private int $timeout;

    // Available models
    public const MODEL_GEMINI_3_FLASH = 'GEMINI-3-FLASH-PREVIEW';

    public const MODEL_CLAUDE_SONNET_45 = 'CLAUDE-SONNET-4-5-20250929';

    public const MODEL_GEMINI_25_PRO = 'GEMINI-2.5-PRO';

    public const MODEL_GPT_52 = 'GPT-5.2';

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        int $timeout = 90
    ) {
        $this->apiKey = $apiKey ?? config('services.abacusai.api_key');
        $this->baseUrl = $baseUrl ?? config('services.abacusai.base_url', 'https://routellm.abacus.ai');
        $this->timeout = $timeout;
    }

    /**
     * Extract text and structured data from death notice image
     *
     * @param  string  $imagePath  Path to image file
     * @param  string  $model  Model to use (default: GEMINI-3-FLASH-PREVIEW)
     * @return array{full_name: string, death_date: ?string, funeral_date: ?string, announcement_text: string}
     *
     * @throws \Exception
     */
    public function extractDeathNotice(string $imagePath, string $model = self::MODEL_GEMINI_3_FLASH): array
    {
        $imageBase64 = base64_encode(file_get_contents($imagePath));

        $prompt = 'Extract information from this Czech/Polish death notice (parte). '.
            'Return ONLY valid JSON with these exact fields: '.
            '{"full_name": string, "death_date": "YYYY-MM-DD" or null, "funeral_date": "YYYY-MM-DD" or null, '.
            '"announcement_text": string with COMPLETE announcement INCLUDING funeral details}. '.
            'The announcement_text must include ALL text from the notice. '.
            'Preserve Czech/Polish diacritics (á,č,ď,é,ě,í,ň,ó,ř,š,ť,ú,ů,ý,ž,ą,ć,ę,ł,ń,ó,ś,ź,ż). '.
            'Fix any OCR errors. Return ONLY the JSON object, no other text.';

        $result = $this->callApi($model, $prompt, $imageBase64);

        return $this->parseTextExtraction($result['content']);
    }

    /**
     * Call Abacus.AI API
     *
     * @param  string  $model  Model identifier
     * @param  string  $prompt  Text prompt
     * @param  string  $imageBase64  Base64-encoded image
     * @return array{content: string, duration: float, tokens: array}
     *
     * @throws \Exception
     */
    private function callApi(string $model, string $prompt, string $imageBase64): array
    {
        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl.'/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/jpeg;base64,{$imageBase64}",
                                ],
                            ],
                        ],
                    ],
                ],
                'temperature' => 0.0,
                'response_format' => ['type' => 'json'],
            ]);

        $duration = microtime(true) - $startTime;

        if (! $response->successful()) {
            throw new \Exception(
                "Abacus.AI API error: HTTP {$response->status()} - {$response->body()}"
            );
        }

        $data = $response->json();

        if (! isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid Abacus.AI API response structure');
        }

        return [
            'content' => $data['choices'][0]['message']['content'],
            'duration' => round($duration, 2),
            'tokens' => $data['usage'] ?? [],
        ];
    }

    /**
     * Parse text extraction response
     */
    private function parseTextExtraction(string $content): array
    {
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $content);

        $data = json_decode($content, true);

        if (! $data || ! isset($data['full_name'])) {
            throw new \Exception('Failed to parse text extraction response');
        }

        // Validate opening_quote length
        if (isset($data['opening_quote']) && $data['opening_quote'] && strlen($data['opening_quote']) > 500) {
            Log::warning('Opening quote suspiciously long, might be full announcement', [
                'length' => strlen($data['opening_quote']),
                'preview' => substr($data['opening_quote'], 0, 100),
            ]);
        }

        return [
            'full_name' => $this->cleanFullName($data['full_name']),
            'opening_quote' => $data['opening_quote'] ?? null,
            'death_date' => $data['death_date'] ?? null,
            'funeral_date' => $data['funeral_date'] ?? null,
            'announcement_text' => $data['announcement_text'] ?? '',
        ];
    }

    /**
     * Clean full_name by removing Polish deceased prefix
     */
    private function cleanFullName(string $name): string
    {
        // Remove Polish "śp." prefix (deceased marker)
        // Also Czech/Slovak variants
        $prefixes = ['śp. ', 'sp. ', 'ś.p. ', 'Śp. ', 'Sp. ', 'Ś.p. '];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }

        return trim($name);
    }

    /**
     * Get available models
     */
    public static function getAvailableModels(): array
    {
        return [
            self::MODEL_GEMINI_3_FLASH => [
                'name' => 'Gemini 3 Flash Preview',
                'speed' => 'fast',
                'quality' => 'high',
                'usage' => 'unlimited',
                'recommended_for' => ['text_extraction', 'high_volume'],
            ],
            self::MODEL_CLAUDE_SONNET_45 => [
                'name' => 'Claude Sonnet 4.5',
                'speed' => 'medium',
                'quality' => 'highest',
                'usage' => 'limited',
                'recommended_for' => ['complex_documents'],
            ],
            self::MODEL_GEMINI_25_PRO => [
                'name' => 'Gemini 2.5 Pro',
                'speed' => 'slow',
                'quality' => 'highest',
                'usage' => 'unlimited',
                'recommended_for' => ['validation', 'quality_check'],
            ],
            self::MODEL_GPT_52 => [
                'name' => 'GPT-5.2',
                'speed' => 'medium',
                'quality' => 'medium',
                'usage' => 'limited',
                'recommended_for' => ['fallback'],
                'warning' => 'May have diacritic errors on Czech/Polish text',
            ],
        ];
    }
}
