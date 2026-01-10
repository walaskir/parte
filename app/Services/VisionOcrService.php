<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisionOcrService
{
    private string $textProvider;

    private ?string $textProviderModel;

    private ?string $textFallbackProvider;

    private ?string $textFallbackModel;

    private string $photoProvider;

    private ?string $photoProviderModel;

    private ?string $photoFallbackProvider;

    private ?string $photoFallbackModel;

    private ?string $geminiApiKey;

    private ?string $zhipuaiApiKey;

    private ?string $anthropicApiKey;

    private ?string $abacusAiApiKey;

    public function __construct()
    {
        // BREAKING CHANGE: Validate configuration
        $this->validateConfiguration();

        // Parse new provider/model syntax
        [$this->textProvider, $this->textProviderModel] = $this->parseProvider(
            config('services.vision.text_provider')
        );
        [$this->textFallbackProvider, $this->textFallbackModel] = $this->parseProvider(
            config('services.vision.text_fallback') ?? ''
        );
        [$this->photoProvider, $this->photoProviderModel] = $this->parseProvider(
            config('services.vision.photo_provider')
        );
        [$this->photoFallbackProvider, $this->photoFallbackModel] = $this->parseProvider(
            config('services.vision.photo_fallback') ?? ''
        );

        // Load API keys
        $this->geminiApiKey = config('services.gemini.api_key');
        $this->zhipuaiApiKey = config('services.zhipuai.api_key');
        $this->anthropicApiKey = config('services.anthropic.api_key');
        $this->abacusAiApiKey = config('services.abacusai.api_key');

        // Validate at least one provider configured
        $configuredProviders = $this->getAllConfiguredProviders();

        if (empty($configuredProviders)) {
            throw new Exception('No vision providers configured. Please set at least one provider API key (GEMINI_API_KEY, ZHIPUAI_API_KEY, ANTHROPIC_API_KEY, or ABACUSAI_API_KEY) in .env file.');
        }

        // Validate text provider is configured
        if (! $this->isProviderConfigured($this->textProvider)) {
            throw new Exception("Text provider '{$this->textProvider}' is not configured. Please set the API key in .env file.");
        }

        // Validate photo provider is configured
        if (! $this->isProviderConfigured($this->photoProvider)) {
            throw new Exception("Photo provider '{$this->photoProvider}' is not configured. Please set the API key in .env file.");
        }
    }

    /**
     * Validate configuration and handle deprecated syntax
     */
    private function validateConfiguration(): void
    {
        $oldProvider = config('services.vision.provider');
        $oldFallback = config('services.vision.fallback_provider');

        // Production: Throw exception if old syntax detected
        if (app()->environment('production')) {
            if ($oldProvider || $oldFallback) {
                throw new Exception(
                    'BREAKING CHANGE: VISION_PROVIDER syntax deprecated. '.
                    'Update .env to use new format: '.
                    'VISION_TEXT_PROVIDER=abacusai/gemini-3-flash '.
                    'VISION_PHOTO_PROVIDER=abacusai/gemini-3-flash. '.
                    'See .env.example for details. (Version 2.0)'
                );
            }
        }

        // Local/testing: Auto-convert with warning
        if (app()->environment(['local', 'testing'])) {
            if ($oldProvider && ! config('services.vision.text_provider')) {
                Log::warning('AUTO-CONVERTING deprecated VISION_PROVIDER to new format', [
                    'old' => $oldProvider,
                    'new' => $oldProvider,
                ]);

                config(['services.vision.text_provider' => $oldProvider]);
                config(['services.vision.photo_provider' => $oldProvider]);
            }
        }

        // Validate new syntax exists
        if (! config('services.vision.text_provider') || ! config('services.vision.photo_provider')) {
            throw new Exception(
                'VISION_TEXT_PROVIDER and VISION_PHOTO_PROVIDER required. '.
                'See .env.example for new configuration syntax.'
            );
        }
    }

    /**
     * Parse provider string: "abacusai/gemini-3-flash" or "anthropic"
     *
     * @return array{string, string|null} [provider, model]
     */
    private function parseProvider(string $providerString): array
    {
        if (empty($providerString)) {
            return ['', null];
        }

        if (str_contains($providerString, '/')) {
            [$provider, $model] = explode('/', $providerString, 2);

            return [trim($provider), trim($model)];
        }

        return [trim($providerString), null];
    }

    /**
     * Extract text data (name, dates, announcement, opening_quote) from image
     *
     * @deprecated Use extractTextFromImage() or extractPhotoFromImage() instead
     */
    public function extractFromImage(string $imagePath, bool $extractDeathDate = false, ?string $knownName = null): ?array
    {
        // Backward compatibility wrapper - delegates to new method
        return $this->extractTextFromImage($imagePath, $knownName);
    }

    /**
     * Extract text data (name, dates, announcement, opening_quote) from image
     */
    public function extractTextFromImage(string $imagePath, ?string $knownName = null): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                Log::error('Image file not found for extraction', ['path' => $imagePath]);

                return null;
            }

            Log::info('VisionOcrService: Text extraction starting', [
                'text_provider' => $this->textProvider,
                'text_model' => $this->textProviderModel,
            ]);

            // Try primary text provider
            $result = $this->extractTextWithProvider(
                $this->textProvider,
                $this->textProviderModel,
                $imagePath,
                $knownName
            );

            if ($result && $this->validateTextExtraction($result)) {
                Log::info('VisionOcrService: Text extraction successful', [
                    'provider' => $this->textProvider,
                ]);

                return $result;
            }

            // Try fallback
            if ($this->textFallbackProvider && $this->isProviderConfigured($this->textFallbackProvider)) {
                Log::warning('VisionOcrService: Text extraction fallback', [
                    'fallback_provider' => $this->textFallbackProvider,
                ]);

                $result = $this->extractTextWithProvider(
                    $this->textFallbackProvider,
                    $this->textFallbackModel,
                    $imagePath,
                    $knownName
                );

                if ($result && $this->validateTextExtraction($result)) {
                    Log::info('VisionOcrService: Fallback text extraction successful', [
                        'provider' => $this->textFallbackProvider,
                    ]);

                    return $result;
                }
            }

            Log::error('VisionOcrService: All text extraction methods failed', ['image_path' => $imagePath]);

            return null;

        } catch (Exception $e) {
            Log::error('VisionOcrService text extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Extract photo bounding box from image
     */
    public function extractPhotoFromImage(string $imagePath): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                return ['has_photo' => false];
            }

            Log::info('VisionOcrService: Photo extraction starting', [
                'photo_provider' => $this->photoProvider,
                'photo_model' => $this->photoProviderModel,
            ]);

            // Try primary photo provider
            $result = $this->extractPhotoWithProvider(
                $this->photoProvider,
                $this->photoProviderModel,
                $imagePath
            );

            if ($result && ($result['has_photo'] ?? false)) {
                Log::info('VisionOcrService: Photo extraction successful', [
                    'provider' => $this->photoProvider,
                ]);

                return $result;
            }

            // Try fallback
            if ($this->photoFallbackProvider && $this->isProviderConfigured($this->photoFallbackProvider)) {
                Log::warning('VisionOcrService: Photo extraction fallback', [
                    'fallback_provider' => $this->photoFallbackProvider,
                ]);

                $result = $this->extractPhotoWithProvider(
                    $this->photoFallbackProvider,
                    $this->photoFallbackModel,
                    $imagePath
                );

                if ($result && ($result['has_photo'] ?? false)) {
                    Log::info('VisionOcrService: Fallback photo extraction successful', [
                        'provider' => $this->photoFallbackProvider,
                    ]);

                    return $result;
                }
            }

            return ['has_photo' => false];

        } catch (Exception $e) {
            Log::error('VisionOcrService photo extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return ['has_photo' => false];
        }
    }

    /**
     * Extract text with specific provider
     */
    private function extractTextWithProvider(
        string $provider,
        ?string $model,
        string $imagePath,
        ?string $knownName
    ): ?array {
        return match ($provider) {
            'abacusai' => $this->extractTextWithAbacusAI($imagePath, $model, $knownName),
            'gemini' => $this->extractWithGemini($imagePath, $knownName),
            'zhipuai' => $this->extractWithZhipuAI($imagePath, $knownName),
            'anthropic' => $this->extractWithAnthropic($imagePath, $knownName),
            default => throw new Exception("Unknown provider: {$provider}")
        };
    }

    /**
     * Extract photo with specific provider
     */
    private function extractPhotoWithProvider(
        string $provider,
        ?string $model,
        string $imagePath
    ): ?array {
        return match ($provider) {
            'abacusai' => $this->extractPhotoWithAbacusAI($imagePath, $model),
            'gemini' => $this->extractPhotoOnlyWithGemini($imagePath),
            'zhipuai' => $this->extractPhotoOnlyWithZhipuAI($imagePath),
            'anthropic' => $this->extractPhotoOnlyWithAnthropic($imagePath),
            default => throw new Exception("Unknown provider: {$provider}")
        };
    }

    /**
     * Extract text using Abacus.AI API
     */
    private function extractTextWithAbacusAI(
        string $imagePath,
        ?string $modelKey,
        ?string $knownName
    ): ?array {
        if (! $this->abacusAiApiKey) {
            return null;
        }

        try {
            $service = new AbacusAiVisionService($this->abacusAiApiKey);

            // Convert model key to Abacus constant
            $model = $this->getAbacusAiModel($modelKey);

            $result = $service->extractDeathNotice($imagePath, $model);

            // Add required fields for compatibility
            $result['has_photo'] = false; // Handled separately

            // Apply post-processing and cleaning
            return $this->cleanExtractionResult($result, $knownName);
        } catch (Exception $e) {
            Log::error('Abacus.AI text extraction failed', [
                'error' => $e->getMessage(),
                'model' => $modelKey,
            ]);

            return null;
        }
    }

    /**
     * Extract photo using Abacus.AI API
     */
    private function extractPhotoWithAbacusAI(string $imagePath, ?string $modelKey): ?array
    {
        if (! $this->abacusAiApiKey) {
            return null;
        }

        try {
            $service = new AbacusAiVisionService($this->abacusAiApiKey);
            $model = $this->getAbacusAiModel($modelKey);

            $result = $service->detectPortrait($imagePath, $model);

            // Convert photo_bounds to photo_bbox format for compatibility
            if ($result['has_photo'] && isset($result['photo_bounds'])) {
                $result['photo_bbox'] = [
                    'x_percent' => $result['photo_bounds']['x'],
                    'y_percent' => $result['photo_bounds']['y'],
                    'width_percent' => $result['photo_bounds']['width'],
                    'height_percent' => $result['photo_bounds']['height'],
                ];
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Abacus.AI photo extraction failed', [
                'error' => $e->getMessage(),
                'model' => $modelKey,
            ]);

            return null;
        }
    }

    /**
     * Map model key to Abacus.AI constant
     */
    private function getAbacusAiModel(?string $modelKey): string
    {
        if (! $modelKey) {
            return AbacusAiVisionService::MODEL_GEMINI_3_FLASH;
        }

        $models = config('services.abacusai.models', []);

        return $models[$modelKey] ?? AbacusAiVisionService::MODEL_GEMINI_3_FLASH;
    }

    /**
     * Validate text extraction result
     */
    private function validateTextExtraction(array $result): bool
    {
        return isset($result['full_name']) && $result['full_name'];
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
            'abacusai' => ! empty($this->abacusAiApiKey),
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

        if ($this->isProviderConfigured('abacusai')) {
            $providers[] = 'abacusai';
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
  \"opening_quote\": \"Poetic/memorial quote at document start (or null if none)\",
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

1.5. OPENING QUOTE (SEPARATE JSON FIELD - CRITICAL):
   ⚠️ THIS MUST BE EXTRACTED TO A SEPARATE FIELD, NOT INCLUDED IN ANNOUNCEMENT_TEXT ⚠️
   
   WHAT TO EXTRACT:
   - Poetic, memorial, or biblical quotes at the VERY START of document
   - Appears BEFORE main announcement phrases: \"Z głębokim smutkiem...\", \"S hlubokým smutkem...\"
   
   COMMON EXAMPLES:
   Polish quotes:
   * \"Będę żyć dalej w sercach tych, którzy mnie kochali\"
   * \"Kto Cię znał, ten Cię ceni i pamięta\"
   * \"Odszedłeś, ale pozostajesz w naszych sercach\"
   
   Czech quotes:
   * \"Kdo Tě znal, ten Tě ctít musí\"
   * \"Zůstaneš navždy v našich srdcích\"
   * \"Čas plyne, ale vzpomínky zůstávají\"
   
   ⚠️ CRITICAL EXTRACTION RULES:
   - ✅ EXTRACT to \"opening_quote\" JSON field (SEPARATE from announcement_text)
   - ✅ announcement_text must START from \"Z głębokim smutkiem...\" (AFTER opening quote)
   - ❌ DO NOT include opening quote in \"announcement_text\" field
   - ❌ DO NOT return null if opening quote clearly exists
   
   VALIDATION:
   - Maximum reasonable length: ~500 characters
   - If \"quote\" is longer than 500 chars: it's likely the full announcement (return null)
   - If no opening quote exists (announcement starts directly): return null
   
   EXAMPLE OF CORRECT vs WRONG EXTRACTION:
   
   Document text: \"Będę żyć dalej w sercach tych, którzy mnie kochali. Z głębokim smutkiem...\"
   
   ✅ CORRECT:
   {
     \"opening_quote\": \"Będę żyć dalej w sercach tych, którzy mnie kochali\",
     \"announcement_text\": \"Z głębokim smutkiem i żalem zawiadamiamy...\"
   }
   
   ❌ WRONG (opening_quote is null, included in announcement instead):
   {
     \"opening_quote\": null,
     \"announcement_text\": \"Będę żyć dalej w sercach... Z głębokim smutkiem...\"
   }

⚠️ SPECIAL CASES FOR OPENING QUOTES (CRITICAL):

1. QUOTES WITH AUTHOR ATTRIBUTION:
   If the opening quote includes an author name AFTER the quote text, 
   the author name is PART OF the opening_quote field:
   
   ✅ CORRECT (author included in opening_quote):
   {
     \"opening_quote\": \"Zvedám jí ruku. Je v ní chlad, prsty jsou přitisklé k dlani. Kytičku chci jí do ní dát, už naposledy tentokrát. J. Seifert\",
     \"announcement_text\": \"V tichém zármutku oznamujeme...\"
   }
   
   ❌ WRONG (author name left in announcement):
   {
     \"opening_quote\": \"Zvedám jí ruku. Je v ní chlad...\",
     \"announcement_text\": \"J. Seifert V tichém zármutku...\"
   }
   
   Common author formats:
   - Full name: \"Jiří Wolker.\", \"Jan Neruda.\", \"Jaroslav Seifert.\"
   - Initial + name: \"J. Seifert\", \"J. Wolker\"

2. BIBLE VERSES OR PSALM QUOTES:
   Biblical quotes with verse references (Žalm/Psalm X, Y-Z) are opening quotes:
   
   ✅ CORRECT (verse reference included):
   {
     \"opening_quote\": \"Kdo v úkrytu Nejvyššího bydlí, přečká noc ve stínu Všemohoucího. Říkám o Hospodinu: „Mé útočiště, má pevná tvrz je můj Bůh, v nějž doufám.\" Žalm 91, 1-2\",
     \"announcement_text\": \"V hlubokém zármutku oznamujeme...\"
   }

3. QUOTES ENDING WITH ELLIPSIS:
   Opening quotes can end with \"...\" (ellipsis) instead of period:
   
   ✅ CORRECT:
   {
     \"opening_quote\": \"Kto w sercach żyje tych, których opuścił, ten nie odszedł ...\",
     \"announcement_text\": \"W głębokim smutku pogrążeni...\"
   }

4. ANNOUNCEMENT STARTERS - EXPANDED LIST:
   Opening quote ends BEFORE these phrases (announcement starts here):
   
   Polish starters:
   - \"Z głębokim smutkiem...\" ← MAIN PATTERN
   - \"W głębokim żalu...\" ← MAIN PATTERN
   - \"W głębokim smutku pogrążeni...\" ← VARIANT
   - \"Zmarł dnia...\" ← Death date announcement (no grief phrase)
   - \"Zmarła dnia...\" ← Death date announcement (feminine)
   
   Czech starters:
   - \"S hlubokým smutkem...\" ← MAIN PATTERN
   - \"S bolestí v srdci...\" ← MAIN PATTERN
   - \"V hlubokém zármutku...\" ← VERY COMMON
   - \"V tichém zármutku...\" ← VERY COMMON

5. \"NEPLAČTE/NELKEJTE\" MOURNING QUOTES:
   Mourning quotes starting with \"Neplačte\" (Czech: don't cry) are opening quotes:
   
   ✅ CORRECT:
   {
     \"opening_quote\": \"Neplačte, že jsem odešla, ten klid a mír mi přejte a vzpomínku mi v srdci svém jen věrně zachovejte.\",
     \"announcement_text\": \"V hlubokém zármutku oznamujeme...\"
   }
   
   Also applies to: \"Nelkejte, že jsem odešel...\" (variant spelling)

WHY THESE SPECIAL CASES MATTER:
- Opening quotes are poetic/memorial tributes (often from famous Czech/Polish poets or Bible)
- They may include author attribution, verse references, or end with ellipsis
- Announcement text is factual death/funeral information
- These MUST be separated into different JSON fields for proper data structure

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

4. ANNOUNCEMENT TEXT (FAMILY ANNOUNCEMENT ONLY - NO OPENING QUOTE, NO BUSINESS INFO):
   ⚠️ CRITICAL: DO NOT INCLUDE OPENING QUOTE HERE - it goes to opening_quote field ⚠️
   
   WHAT TO EXTRACT:
   - Complete family announcement: names → dates → funeral details → family closing
   - INCLUDE: Full text, relationships, funeral time/location/venue, family signature
   
   ❌ WHAT TO EXCLUDE (DO NOT INCLUDE):
     * Opening quote (already extracted to opening_quote field - DO NOT REPEAT HERE)
     * Biblical verses BEFORE main announcement
     * Decorative borders, page numbers, design elements
     * Funeral service business names (e.g., \"Jan Sadový Pohřební služba\", \"PSHAJDUKOVÁ, s.r.o.\")
     * Contact information: phone (tel., mobil), addresses, emails, websites
     * Business information that appears AFTER family signature
   
   📍 WHERE TO START (CRITICAL):
   - ✅ START from: \"Z głębokim smutkiem...\" OR \"S hlubokým smutkem...\"
   - ✅ This is AFTER the opening quote (if opening quote exists)
   - ❌ DO NOT start with poetic quotes like \"Będę żyć dalej w sercach...\"
   - ❌ DO NOT start with biblical verses
   
   EXAMPLE OF CORRECT START:
   ✅ \"Z głębokim smutkiem i żalem zawiadamiamy rodzinę...\"
   ✅ \"S hlubokým smutkem oznamujeme...\"
   
   EXAMPLE OF WRONG START (includes opening quote):
   ❌ \"Będę żyć dalej w sercach tych, którzy mnie kochali. Z głębokim smutkiem...\"
   
   📍 WHERE TO END (CRITICAL):
   - ✅ END at family signature phrases:
     * Czech: 'Zarmoucená rodina', 'Smutná rodina', 'Manžel a děti', 'Pozůstalí', 'Rodina'
     * Polish: 'Zasmucona rodzina', 'Smutna rodzina', 'Żona i dzieci', 'Rodzina'
   - ✅ INCLUDE the family signature phrase in your extraction
   - ✅ STOP EXTRACTION IMMEDIATELY after family signature
   - ❌ DO NOT continue past family signature
   - ❌ DO NOT include anything that appears AFTER family signature
   
   🛑 CRITICAL WARNING - COMMON EXTRACTION ERRORS:
   If you see these patterns AFTER family signature, they are funeral service footers - EXCLUDE THEM:
   * Business names: \"Jan Sadový\", \"PsHAJDUKOVÁ\", \"Hajduková\" (funeral service owners)
   * Company designations: \"s.r.o.\", \"sp. z o.o.\", \"Pohřební služba\"
   * Contact info: \"tel.\", \"mobil\", phone numbers, addresses (\"ul.\", \"č.p.\")
   * If you see capitalized names AFTER family members list → it's likely a business name → STOP BEFORE IT
   
   ⚠️ CONCRETE EXAMPLES OF CORRECT vs WRONG ENDINGS:
   
   EXAMPLE 1 - Polish announcement:
   Full text: \"...pogrzeb odbędzie się w czwartek 9 stycznia 2026 roku o godzinie 14.00 z kościoła. Zasmucona rodzina Jan Sadový Pohřební služba Bystřice tel. 558352208 mobil: 602539388\"
   
   ✅ CORRECT EXTRACTION (stops at family signature):
   \"...pogrzeb odbędzie się w czwartek 9 stycznia 2026 roku o godzinie 14.00 z kościoła. Zasmucona rodzina\"
   
   ❌ WRONG EXTRACTION (includes funeral business):
   \"...Zasmucona rodzina Jan Sadový Pohřební služba Bystřice tel. 558352208 mobil: 602539388\"
   
   EXAMPLE 2 - Czech announcement:
   Full text: \"...rozloučení proběhne v pátek v obřadní síni. Zarmoucená rodina. PSHAJDUKOVÁ, s.r.o., ul. 1.máje 172, Třinec, tel.: 558 339 296\"
   
   ✅ CORRECT EXTRACTION (stops at family signature):
   \"...rozloučení proběhne v pátek v obřadní síni. Zarmoucená rodina\"
   
   ❌ WRONG EXTRACTION (includes funeral company):
   \"...Zarmoucená rodina. PSHAJDUKOVÁ, s.r.o., ul. 1.máje 172, Třinec, tel.: 558 339 296\"
   
   WHY THIS MATTERS:
   The funeral service footer is business information added by the funeral company for contact purposes.
   Your job is to extract ONLY what the FAMILY wrote in their announcement, NOT the business contact info.
   Think of it like a letter: you extract the letter content, not the letterhead/footer of the printing company.
   
   OTHER RULES:
   - Fix OCR errors: 'nds'→'nás', 'zm arl'→'zmarł', remove garbage characters ('R ża ©')
   - CRITICAL: Preserve ALL Czech and Polish diacritics in the entire text
   - PRESERVE CORRECT NAME SPELLING (use known name if provided above)
   - Return as continuous text with single spaces (collapse multiple spaces)
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

⚠️ FINAL VALIDATION CHECKLIST - VERIFY BEFORE RETURNING JSON:

✓ opening_quote field: Contains ONLY the opening quote text (or null if no quote exists)
✓ opening_quote field: Is NOT duplicated in announcement_text
✓ announcement_text field: Does NOT start with opening quote (starts with \"Z głębokim smutkiem...\" or similar)
✓ announcement_text field: Does NOT end with funeral business names (\"Jan Sadový...\", \"PSHAJDUKOVÁ...\")
✓ announcement_text field: Does NOT end with phone numbers (tel., mobil)
✓ announcement_text field: Ends at family signature phrase (\"Zasmucona rodzina\", \"Zarmoucená rodina\", etc.)
✓ full_name field: Does NOT include \"śp.\", \"Sp.\", or \"§p.\" prefix

Return ONLY valid JSON, nothing else.";
    }

    /**
     * Clean and validate extraction result
     */
    private function cleanExtractionResult(array $json, ?string $knownName = null): array
    {
        $result = [
            'full_name' => $this->cleanFullName($json['full_name'] ?? null),
            'opening_quote' => $json['opening_quote'] ?? null,
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

        // Validate opening_quote
        if (isset($result['opening_quote']) && $result['opening_quote']) {
            if (strlen($result['opening_quote']) > 500) {
                Log::warning('Opening quote suspiciously long', [
                    'length' => strlen($result['opening_quote']),
                    'preview' => substr($result['opening_quote'], 0, 100),
                ]);
            }
        }

        if (isset($json['announcement_text']) && $json['announcement_text']) {
            $cleaned = preg_replace('/\s+/', ' ', trim($json['announcement_text']));

            // POST-PROCESSING STEP 1: Remove funeral service footer FIRST (before splitting text)
            $cleaned = $this->removeFuneralServiceSignature($cleaned);

            // POST-PROCESSING STEP 2: Extract opening quote if AI missed it (from cleaned text)
            if (empty($result['opening_quote']) && strlen($cleaned) > 100) {
                $extracted = $this->extractOpeningQuoteFromAnnouncement($cleaned);
                if ($extracted['opening_quote']) {
                    $result['opening_quote'] = $extracted['opening_quote'];
                    $cleaned = $extracted['announcement_text'];
                }
            }

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
     * Clean full_name by removing Polish deceased prefix
     */
    private function cleanFullName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        // Remove Polish "śp." prefix (deceased marker)
        // Also Czech/Slovak variants
        $prefixes = ['śp. ', 'sp. ', 'ś.p. ', 'Śp. ', 'Sp. ', 'Ś.p. '];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break; // Only remove one prefix
            }
        }

        return trim($name);
    }

    /**
     * Extract opening quote from announcement_text if AI missed it
     * Post-processing fallback method
     */
    private function extractOpeningQuoteFromAnnouncement(string $announcementText): array
    {
        // Common patterns for opening quotes followed by main announcement
        $patterns = [
            // === EXISTING PATTERNS (keep unchanged) ===
            // Polish: Quote ending with period/comma + "Z głębokim smutkiem"
            '/^(.{20,500}?[.!])\s+(Z głębokim smutkiem|W głębokim żalu)/u',
            // Czech: Quote ending with period/comma + "S hlubokým smutkem"
            '/^(.{20,500}?[.!])\s+(S hlubokým smutkem|S bolestí v srdci)/u',
            // Quote with multiple sentences ending before announcement
            '/^((?:[^.!]+[.!]\s*){1,3})\s+(Z głębokim|S hlubokým|W głębokim)/u',

            // === NEW PATTERNS FOR EDGE CASES ===
            // Pattern 4: Czech quotes with author attribution (full name)
            '/^(.{20,600}?[.!]\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\wáčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\wáčďéěíňóřšťúůýž]+\.)\s+(V\s+(?:tichém|hlubokém)\s+zármutku)/u',
            // Pattern 5: Czech quotes with author attribution (initial + name)
            '/^(.{20,600}?[.!]\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]\.\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\wáčďéěíňóřšťúůýž]+)\s+(V\s+(?:tichém|hlubokém)\s+zármutku)/u',
            // Pattern 6: Bible verses with Žalm/Psalm reference
            '/^(.{20,600}?(?:Žalm|Psalm)\s+\d+[,\s\d\-]+)\s+(V\s+hlubokém\s+zármutku|S\s+bolestí)/u',
            // Pattern 7: "Neplačte/Nelkejte" mourning pattern
            '/^(Ne[lp][la]čte[^.]+\.)\s+(V\s+hlubokém\s+zármutku|S\s+bolestí)/u',
            // Pattern 8: Polish quotes ending with ellipsis
            '/^(.{20,600}?\s+\.{3})\s+(W\s+głębokim\s+(?:smutku|żalu)|Z\s+głębokim\s+smutkiem)/u',
            // Pattern 9: Polish philosophical quotes with "Zmarł dnia" starter
            '/^(.{20,600}?\.)\s+(Zmarł\s+dnia|Zmarła\s+dnia)/u',
            // Pattern 10: Broader Czech "V hlubokém/tichém zármutku" catch-all
            '/^(.{20,600}?\.)\s+(V\s+(?:hlubokém|tichém)\s+zármutku)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $announcementText, $matches)) {
                $quote = trim($matches[1]);
                $remainingText = trim(preg_replace('/^'.preg_quote($quote, '/').'\s*/u', '', $announcementText));

                // Validate quote length (reasonable for opening quote, increased to 600 for Bible verses)
                if (strlen($quote) >= 20 && strlen($quote) <= 600) {
                    Log::info('Post-processing: Extracted opening quote from announcement_text', [
                        'quote_length' => strlen($quote),
                        'quote_preview' => substr($quote, 0, 50),
                    ]);

                    return [
                        'opening_quote' => $quote,
                        'announcement_text' => $remainingText,
                    ];
                }
            }
        }

        return [
            'opening_quote' => null,
            'announcement_text' => $announcementText,
        ];
    }

    /**
     * Remove funeral service business footer from announcement text
     * Enhanced to handle more patterns and log removals
     */
    private function removeFuneralServiceSignature(string $text): string
    {
        $original = $text;

        // Pattern 1: Family signature + Business name + contact info
        // Example: "Zasmucona rodzina Jan Sadový Pohřební služba Bystřice tel. 558352208 mobil: 602539388"
        $patterns = [
            // Polish: Zasmucona rodzina + business + all phone numbers (keep family signature WITH period)
            '/((?:Zasmucona|Smutna)\s+rodzina|Żona\s+i\s+dzieci|Rodzina)\.?\s+[A-ZĄĆĘŁŃÓŚŹŻ][\wąćęłńóśźż\s]+(?:Pohřební|Pohrební|służba).*?(?:tel\.?|mobil).*$/ui',

            // Czech: Zarmoucená rodina + business + all phone numbers (keep family signature WITH period)
            '/((?:Zarmoucená|Smutná)\s+rodina|Manžel\s+a\s+děti|Pozůstalí|Rodina)\.?\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\wáčďéěíňóřšťúůýž\s]+(?:Pohřební|služba|s\.r\.o\.).*?(?:tel\.?|mobil).*$/ui',

            // Business with s.r.o. + any contact info to end of text
            '/((?:Zarmoucená|Zasmucona)\s+(?:rodina|rodzina)|Rodzina)\.?\s+[A-ZĄĆĘŁŃÓŚŹŻ][\wąćęłńóśźż\s,]*(?:s\.r\.o\.|sp\. z o\.o\.).*$/ui',

            // Simple: Family signature + anything with "tel." or "mobil" to end
            '/((?:Zarmoucená|Zasmucona|Smutná)\s+(?:rodina|rodzina)|Rodzina)\.?\s+(?:[A-Z][\wąćęłńóśźżáčďéěíňóřšťúůýž\s]+)?(?:tel\.|mobil|telefon).*$/ui',

            // Czech: "Pohřební služba..." + everything to end
            '/pohřební služba.*$/iu',

            // Polish: "Zakład pogrzebowy..." + everything to end
            '/zakład pogrzebowy.*$/iu',

            // Generic: Business with address + phone to end
            '/s\.r\.o\.,?\s+ul\..*$/iu',

            // Standalone: "PsHAJDUKOVÁ, s.r.o." or similar company names
            '/Ps[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]+[áčďéěíňóřšťúůýž]*,\s*s\.r\.o\..*$/iu',

            // Phone numbers after family signature (standalone) - greedy to end
            '/\b(?:tel|mobil|telefon)\.?\s*:?\s*[\d\s\-:]+$/iu',

            // === NEW PATTERNS FOR STANDALONE FUNERAL SERVICE NAMES ===

            // SPECIFIC: Known funeral service owner names (Czech/Polish) - capture everything BEFORE them
            '/(.*?)\s+(?:Jan\s+Sadový|Jan\s+Sadovy|Sadový|Sadovy|PSHAJDUKOVÁ|PsHAJDUKOVÁ|Hajduková)[,\.\s]*$/ui',

            // GENERIC: Incomplete company name with just comma at end (7+ chars) - capture everything BEFORE it
            '/(.*?)\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]{7,}[a-záčďéěíňóřšťúůýž]*,\s*$/u',

            // GENERIC: Family member listing ending with capitalized business name - keep family members only
            '/((?:manželka|žena|dcera|syn|synové|dzieci|córka|brat|sestra)\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽĄĆĘŁŃÓŚŹŻa-záčďéěíňóřšťúůýžąćęłńóśźż\s,]+?)\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽĄĆĘŁŃÓŚŹŻ]{3,}[a-záčďéěíňóřšťúůýžąćęłńóśźż]*,?\s*$/ui',

            // Copyright/logos
            '/©\s*MCST/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '$1', $text);
        }

        // If text was modified, log it
        if ($text !== $original) {
            $removed = trim(substr($original, strlen($text)));
            Log::info('Post-processing: Removed funeral service footer', [
                'removed_text' => substr($removed, 0, 100),
                'original_length' => strlen($original),
                'cleaned_length' => strlen($text),
            ]);
        }

        $text = trim($text);

        // Safety net: Add period if missing after footer removal
        if (! empty($text) && ! preg_match('/[.!?]$/u', $text)) {
            $text .= '.';
            Log::info('Post-processing: Added missing period to announcement text');
        }

        return $text;
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
