<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionOcrService
{
    private string $primaryProvider;

    private ?string $fallbackProvider;

    private ?string $geminiApiKey;

    private ?string $zhipuaiApiKey;

    private ?string $anthropicApiKey;

    public function __construct()
    {
        $this->primaryProvider = config('services.vision.provider', 'gemini');
        $this->fallbackProvider = config('services.vision.fallback_provider');

        $this->geminiApiKey = config('services.gemini.api_key');
        $this->zhipuaiApiKey = config('services.zhipuai.api_key');
        $this->anthropicApiKey = config('services.anthropic.api_key');

        $configuredProviders = $this->getAllConfiguredProviders();

        if (empty($configuredProviders)) {
            throw new Exception('No vision providers configured. Please set at least one provider API key (GEMINI_API_KEY, ZHIPUAI_API_KEY, or ANTHROPIC_API_KEY) in .env file.');
        }

        if (! $this->isProviderConfigured($this->primaryProvider)) {
            throw new Exception("Primary vision provider '{$this->primaryProvider}' is not configured. Please set the API key in .env file or change VISION_PROVIDER.");
        }
    }

    /**
     * Main extraction method - delegates to configured providers
     */
    public function extractFromImage(string $imagePath, bool $extractDeathDate = false, ?string $knownName = null): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                Log::error('Image file not found for extraction', ['path' => $imagePath]);

                return null;
            }

            Log::info('VisionOcrService: Starting extraction', [
                'image_path' => $imagePath,
                'extract_mode' => $extractDeathDate ? 'death_date' : 'name+funeral_date',
                'known_name' => $knownName,
                'primary_provider' => $this->primaryProvider,
                'fallback_provider' => $this->fallbackProvider,
            ]);

            // Try primary provider
            $result = $this->extractWithProvider($this->primaryProvider, $imagePath, $knownName);

            if ($result && $this->validateExtraction($result, $extractDeathDate)) {
                Log::info('VisionOcrService: Primary provider extraction successful', [
                    'provider' => $this->primaryProvider,
                ]);

                // Two-phase detection: If no photo detected, try photo-only mode
                if (! ($result['has_photo'] ?? false)) {
                    Log::info('VisionOcrService: No photo detected, trying photo-only extraction mode');

                    $photoResult = $this->extractPhotoOnly($imagePath);

                    if ($photoResult && ($photoResult['has_photo'] ?? false)) {
                        Log::info('VisionOcrService: Photo-only mode detected photo', [
                            'photo_bbox' => $photoResult['photo_bbox'] ?? null,
                        ]);

                        // Merge photo data into main result
                        $result['has_photo'] = $photoResult['has_photo'];
                        $result['photo_bbox'] = $photoResult['photo_bbox'] ?? null;
                        $result['photo_description'] = $photoResult['photo_description'] ?? null;
                    }
                }

                return $result;
            }

            // Try fallback provider
            if ($this->fallbackProvider && $this->isProviderConfigured($this->fallbackProvider)) {
                Log::warning('VisionOcrService: Primary provider failed, trying fallback', [
                    'primary' => $this->primaryProvider,
                    'fallback' => $this->fallbackProvider,
                ]);

                $result = $this->extractWithProvider($this->fallbackProvider, $imagePath, $knownName);

                if ($result && $this->validateExtraction($result, $extractDeathDate)) {
                    Log::info('VisionOcrService: Fallback provider extraction successful', [
                        'provider' => $this->fallbackProvider,
                    ]);

                    return $result;
                }
            }

            // Try all remaining configured providers as last resort
            $triedProviders = [$this->primaryProvider, $this->fallbackProvider];
            $remainingProviders = array_diff($this->getAllConfiguredProviders(), $triedProviders);

            foreach ($remainingProviders as $provider) {
                Log::warning('VisionOcrService: Trying remaining provider', ['provider' => $provider]);

                $result = $this->extractWithProvider($provider, $imagePath, $knownName);

                if ($result && $this->validateExtraction($result, $extractDeathDate)) {
                    Log::info('VisionOcrService: Remaining provider extraction successful', [
                        'provider' => $provider,
                    ]);

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
     * Extract with specific provider
     */
    private function extractWithProvider(string $provider, string $imagePath, ?string $knownName): ?array
    {
        return match ($provider) {
            'gemini' => $this->extractWithGemini($imagePath, $knownName),
            'zhipuai' => $this->extractWithZhipuAI($imagePath, $knownName),
            'anthropic' => $this->extractWithAnthropic($imagePath, $knownName),
            default => throw new Exception("Unknown vision provider: {$provider}")
        };
    }

    /**
     * Check if provider is configured
     */
    private function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'gemini' => ! empty($this->geminiApiKey),
            'zhipuai' => ! empty($this->zhipuaiApiKey),
            'anthropic' => ! empty($this->anthropicApiKey),
            default => false
        };
    }

    /**
     * Get all configured providers
     */
    private function getAllConfiguredProviders(): array
    {
        $providers = [];

        if ($this->isProviderConfigured('gemini')) {
            $providers[] = 'gemini';
        }

        if ($this->isProviderConfigured('zhipuai')) {
            $providers[] = 'zhipuai';
        }

        if ($this->isProviderConfigured('anthropic')) {
            $providers[] = 'anthropic';
        }

        return $providers;
    }

    /**
     * Extract using Google Gemini
     */
    private function extractWithGemini(string $imagePath, ?string $knownName = null): ?array
    {
        try {
            $startTime = microtime(true);

            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = $this->getMimeType($imagePath);

            $prompt = $this->getExtractionPrompt($knownName);
            $config = config('services.gemini');

            $response = Http::timeout(90)
                ->withHeaders([
                    'x-goog-api-key' => $this->geminiApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$config['base_url']}/models/{$config['model']}:generateContent", [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Image,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3, // Lower temperature for accurate text extraction
                        'topK' => 40,
                        'topP' => 0.95,
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Gemini API request failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! $responseText) {
                Log::warning('Gemini returned empty response', ['data' => $data]);

                return null;
            }

            $json = $this->extractJson($responseText);

            if (! $json) {
                Log::warning('Gemini returned invalid JSON', ['response' => $responseText]);

                return null;
            }

            $result = $this->cleanExtractionResult($json, $knownName);

            $duration = microtime(true) - $startTime;
            Log::info('Gemini extraction completed', [
                'duration_seconds' => round($duration, 2),
                'has_name' => isset($result['full_name']) && $result['full_name'],
                'has_death_date' => isset($result['death_date']) && $result['death_date'],
                'has_funeral_date' => isset($result['funeral_date']) && $result['funeral_date'],
                'has_announcement' => isset($result['announcement_text']) && $result['announcement_text'],
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Gemini extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Extract using ZhipuAI GLM-4V
     */
    private function extractWithZhipuAI(string $imagePath, ?string $knownName = null): ?array
    {
        try {
            $startTime = microtime(true);

            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);

            $prompt = $this->getExtractionPrompt($knownName);
            $config = config('services.zhipuai');

            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->zhipuaiApiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$config['base_url']}/chat/completions", [
                    'model' => $config['model'],
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

            $result = $this->cleanExtractionResult($json, $knownName);

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
    private function extractWithAnthropic(string $imagePath, ?string $knownName = null): ?array
    {
        try {
            $startTime = microtime(true);

            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = $this->getMimeType($imagePath);

            $prompt = $this->getExtractionPrompt($knownName);
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

            $result = $this->cleanExtractionResult($json, $knownName);

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
     * Photo-only extraction mode - focused solely on detecting portrait photos.
     * Used as fallback when main extraction fails to detect photo.
     *
     * @param  string  $imagePath  Path to parte image
     * @return array|null Result with has_photo, photo_bbox, photo_description or null
     */
    private function extractPhotoOnly(string $imagePath): ?array
    {
        try {
            // Try with primary provider first
            $providers = [$this->primaryProvider];

            // Add fallback and other providers
            if ($this->fallbackProvider && $this->isProviderConfigured($this->fallbackProvider)) {
                $providers[] = $this->fallbackProvider;
            }

            $remainingProviders = array_diff($this->getAllConfiguredProviders(), $providers);
            $providers = array_merge($providers, $remainingProviders);

            foreach ($providers as $provider) {
                Log::info('VisionOcrService: Photo-only mode trying provider', ['provider' => $provider]);

                $result = match ($provider) {
                    'gemini' => $this->extractPhotoOnlyWithGemini($imagePath),
                    'zhipuai' => $this->extractPhotoOnlyWithZhipuAI($imagePath),
                    'anthropic' => $this->extractPhotoOnlyWithAnthropic($imagePath),
                    default => null
                };

                if ($result && ($result['has_photo'] ?? false)) {
                    Log::info('VisionOcrService: Photo-only mode found photo', [
                        'provider' => $provider,
                        'bbox' => $result['photo_bbox'] ?? null,
                    ]);

                    return $result;
                }
            }

            Log::warning('VisionOcrService: Photo-only mode failed with all providers');

            return null;

        } catch (Exception $e) {
            Log::error('VisionOcrService: Photo-only extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Get prompt focused solely on portrait photo detection.
     */
    private function getPhotoOnlyPrompt(): string
    {
        return "**TASK: Detect portrait photograph in Czech/Polish death notice (parte)**

**YOUR ONLY JOB: Find the portrait photo (if present)**

Portrait characteristics:
• Formal photograph showing deceased person's face/head
• Usually black & white or sepia-toned
• Often has thin decorative border
• Common positions:
  - Top-right: X=65-95%, Y=5-20%
  - Top-center: X=35-65%, Y=5-20%
  - Top-left: X=5-30%, Y=5-20%
• Typical size: 15-30% width, 15-35% height

**DETECTION RULES:**
1. HIGH SENSITIVITY: Prefer false positives over missing real photos
2. Look for ANY rectangular region containing a human face/portrait
3. IGNORE text, decorative elements, logos (unless they contain a portrait)
4. If you see a face photograph, ALWAYS report it
5. Bounding box: Measure from page top-left corner (0,0) to bottom-right (100,100)

**RESPONSE FORMAT (JSON only, no explanation):**

If photo found:
{
  \"has_photo\": true,
  \"photo_bbox\": {
    \"x_percent\": 76.5,
    \"y_percent\": 8.2,
    \"width_percent\": 18.3,
    \"height_percent\": 32.1
  },
  \"photo_description\": \"elderly woman with glasses, formal portrait, black and white\"
}

If NO photo:
{
  \"has_photo\": false
}

**CRITICAL: Respond ONLY with valid JSON. No explanations, no markdown code blocks.**";
    }

    /**
     * Photo-only extraction using Gemini API.
     */
    private function extractPhotoOnlyWithGemini(string $imagePath): ?array
    {
        if (! $this->geminiApiKey) {
            return null;
        }

        try {
            $mimeType = $this->getMimeType($imagePath);
            $imageData = base64_encode(file_get_contents($imagePath));

            $response = Http::timeout(90)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model', 'gemini-2.0-flash-exp').":generateContent?key={$this->geminiApiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $this->getPhotoOnlyPrompt(),
                                ],
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $imageData,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.5, // Higher sensitivity for photo detection
                        'topK' => 40,
                        'topP' => 0.95,
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $json = $this->extractJson($text);

            return $json;

        } catch (Exception $e) {
            Log::error('Gemini photo-only extraction failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Photo-only extraction using ZhipuAI API.
     */
    private function extractPhotoOnlyWithZhipuAI(string $imagePath): ?array
    {
        if (! $this->zhipuaiApiKey) {
            return null;
        }

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = $this->getMimeType($imagePath);
            $imageUrl = "data:{$mimeType};base64,{$imageData}";

            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->zhipuaiApiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://open.bigmodel.cn/api/paas/v4/chat/completions', [
                    'model' => 'glm-4v-flash',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $this->getPhotoOnlyPrompt()],
                                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';

            $json = $this->extractJson($text);

            return $json;

        } catch (Exception $e) {
            Log::error('ZhipuAI photo-only extraction failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Photo-only extraction using Anthropic API.
     */
    private function extractPhotoOnlyWithAnthropic(string $imagePath): ?array
    {
        if (! $this->anthropicApiKey) {
            return null;
        }

        try {
            $mimeType = $this->getMimeType($imagePath);
            $imageData = base64_encode(file_get_contents($imagePath));

            $response = Http::timeout(90)
                ->withHeaders([
                    'x-api-key' => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-3-5-sonnet-20241022'),
                    'max_tokens' => 2048,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image',
                                    'source' => [
                                        'type' => 'base64',
                                        'media_type' => $mimeType,
                                        'data' => $imageData,
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $this->getPhotoOnlyPrompt(),
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';

            $json = $this->extractJson($text);

            return $json;

        } catch (Exception $e) {
            Log::error('Anthropic photo-only extraction failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Universal extraction prompt for all APIs
     */
    private function getExtractionPrompt(?string $knownName = null): string
    {
        $currentDate = now()->format('Y-m-d');
        $currentYear = now()->year;
        $currentMonth = now()->format('F Y');

        $nameContext = $knownName
            ? "\n\n**CRITICAL - KNOWN NAME CONTEXT:**\nThe deceased person's name has been VERIFIED: \"{$knownName}\"\n\n**MANDATORY RULES:**\n1. RETURN THIS EXACT NAME in the \"full_name\" field - character-by-character identical\n2. DO NOT \"fix\" or \"correct\" this name - it already has proper Czech/Polish diacritics\n3. DO NOT change any characters: 'á' must stay 'á', 'ř' must stay 'ř', 'ł' must stay 'ł', etc.\n4. When this name appears in announcement_text, use this EXACT spelling (preserve diacritics)\n5. This name is authoritative - OCR in the image may have errors, THIS text is correct\n\nEXAMPLE: If known name is \"Dvořák\", return {\"full_name\": \"Dvořák\"} - NOT \"Dvorak\", \"Dvořâk\", or \"Dvořak\""
            : '';

        return "**DOCUMENT LANGUAGE: Czech or Polish**

**CRITICAL PRIORITY #1 - PORTRAIT PHOTO DETECTION:**

Death notices (parte) VERY COMMONLY include portrait photographs of the deceased person.

Portrait photo characteristics in Czech/Polish death notices:
• APPEARANCE: Formal portrait showing person's face/head
• STYLE: Usually black & white or sepia-toned
• FRAME: Often has thin black decorative border (1-3 pixels)
• POSITION: Typically located at:
  - Top-right corner (X: 65-95%, Y: 5-20%)
  - Top-center (X: 35-65%, Y: 5-20%)
  - Top-left corner (X: 5-30%, Y: 5-20%)
• SIZE: Usually 15-30% of page width, 15-35% of page height
• ASPECT RATIO: Portrait orientation (height ≥ width) or square
• QUALITY: May be grainy/low-resolution (still detect it!)

**YOUR TASK - DETECT WITH HIGH SENSITIVITY:**
1. Look VERY CAREFULLY for ANY portrait photograph
2. Even if photo quality is poor, bbox detection is uncertain, or image is small → STILL DETECT IT
3. Set has_photo=true if you see ANYTHING resembling a formal portrait photo
4. Provide best-estimate bbox even if edges are unclear
5. Common mistake: Missing photos because they blend with decorative borders → CHECK CAREFULLY

**EXAMPLES of valid photo regions:**
• Right corner: {\"x_percent\": 70.5, \"y_percent\": 7.6, \"width_percent\": 22.1, \"height_percent\": 19.8}
• Center top: {\"x_percent\": 40.0, \"y_percent\": 7.5, \"width_percent\": 17.5, \"height_percent\": 16.5}
• Left corner: {\"x_percent\": 10.0, \"y_percent\": 8.0, \"width_percent\": 20.0, \"height_percent\": 25.0}

**CRITICAL:** If uncertain, prefer FALSE POSITIVE (detect photo that might not exist) over FALSE NEGATIVE (miss existing photo).

**CRITICAL - DIACRITICS PRESERVATION:**
- ALWAYS preserve Czech diacritics: á č ď é ě í ň ó ř š ť ú ů ý ž (uppercase: Á Č Ď É Ě Í Ň Ó Ř Š Ť Ú Ů Ý Ž)
- ALWAYS preserve Polish characters: ą ć ę ł ń ó ś ź ż (uppercase: Ą Ć Ę Ł Ń Ó Ś Ź Ż)
- Do NOT replace diacritics with plain ASCII (WRONG: 'Novak' → CORRECT: 'Novák')
- Do NOT use combining diacritics or wrong Unicode (WRONG: 'Nová́k' → CORRECT: 'Novák')
- Common Czech names: Novák, Dvořák, Němec, Kučera, Hájek, Černý, Svoboda
- Common Polish names: Wałęsa, Kowalski, Wójcik, Szymański, Dąbrowski

Analyze this death notice (parte/obituary) image. Extract ALL available information in JSON format:

{
  \"full_name\": \"Full name of the deceased (first name + last name, e.g. 'Jan Novák')\",
  \"death_date\": \"Date of death in YYYY-MM-DD format (or null if not found)\",
  \"funeral_date\": \"Date of funeral ceremony in YYYY-MM-DD format (or null if not found)\",
  \"announcement_text\": \"Complete announcement text including funeral details (or null if not extractable)\",
  \"has_photo\": true,
  \"photo_description\": \"Brief description of portrait (e.g., 'elderly woman, formal attire')\",
  \"photo_bbox\": {
    \"x_percent\": 15.5,
    \"y_percent\": 10.0,
    \"width_percent\": 25.0,
    \"height_percent\": 35.0
  }
}{$nameContext}

CURRENT DATE CONTEXT: Today is {$currentDate} ({$currentMonth})
- Death notices are typically published within days/weeks of death
- Death dates should be recent (typically within last 1-30 days from today)
- If you see a year like '23', '24', '25', '26' it means 20{$currentYear}, NOT 1923/2023
- CRITICAL: Do NOT extract old years like 2005, 2010, 2015, etc. Recent deaths are from late 2025 or early {$currentYear}

EXTRACTION RULES:

0. CHARACTER ENCODING (ABSOLUTE PRIORITY):
   - Document is written in Czech or Polish language
   - PRESERVE all diacritics and special characters exactly as they appear
   - Fix OCR errors EXCEPT diacritics that look correct
   - If a name has diacritics, they are likely CORRECT (Czech/Polish names require them)
   - Example fixes: 'Novdk'→'Novák' (add missing 'á'), 'Nov6k'→'Novák' (fix OCR digit error)
   - Example DO NOT change: 'Dvořák' stays 'Dvořák' (already correct diacritics)
   - Czech diacritics: á č ď é ě í ň ó ř š ť ú ů ý ž
   - Polish characters: ą ć ę ł ń ó ś ź ż

1. FULL NAME:
   - Look after: 'paní', 'pan', 'panem' (Czech), 'Sp.', 'śp.', '§p.' (Polish)
   - Combine first + last name into single field
   - Fix OCR errors (e.g., 'Novdk' → 'Novák')
   - PRESERVE Czech/Polish diacritics: 'Novák' not 'Novak', 'Dvořák' not 'Dvorak'
   - Examples: 'Jan Novák', 'Marie Dvořáková', 'Józef Wójcik', 'Anna Wałęsa'

2. DEATH DATE (CRITICAL - DATE VALIDATION):
   - Keywords: 'zemřel/a', '†', 'zmarł/a', 'data śmierci', 'dne', 'dnia'
   - Formats: 'DD.MM.YYYY', 'DD.MM.YY', 'D. MONTH YYYY' (e.g., '31. prosince 2025')
   - Convert to YYYY-MM-DD
   - VALIDATE: Death date must be RECENT (December 2025 or January {$currentYear})
   - If OCR gives '24.12.05' it means '24.12.2025', NOT '24.12.2005'
   - If OCR gives '22.3.23' it means '22.3.2023' (likely OCR error, check context)
   - Common OCR errors: '2005'→'2025', '2015'→'2025', '23'→'2025'

3. FUNERAL DATE:
   - Keywords: 'pohřeb', 'rozloučení', 'pogrzeb', 'pożegnanie', 'obřad se koná'
   - Same formats as death_date
   - Convert to YYYY-MM-DD
   - Must be AFTER death_date (typically 3-14 days later)

4. ANNOUNCEMENT TEXT:
   - Extract complete announcement: opening → names → dates → funeral details → closing
   - INCLUDE: Full text, relationships, funeral time/location/venue, ending phrases
   - EXCLUDE: 
     * Biblical verses BEFORE announcement
     * Decorative borders
     * Funeral service contact information (phone numbers, addresses of funeral homes)
     * Business names/logos of funeral services
   - END the text at phrases like: 'Zarmoucená rodina', 'Smutná rodina', 'Manžel a děti', 'Pozůstalí'
   - Do NOT include contact details (phone, address, email) that appear AFTER family signature
   - Fix OCR errors: 'nds'→'nás', remove garbage ('R ża ©')
   - CRITICAL: Preserve ALL Czech and Polish diacritics in the entire text
   - PRESERVE CORRECT NAME SPELLING (use known name if provided above)
   - Return as continuous text with single spaces
   - ALWAYS ensure the announcement ends with a period (.) - if missing, add it

5. PHOTO DETECTION (EXECUTE PRIORITY #1 INSTRUCTIONS ABOVE):
   - Apply HIGH-SENSITIVITY detection from PRIORITY #1 section above
   - If portrait photo detected: Set \"has_photo\": true
   - Provide photo_description: Brief description (e.g., \"elderly man, grey hair, formal attire\")
   - Provide photo_bbox: Bounding box as PERCENTAGES (see examples in PRIORITY #1)
   - INCLUDE bbox even if edges are uncertain (best estimate is acceptable)
   - If absolutely NO photo visible after CAREFUL inspection: Set \"has_photo\": false, omit photo fields

BOUNDING BOX FORMAT (percentages of total image size):
- x_percent: Distance from LEFT edge (0-100%)
- y_percent: Distance from TOP edge (0-100%)
- width_percent: Photo width as % of total image width (typically 20-30%)
- height_percent: Photo height as % of total image height (typically 25-40%)

Example: Photo at top-left corner, 200px×250px in 800px×1000px image:
{
  \"x_percent\": 5.0,
  \"y_percent\": 8.0,
  \"width_percent\": 25.0,
  \"height_percent\": 25.0
}

Languages: Czech or Polish
Return ONLY valid JSON, nothing else.";
    }

    /**
     * Clean and validate extraction result
     */
    private function cleanExtractionResult(array $json, ?string $knownName = null): array
    {
        $result = [
            'full_name' => $json['full_name'] ?? null,
            'death_date' => $json['death_date'] ?? null,
            'funeral_date' => $json['funeral_date'] ?? null,
            'announcement_text' => null,
        ];

        // CRITICAL: Validate known name wasn't changed by AI
        if ($knownName && isset($result['full_name'])) {
            if ($result['full_name'] !== $knownName) {
                Log::warning('AI attempted to change known name - reverting to original', [
                    'known_name' => $knownName,
                    'ai_returned' => $result['full_name'],
                    'difference' => $this->compareStrings($knownName, $result['full_name']),
                ]);
                // Force correct name with proper diacritics
                $result['full_name'] = $knownName;
            }
        }

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
        // Add photo detection fields
        $result['has_photo'] = isset($json['has_photo']) ? (bool) $json['has_photo'] : false;
        $result['photo_description'] = $json['photo_description'] ?? null;
        $result['photo_bbox'] = $json['photo_bbox'] ?? null;

        // Validate photo_bbox if present
        if ($result['photo_bbox']) {
            $bbox = $result['photo_bbox'];
            if (! isset($bbox['x_percent'], $bbox['y_percent'], $bbox['width_percent'], $bbox['height_percent'])) {
                Log::warning('Photo bbox missing required fields', ['bbox' => $bbox]);
                $result['photo_bbox'] = null;
            } elseif ($bbox['x_percent'] < 0 || $bbox['x_percent'] > 100 ||
                      $bbox['y_percent'] < 0 || $bbox['y_percent'] > 100 ||
                      $bbox['width_percent'] < 5 || $bbox['width_percent'] > 100 ||
                      $bbox['height_percent'] < 5 || $bbox['height_percent'] > 100) {
                Log::warning('Photo bbox has invalid values', ['bbox' => $bbox]);
                $result['photo_bbox'] = null;
            }
        }

        return $result;
    }

    /**
     * Compare two strings and return difference summary
     */
    private function compareStrings(string $expected, string $received): string
    {
        if ($expected === $received) {
            return 'identical';
        }

        $diffs = [];

        // Check for removed diacritics
        $expectedNormalized = $this->removeDiacritics($expected);
        $receivedNormalized = $this->removeDiacritics($received);

        if ($expectedNormalized === $receivedNormalized) {
            $diffs[] = 'diacritics removed or changed';
        }

        // Check case changes
        if (mb_strtolower($expected) === mb_strtolower($received)) {
            $diffs[] = 'case changed';
        }

        // Check length difference
        $lenDiff = mb_strlen($received) - mb_strlen($expected);
        if ($lenDiff !== 0) {
            $diffs[] = sprintf('%d characters %s', abs($lenDiff), $lenDiff > 0 ? 'added' : 'removed');
        }

        return empty($diffs) ? 'completely different' : implode(', ', $diffs);
    }

    /**
     * Remove Czech and Polish diacritics for comparison
     */
    private function removeDiacritics(string $text): string
    {
        $diacritics = [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
            'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
            'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
            'Á' => 'A', 'Č' => 'C', 'Ď' => 'D', 'É' => 'E', 'Ě' => 'E',
            'Í' => 'I', 'Ň' => 'N', 'Ó' => 'O', 'Ř' => 'R', 'Š' => 'S',
            'Ť' => 'T', 'Ú' => 'U', 'Ů' => 'U', 'Ý' => 'Y', 'Ž' => 'Z',
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
            'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
        ];

        return strtr($text, $diacritics);
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
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
    }
}
