<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use thiagoalessio\TesseractOCR\TesseractOCR;

class GeminiService
{
    private string $apiKey;

    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    private string $model = 'gemini-2.0-flash-exp';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key is not configured. Please set GEMINI_API_KEY in .env file.');
        }
    }

    /**
     * Extract information from parte image using Tesseract OCR
     */
    public function extractFromImage(string $imagePath, bool $extractDeathDate = false): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                Log::error('Image file not found for OCR extraction', ['path' => $imagePath]);

                return null;
            }

            // Use Tesseract OCR to extract text from image
            $text = $this->ocrImage($imagePath);

            if (empty($text)) {
                Log::warning('Tesseract OCR returned empty text, trying OpenRouter fallback', ['image_path' => $imagePath]);

                // If OCR returns empty text, try OpenRouter fallback immediately
                return $this->extractFromImageWithGemini($imagePath);
            }

            // Parse extracted text for name and date
            return $this->parseParteText($text, $extractDeathDate, $imagePath);
        } catch (Exception $e) {
            Log::error('OCR extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Extract text from image using Tesseract OCR
     */
    private function ocrImage(string $imagePath): string
    {
        try {
            $ocr = new TesseractOCR($imagePath);

            // Use Czech and Polish languages for better recognition
            $ocr->lang('ces', 'pol', 'eng');

            // PSM 3 = Fully automatic page segmentation, but no OSD (default)
            $ocr->psm(3);

            $text = $ocr->run();

            return trim($text);
        } catch (Exception $e) {
            Log::error('Tesseract OCR failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return '';
        }
    }

    /**
     * Parse parte text to extract name, death date, and funeral date
     * Uses regex first, then falls back to Gemini AI if regex fails
     */
    private function parseParteText(string $text, bool $extractDeathDate = false, ?string $imagePath = null): ?array
    {
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_values(array_filter($lines)); // Odstraň prázdné řádky a reindexuj

        $fullName = null;
        $deathDate = null;
        $funeralDate = null;

        // Hledej jméno - může být na více řádcích, velkými písmeny
        $collectingName = false;
        $nameParts = [];

        foreach ($lines as $i => $line) {
            // Czech: jméno po slovech jako "paní", "panem", "pan"
            if (preg_match('/\b(paní|panem|pan)\s*$/iu', $line)) {
                $collectingName = true;

                continue;
            }

            // Polish: jméno na stejném řádku s "§p.", "śp.", "Sp."
            if (preg_match('/(?:§p\.|śp\.|Sp\.)\s+(.+)/iu', $line, $matches)) {
                $fullName = trim($matches[1]);

                break;
            }

            // Pokud sbíráme jméno (Czech format)
            if ($collectingName) {
                // Zkontroluj, jestli řádek vypadá jako jméno (velká písmena nebo první velké)
                if (preg_match('/^[A-ZČŘŠŽÝÁÍÉÚŮĎŤŇÓĚĻĶŅĢ]/u', $line)) {
                    // Přeskoč klíčová slova, která nejsou jméno
                    if (preg_match('/^(ROZLOUČENÍ|POHŘEB|SMUTEČNÍ|PARTE|KREMACE|OBŘAD)/iu', $line)) {
                        break;
                    }

                    $nameParts[] = $line;

                    // Pokud je další řádek prázdný, končí velkým písmenem nebo neexistuje, končíme
                    if (! isset($lines[$i + 1]) ||
                        preg_match('/^[a-z]/u', $lines[$i + 1])) {
                        break;
                    }
                } else {
                    // Našli jsme něco, co už není jméno
                    break;
                }
            }
        }

        // Pokud jsme nenašli jméno přes Polish format, zkusíme Czech format
        if (! $fullName && ! empty($nameParts)) {
            $fullName = implode(' ', $nameParts);
        }

        // Hledej data úmrtí a pohřbu
        $fullText = implode(' ', $lines);

        // PRIORITY 1: Text dates with month names (Czech + Polish)
        // These have HIGHER priority than numeric dates to avoid confusion

        // Czech month names (Pattern: "31. PROSINEC 2025" or "31 prosince 2025")
        if (! $deathDate && preg_match('/(\d{1,2})\.\s*(\w+)\s+(\d{4})/iu', $fullText, $matches)) {
            $czechMonths = [
                'ledna' => 1, 'unor' => 2, 'února' => 2, 'unora' => 2, 'březen' => 3, 'března' => 3, 'brezna' => 3,
                'duben' => 4, 'dubna' => 4, 'kveten' => 5, 'května' => 5, 'kvetna' => 5,
                'červen' => 6, 'června' => 6, 'cervna' => 6, 'cerven' => 6,
                'červenec' => 7, 'července' => 7, 'cervence' => 7, 'cervenec' => 7,
                'srpen' => 8, 'srpna' => 8, 'srpna' => 8,
                'září' => 9, 'zari' => 9, 'říjen' => 10, 'října' => 10, 'rijna' => 10, 'rijen' => 10,
                'listopad' => 11, 'listopadu' => 11, 'prosinec' => 12, 'prosince' => 12, 'prosince' => 12,
            ];

            $day = (int) $matches[1];
            $monthName = mb_strtolower($matches[2], 'UTF-8');
            $year = (int) $matches[3];

            if (isset($czechMonths[$monthName])) {
                $month = $czechMonths[$monthName];
                try {
                    $deathDate = Carbon::create($year, $month, $day)->format('Y-m-d');
                    Log::info('GeminiService: Found Czech death_date', [
                        'text_match' => $matches[0],
                        'death_date' => $deathDate,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('GeminiService: Failed to parse Czech month date', [
                        'match' => $matches[0],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Polish month names
        // Pattern 1: Date BEFORE keyword - "dnia 24 grudnia 2025 ... zmarł"
        if (! $deathDate && preg_match('/(?:dnia|dnia\s+\d{1,2})\s+(\d{1,2})\s+(stycznia|lutego|marca|kwietnia|maja|czerwca|lipca|sierpnia|września|października|listopada|grudnia)\s+(\d{4})(?:.*?zmarł[a]?)?/iu', $fullText, $matches)) {
            try {
                $polishMonths = [
                    'stycznia' => 1, 'lutego' => 2, 'marca' => 3, 'kwietnia' => 4,
                    'maja' => 5, 'czerwca' => 6, 'lipca' => 7, 'sierpnia' => 8,
                    'września' => 9, 'października' => 10, 'listopada' => 11, 'grudnia' => 12,
                ];

                $month = $polishMonths[mb_strtolower($matches[2])] ?? null;
                if ($month) {
                    $deathDate = Carbon::createFromDate($matches[3], $month, $matches[1])->format('Y-m-d');
                    Log::info('GeminiService: Found Polish death_date (date before keyword)', ['death_date' => $deathDate]);
                }
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse Polish death date (date before)', [
                    'date_string' => "{$matches[1]} {$matches[2]} {$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Pattern 2: Keyword BEFORE date - "zmarł w środę dnia 24 grudnia 2025"
        if (! $deathDate && preg_match('/(?:zmarł[a]?\s+(?:w[eo]?\s+\w+\s+)?(?:dnia\s+)?|data\s+śmierci[:\s]+)(\d{1,2})\s+(stycznia|lutego|marca|kwietnia|maja|czerwca|lipca|sierpnia|września|października|listopada|grudnia)\s+(\d{4})/iu', $fullText, $matches)) {
            try {
                $polishMonths = [
                    'stycznia' => 1, 'lutego' => 2, 'marca' => 3, 'kwietnia' => 4,
                    'maja' => 5, 'czerwca' => 6, 'lipca' => 7, 'sierpnia' => 8,
                    'września' => 9, 'października' => 10, 'listopada' => 11, 'grudnia' => 12,
                ];

                $month = $polishMonths[mb_strtolower($matches[2])] ?? null;
                if ($month) {
                    $deathDate = Carbon::createFromDate($matches[3], $month, $matches[1])->format('Y-m-d');
                    Log::info('GeminiService: Found Polish death_date (keyword before date)', ['death_date' => $deathDate]);
                }
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse Polish death date (keyword before)', [
                    'date_string' => "{$matches[1]} {$matches[2]} {$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // PRIORITY 2: Numeric death date patterns
        // Look for death date patterns
        // Handles:
        // Czech: "zemřel/a dne DD.MM.YYYY", "zemřel ve čtvrtek DD.MM.YYYY", "zemřel tise ve středu DD.MM.YYYY"
        // Polish: "zmarł/a dnia DD.MM.YYYY", "zmarł w środę dnia DD.MM.YYYY"
        // Also: "† DD.MM.YYYY", "data śmierci: DD.MM.YYYY"
        // Pattern allows optional day-of-week and OCR typo variants (tise/tiše, zemrel/zemřel)
        if (! $deathDate && preg_match('/(?:zem[řr]el[a]?\s+(?:tise|tiše)?\s*(?:v[eo]?\s+\w+\s+)?(?:dne\s+)?|zmarł[a]?\s+(?:w[eo]?\s+\w+\s+)?(?:dnia\s+)?|†\s*|data\s+śmierci[:\s]+)(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/iu', $fullText, $matches)) {
            try {
                Carbon::setLocale('cs');
                $deathDate = Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
                Log::info('GeminiService: Found death_date via regex (numeric format)', ['date' => $deathDate]);
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse death date', [
                    'date_string' => "{$matches[1]}.{$matches[2]}.{$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Pattern: Date BEFORE keyword (e.g., "dne 29.12.2025 zemřel" or "dnia 30.12.2025 zmarł")
        if (! $deathDate && preg_match('/(?:dne|dnia)\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})(?:\s+(?:zemřel[a]?|zmarł[a]?))?/iu', $fullText, $matches)) {
            try {
                Carbon::setLocale('cs');
                $deathDate = Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
                Log::info('GeminiService: Found death_date (date before keyword)', [
                    'date' => $deathDate,
                    'matched_text' => $matches[0],
                ]);
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse death date (date before keyword)', [
                    'date_string' => "{$matches[1]}.{$matches[2]}.{$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Look for funeral date patterns (Czech: "pohřeb", "rozloučení", Polish: "pogrzeb", "pożegnanie")
        if (preg_match('/(?:pohřeb|rozloučení|pogrzeb|pożegnanie)[^\d]*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/iu', $fullText, $matches)) {
            try {
                Carbon::setLocale('cs');
                $funeralDate = Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse funeral date', [
                    'date_string' => "{$matches[1]}.{$matches[2]}.{$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Polish text dates for funeral date
        if (! $funeralDate && preg_match('/(?:pogrzeb|pożegnanie)[^\d]*(\d{1,2})\s+(stycznia|lutego|marca|kwietnia|maja|czerwca|lipca|sierpnia|września|października|listopada|grudnia)\s+(\d{4})/iu', $fullText, $matches)) {
            try {
                $polishMonths = [
                    'stycznia' => 1, 'lutego' => 2, 'marca' => 3, 'kwietnia' => 4,
                    'maja' => 5, 'czerwca' => 6, 'lipca' => 7, 'sierpnia' => 8,
                    'września' => 9, 'października' => 10, 'listopada' => 11, 'grudnia' => 12,
                ];

                $month = $polishMonths[mb_strtolower($matches[2])] ?? null;
                if ($month) {
                    $funeralDate = Carbon::createFromDate($matches[3], $month, $matches[1])->format('Y-m-d');
                }
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse Polish funeral date', [
                    'date_string' => "{$matches[1]} {$matches[2]} {$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: if we didn't find specific patterns, extract all dates and use heuristics
        if (! $deathDate && ! $funeralDate) {
            preg_match_all('/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/', $fullText, $allMatches, PREG_SET_ORDER);

            if (count($allMatches) >= 2) {
                // First date is typically death date, second is funeral date
                try {
                    $deathDate = Carbon::createFromFormat('j.n.Y', "{$allMatches[0][1]}.{$allMatches[0][2]}.{$allMatches[0][3]}")->format('Y-m-d');
                    $funeralDate = Carbon::createFromFormat('j.n.Y', "{$allMatches[1][1]}.{$allMatches[1][2]}.{$allMatches[1][3]}")->format('Y-m-d');
                } catch (\Exception $e) {
                    Log::warning('GeminiService: Failed to parse fallback dates', ['error' => $e->getMessage()]);
                }
            } elseif (count($allMatches) === 1) {
                // Only one date - assume it's funeral date
                try {
                    $funeralDate = Carbon::createFromFormat('j.n.Y', "{$allMatches[0][1]}.{$allMatches[0][2]}.{$allMatches[0][3]}")->format('Y-m-d');
                } catch (\Exception $e) {
                    Log::warning('GeminiService: Failed to parse single fallback date', ['error' => $e->getMessage()]);
                }
            }
        }

        // Check if we successfully extracted required data
        $regexSuccess = false;
        if ($extractDeathDate) {
            // For death_date extraction mode, we need death_date
            $regexSuccess = ($deathDate !== null);
        } else {
            // For name+funeral_date extraction mode, we need full_name
            $regexSuccess = ($fullName !== null);
        }

        // If regex failed and we have image path, try Gemini AI fallback
        if (! $regexSuccess && $imagePath && file_exists($imagePath)) {
            Log::info('GeminiService: Regex extraction failed, trying Gemini AI fallback', [
                'extract_mode' => $extractDeathDate ? 'death_date' : 'name+funeral_date',
                'image_path' => $imagePath,
            ]);

            $geminiResult = $this->extractFromImageWithGemini($imagePath);

            if ($geminiResult) {
                // Merge Gemini results with regex results (Gemini takes priority)
                if ($extractDeathDate) {
                    // Only update death_date in death_date extraction mode
                    if (isset($geminiResult['death_date']) && $geminiResult['death_date']) {
                        $deathDate = $geminiResult['death_date'];
                        Log::info('GeminiService: Successfully extracted death_date via Gemini AI fallback');
                    }
                } else {
                    // Update name and funeral_date in name extraction mode
                    if (isset($geminiResult['full_name']) && $geminiResult['full_name']) {
                        $fullName = $geminiResult['full_name'];
                    }
                    if (isset($geminiResult['funeral_date']) && $geminiResult['funeral_date']) {
                        $funeralDate = $geminiResult['funeral_date'];
                    }
                    if ($fullName) {
                        Log::info('GeminiService: Successfully extracted name via Gemini AI fallback', ['name' => $fullName]);
                    }
                }
            }
        }

        if (! $fullName && ! $extractDeathDate) {
            Log::warning('GeminiService: Could not extract name from OCR text (regex and Gemini failed)', ['text' => substr($fullText, 0, 500)]);
        }

        if (! $deathDate && $extractDeathDate) {
            Log::warning('GeminiService: Could not extract death_date from OCR text (regex and Gemini failed)', ['text' => substr($fullText, 0, 500)]);
        }

        return [
            'full_name' => $fullName,
            'death_date' => $deathDate,
            'funeral_date' => $funeralDate,
        ];
    }

    /**
     * Extract information from parte image using Gemini Vision (fallback method)
     */
    public function extractFromImageWithGemini(string $imagePath): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                Log::error('Image file not found for Gemini extraction', ['path' => $imagePath]);

                return null;
            }

            // Read and encode image
            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = $this->getMimeType($imagePath);

            $prompt = "Analyze this death notice (parte/obituary) image. Extract the following information in JSON format:
{
  \"full_name\": \"Full name of the deceased (first name and last name together)\",
  \"death_date\": \"Date of death in YYYY-MM-DD format (or null if not found)\",
  \"funeral_date\": \"Date of the funeral in YYYY-MM-DD format (or null if not found)\"
}

The text may be in Czech or Polish. Look for:
- Death date: after words like 'zemřel/a', '†', 'zmarł/a', 'data śmierci'
- Funeral date: after words like 'pohřeb', 'rozloučení', 'pogrzeb', 'pożegnanie'
Dates can be in formats like '2.1.2026', '31.12.2025', or written as 'pátek 2. ledna 2026'.
Return ONLY the JSON object, nothing else.";

            // Use direct Google Gemini API
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
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
                        'temperature' => 0.4,
                        'maxOutputTokens' => 1024,
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

            // Parse JSON response
            $json = $this->extractJson($responseText);

            if ($json) {
                return [
                    'full_name' => $json['full_name'] ?? null,
                    'death_date' => $json['death_date'] ?? null,
                    'funeral_date' => $json['funeral_date'] ?? null,
                ];
            }

            Log::warning('Gemini returned invalid JSON', ['response' => $responseText]);

            return null;
        } catch (Exception $e) {
            Log::error('Gemini extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return null;
        }
    }

    /**
     * Send a chat message to Gemini
     */
    public function chat(string $message): ?string
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $message],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('Gemini chat request failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (Exception $e) {
            Log::error('Gemini chat failed', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return null;
        }
    }

    /**
     * Get MIME type for image file
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            default => 'image/jpeg',
        };
    }

    /**
     * Extract JSON from response text
     */
    private function extractJson(string $text): ?array
    {
        // Try to find JSON in response (may be wrapped in markdown code blocks)
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // Try direct JSON parsing
        $json = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return null;
    }
}
