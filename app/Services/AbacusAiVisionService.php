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
     * Detect portrait photo and get bounding box
     *
     * @param  string  $imagePath  Path to image file
     * @param  string  $model  Model to use (default: CLAUDE-SONNET-4-5-20250929)
     * @return array{has_photo: bool, photo_bounds: ?array{x: float, y: float, width: float, height: float}}
     *
     * @throws \Exception
     */
    public function detectPortrait(string $imagePath, string $model = self::MODEL_CLAUDE_SONNET_45): array
    {
        $imageBase64 = base64_encode(file_get_contents($imagePath));

        $prompt = 'Analyze this death notice image. Does it contain a portrait photo of the deceased person? '.
            'If YES, provide bounding box coordinates as percentages (0-100) of image dimensions. '.
            'Return ONLY valid JSON: {"has_photo": boolean, "photo_bounds": {"x": number, "y": number, '.
            '"width": number, "height": number} or null}. '.
            'Coordinates are percentages: x=left edge, y=top edge, width=box width, height=box height. '.
            'Return ONLY the JSON, no other text.';

        $result = $this->callApi($model, $prompt, $imageBase64);

        $photoData = $this->parsePhotoDetection($result['content']);

        if ($photoData['has_photo'] && $photoData['photo_bounds']) {
            $imageInfo = getimagesize($imagePath);
            $normalizedBounds = $this->normalizeCoordinates(
                $photoData['photo_bounds'],
                $imageInfo[0],
                $imageInfo[1]
            );

            $photoData['photo_bounds'] = $normalizedBounds;
        }

        return $photoData;
    }

    /**
     * Extract portrait from image using bounding box
     *
     * @param  string  $imagePath  Source image path
     * @param  array  $bounds  Bounding box {x, y, width, height} as percentages
     * @param  string  $outputPath  Output portrait path
     * @param  int  $maxSize  Maximum dimension in pixels (default: 400)
     * @param  int  $quality  JPEG quality (default: 85)
     * @return bool Success
     */
    public function extractPortrait(
        string $imagePath,
        array $bounds,
        string $outputPath,
        int $maxSize = 400,
        int $quality = 85
    ): bool {
        if (! isset($bounds['x'], $bounds['y'], $bounds['width'], $bounds['height'])) {
            return false;
        }

        try {
            $imagick = new \Imagick($imagePath);
            $imageWidth = $imagick->getImageWidth();
            $imageHeight = $imagick->getImageHeight();

            // Convert percentages to pixels
            $x = (int) ($imageWidth * $bounds['x'] / 100);
            $y = (int) ($imageHeight * $bounds['y'] / 100);
            $width = (int) ($imageWidth * $bounds['width'] / 100);
            $height = (int) ($imageHeight * $bounds['height'] / 100);

            // Crop the portrait
            $imagick->cropImage($width, $height, $x, $y);
            $imagick->setImagePage(0, 0, 0, 0);

            // Resize to max dimensions
            $imagick->scaleImage($maxSize, $maxSize, true);

            // Save as JPEG
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality($quality);
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            return true;
        } catch (\Exception $e) {
            Log::error('Portrait extraction failed', [
                'error' => $e->getMessage(),
                'image' => $imagePath,
                'bounds' => $bounds,
            ]);

            return false;
        }
    }

    /**
     * Complete extraction: text + photo in one call
     *
     * @param  string  $imagePath  Path to image file
     * @param  string  $textModel  Model for text extraction
     * @param  string  $photoModel  Model for photo detection
     * @return array{text: array, photo: array, portrait_path: ?string}
     *
     * @throws \Exception
     */
    public function extractComplete(
        string $imagePath,
        string $textModel = self::MODEL_GEMINI_3_FLASH,
        string $photoModel = self::MODEL_CLAUDE_SONNET_45
    ): array {
        // Extract text
        $textData = $this->extractDeathNotice($imagePath, $textModel);

        // Detect photo
        $photoData = $this->detectPortrait($imagePath, $photoModel);

        // Extract portrait if found
        $portraitPath = null;
        if ($photoData['has_photo'] && $photoData['photo_bounds']) {
            $dir = dirname($imagePath);
            $portraitPath = $dir.'/portrait_abacusai_'.uniqid().'.jpg';

            if ($this->extractPortrait($imagePath, $photoData['photo_bounds'], $portraitPath)) {
                Log::info('Portrait extracted via Abacus.AI', [
                    'source' => $imagePath,
                    'portrait' => $portraitPath,
                    'model' => $photoModel,
                ]);
            } else {
                $portraitPath = null;
            }
        }

        return [
            'text' => $textData,
            'photo' => $photoData,
            'portrait_path' => $portraitPath,
        ];
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
     * Parse photo detection response
     */
    private function parsePhotoDetection(string $content): array
    {
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $content);

        $data = json_decode($content, true);

        if (! $data || ! isset($data['has_photo'])) {
            throw new \Exception('Failed to parse photo detection response');
        }

        return [
            'has_photo' => $data['has_photo'],
            'photo_bounds' => $data['photo_bounds'] ?? null,
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
     * Normalize coordinate system (pixels to percentages if needed)
     *
     * @param  array  $bounds  Bounding box coordinates
     * @param  int  $imageWidth  Image width in pixels
     * @param  int  $imageHeight  Image height in pixels
     * @return array Normalized coordinates as percentages
     */
    private function normalizeCoordinates(array $bounds, int $imageWidth, int $imageHeight): array
    {
        if (! isset($bounds['x'], $bounds['y'], $bounds['width'], $bounds['height'])) {
            return $bounds;
        }

        if ($bounds['x'] > 100 || $bounds['y'] > 100 || $bounds['width'] > 100 || $bounds['height'] > 100) {
            Log::warning('Abacus.AI returned pixel coordinates, converting to percentages', [
                'original' => $bounds,
                'image_size' => "{$imageWidth}x{$imageHeight}",
            ]);

            return [
                'x' => round(($bounds['x'] / $imageWidth) * 100, 2),
                'y' => round(($bounds['y'] / $imageHeight) * 100, 2),
                'width' => round(($bounds['width'] / $imageWidth) * 100, 2),
                'height' => round(($bounds['height'] / $imageHeight) * 100, 2),
            ];
        }

        return $bounds;
    }

    /**
     * Validate portrait extraction quality
     *
     * @param  string  $portraitPath  Path to extracted portrait
     * @return array{valid: bool, reason?: string, size?: int, dimensions?: string, quality?: string}
     */
    public function validatePortraitQuality(string $portraitPath): array
    {
        if (! file_exists($portraitPath)) {
            return ['valid' => false, 'reason' => 'File not found'];
        }

        $size = filesize($portraitPath);
        $imageInfo = getimagesize($portraitPath);

        if (! $imageInfo) {
            return ['valid' => false, 'reason' => 'Invalid image'];
        }

        [$width, $height] = $imageInfo;

        if ($size < 5000) {
            return [
                'valid' => false,
                'reason' => 'File too small (< 5 KB)',
                'size' => $size,
                'dimensions' => "{$width}x{$height}",
            ];
        }

        if ($width < 100 || $height < 100) {
            return [
                'valid' => false,
                'reason' => 'Dimensions too small',
                'size' => $size,
                'dimensions' => "{$width}x{$height}",
            ];
        }

        if ($width === $height && $size < 10000) {
            return [
                'valid' => false,
                'reason' => 'Square crop (likely wrong area)',
                'size' => $size,
                'dimensions' => "{$width}x{$height}",
            ];
        }

        $quality = $size > 30000 ? 'excellent' : ($size > 15000 ? 'good' : 'acceptable');

        return [
            'valid' => true,
            'size' => $size,
            'dimensions' => "{$width}x{$height}",
            'quality' => $quality,
        ];
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
                'recommended_for' => ['photo_detection', 'complex_documents'],
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
