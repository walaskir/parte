<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionOcrService
{
    private ?string $zhipuaiApiKey;

    private string $zhipuaiModel;

    private string $zhipuaiBaseUrl;

    private ?string $anthropicApiKey;

    public function __construct()
    {
        $this->zhipuaiApiKey = config('services.zhipuai.api_key');
        $this->zhipuaiModel = config('services.zhipuai.model');
        $this->zhipuaiBaseUrl = config('services.zhipuai.base_url');

        $this->anthropicApiKey = config('services.anthropic.api_key');

        if (empty($this->zhipuaiApiKey)) {
            throw new Exception('ZhipuAI API key is not configured. Please set ZHIPUAI_API_KEY in .env file.');
        }
    }

    /**
     * Main extraction method - delegates to ZhipuAI or Anthropic
     */
    public function extractFromImage(string $imagePath, bool $extractDeathDate = false): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                Log::error('Image file not found for extraction', ['path' => $imagePath]);

                return null;
            }

            Log::info('VisionOcrService: Starting extraction', [
                'image_path' => $imagePath,
                'extract_mode' => $extractDeathDate ? 'death_date' : 'name+funeral_date',
            ]);

            // Priority 1: ZhipuAI GLM-4V (primary)
            $result = $this->extractWithZhipuAI($imagePath);

            if ($result && $this->validateExtraction($result, $extractDeathDate)) {
                Log::info('VisionOcrService: ZhipuAI extraction successful');

                return $result;
            }

            // Priority 2: Anthropic Claude (fallback)
            if ($this->anthropicApiKey) {
                Log::warning('VisionOcrService: ZhipuAI failed, trying Anthropic fallback');

                $result = $this->extractWithAnthropic($imagePath);

                if ($result && $this->validateExtraction($result, $extractDeathDate)) {
                    Log::info('VisionOcrService: Anthropic extraction successful');

                    return $result;
                }
            }

            Log::error('VisionOcrService: All extraction methods failed', ['image_path' => $imagePath]);

            return null;

        } catch (Exception $e) {
            Log::error('VisionOcrService extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Extract using ZhipuAI GLM-4V
     */
    private function extractWithZhipuAI(string $imagePath): ?array
    {
        try {
            $startTime = microtime(true);

            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);

            $prompt = $this->getExtractionPrompt();

            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->zhipuaiApiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->zhipuaiBaseUrl}/chat/completions", [
                    'model' => $this->zhipuaiModel,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $base64Image,
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('ZhipuAI API request failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $responseText = $data['choices'][0]['message']['content'] ?? null;

            if (! $responseText) {
                Log::warning('ZhipuAI returned empty response', ['data' => $data]);

                return null;
            }

            $json = $this->extractJson($responseText);

            if (! $json) {
                Log::warning('ZhipuAI returned invalid JSON', ['response' => $responseText]);

                return null;
            }

            $result = $this->cleanExtractionResult($json);

            $duration = microtime(true) - $startTime;
            Log::info('ZhipuAI extraction completed', [
                'duration_seconds' => round($duration, 2),
                'has_name' => isset($result['full_name']) && $result['full_name'],
                'has_death_date' => isset($result['death_date']) && $result['death_date'],
                'has_funeral_date' => isset($result['funeral_date']) && $result['funeral_date'],
                'has_announcement' => isset($result['announcement_text']) && $result['announcement_text'],
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('ZhipuAI extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Extract using Anthropic Claude (fallback)
     */
    private function extractWithAnthropic(string $imagePath): ?array
    {
        try {
            $startTime = microtime(true);

            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = $this->getMimeType($imagePath);

            $prompt = $this->getExtractionPrompt();
            $config = config('services.anthropic');

            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $this->anthropicApiKey,
                    'anthropic-version' => $config['version'],
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $config['model'],
                    'max_tokens' => $config['max_tokens'],
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image',
                                    'source' => [
                                        'type' => 'base64',
                                        'media_type' => $mimeType,
                                        'data' => $base64Image,
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Anthropic API request failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $responseText = $data['content'][0]['text'] ?? null;

            if (! $responseText) {
                Log::warning('Anthropic returned empty response', ['data' => $data]);

                return null;
            }

            $json = $this->extractJson($responseText);

            if (! $json) {
                Log::warning('Anthropic returned invalid JSON', ['response' => $responseText]);

                return null;
            }

            $result = $this->cleanExtractionResult($json);

            $duration = microtime(true) - $startTime;
            Log::info('Anthropic extraction completed', [
                'duration_seconds' => round($duration, 2),
                'has_name' => isset($result['full_name']) && $result['full_name'],
                'has_death_date' => isset($result['death_date']) && $result['death_date'],
                'has_funeral_date' => isset($result['funeral_date']) && $result['funeral_date'],
                'has_announcement' => isset($result['announcement_text']) && $result['announcement_text'],
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Anthropic extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Universal extraction prompt for both APIs
     */
    private function getExtractionPrompt(): string
    {
        return "Analyze this death notice (parte/obituary) image. Extract ALL available information in JSON format:

{
  \"full_name\": \"Full name of the deceased (first name + last name, e.g. 'Jan Novák')\",
  \"death_date\": \"Date of death in YYYY-MM-DD format (or null if not found)\",
  \"funeral_date\": \"Date of funeral ceremony in YYYY-MM-DD format (or null if not found)\",
  \"announcement_text\": \"Complete announcement text including funeral details (or null if not extractable)\"
}

EXTRACTION RULES:

1. FULL NAME:
   - Look after: 'paní', 'pan', 'panem' (Czech), 'Sp.', 'śp.', '§p.' (Polish)
   - Combine first + last name into single field
   - Fix OCR errors (e.g., 'Novdk' → 'Novák')

2. DEATH DATE:
   - Keywords: 'zemřel/a', '†', 'zmarł/a', 'data śmierci', 'dne', 'dnia'
   - Formats: 'DD.MM.YYYY', 'D. MONTH YYYY' (e.g., '31. prosince 2025')
   - Convert to YYYY-MM-DD

3. FUNERAL DATE:
   - Keywords: 'pohřeb', 'rozloučení', 'pogrzeb', 'pożegnanie', 'obřad se koná'
   - Same formats as death_date
   - Convert to YYYY-MM-DD

4. ANNOUNCEMENT TEXT:
   - Extract complete announcement: opening → names → dates → funeral details → closing
   - INCLUDE: Full text, relationships, addresses, funeral time/location/venue
   - EXCLUDE: Biblical verses BEFORE announcement, decorative borders
   - Fix OCR errors: 'nds'→'nás', remove garbage ('R ża ©')
   - Return as continuous text with single spaces

Languages: Czech or Polish
Return ONLY valid JSON, nothing else.";
    }

    /**
     * Clean and validate extraction result
     */
    private function cleanExtractionResult(array $json): array
    {
        $result = [
            'full_name' => $json['full_name'] ?? null,
            'death_date' => $json['death_date'] ?? null,
            'funeral_date' => $json['funeral_date'] ?? null,
            'announcement_text' => null,
        ];

        if (isset($json['announcement_text']) && $json['announcement_text']) {
            $cleaned = preg_replace('/\s+/', ' ', trim($json['announcement_text']));

            if (strlen($cleaned) < 50) {
                Log::warning('AI returned suspiciously short announcement_text', [
                    'length' => strlen($cleaned),
                    'text' => $cleaned,
                ]);
            } elseif ($cleaned !== 'null') {
                $result['announcement_text'] = $cleaned;
            }
        }

        return $result;
    }

    /**
     * Validate extraction based on mode
     */
    private function validateExtraction(array $result, bool $extractDeathDate): bool
    {
        if ($extractDeathDate) {
            return isset($result['death_date']) && $result['death_date'];
        } else {
            return isset($result['full_name']) && $result['full_name'];
        }
    }

    /**
     * Extract JSON from AI response
     */
    private function extractJson(string $text): ?array
    {
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        $json = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return null;
    }

    /**
     * Get MIME type for image
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
