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
    public function extractFromImage(string $imagePath): ?array
    {
        try {
            if (! file_exists($imagePath)) {
                Log::error('Image file not found for OCR extraction', ['path' => $imagePath]);

                return null;
            }

            // Use Tesseract OCR to extract text from image
            $text = $this->ocrImage($imagePath);

            if (empty($text)) {
                Log::warning('Tesseract OCR returned empty text', ['image_path' => $imagePath]);

                return null;
            }

            // Parse extracted text for name and date
            return $this->parseParteText($text);
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
     * Parse parte text to extract name and funeral date
     */
    private function parseParteText(string $text): ?array
    {
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_values(array_filter($lines)); // Odstraň prázdné řádky a reindexuj

        $fullName = null;
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

        // Hledej datum pohřbu
        $fullText = implode(' ', $lines);

        // Format 1: Czech/Polish numeric date (d.m.Y nebo dd.mm.YYYY)
        if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u', $fullText, $matches)) {
            try {
                Carbon::setLocale('cs');
                $funeralDate = Carbon::createFromFormat('j.n.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}")->format('Y-m-d');
            } catch (\Exception $e) {
                Log::warning('GeminiService: Failed to parse numeric date', [
                    'date_string' => "{$matches[1]}.{$matches[2]}.{$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Format 2: Polish text date (e.g., "2 stycznia 2026")
        if (! $funeralDate && preg_match('/(\d{1,2})\s+(stycznia|lutego|marca|kwietnia|maja|czerwca|lipca|sierpnia|września|października|listopada|grudnia)\s+(\d{4})/iu', $fullText, $matches)) {
            try {
                // Map Polish month names to numbers
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
                Log::warning('GeminiService: Failed to parse Polish text date', [
                    'date_string' => "{$matches[1]} {$matches[2]} {$matches[3]}",
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $fullName) {
            Log::warning('GeminiService: Could not extract name from OCR text', ['text' => substr($fullText, 0, 500)]);
        }

        return [
            'full_name' => $fullName,
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
  \"funeral_date\": \"Date of the funeral in YYYY-MM-DD format (or null if not found)\"
}

The text may be in Czech or Polish. Look for dates in formats like '2.1.2026', '31.12.2025', or written as 'pátek 2. ledna 2026'.
Return ONLY the JSON object, nothing else.";

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
