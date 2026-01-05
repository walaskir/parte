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

5. PHOTO DETECTION:
   - Does this parte contain a portrait photograph of the deceased person?
   - If YES: Set \"has_photo\": true
   - Provide brief description (approximate age, gender, attire if visible)
   - Provide bounding box as PERCENTAGE of image dimensions (0-100%)
   - Photo is typically in upper left/right corner or center of document
   - If NO photo: Set \"has_photo\": false, omit \"photo_description\" and \"photo_bbox\"

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
