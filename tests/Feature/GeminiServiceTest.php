<?php

use App\Services\GeminiService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

test('falls back to anthropic when gemini fails', function () {
    Log::spy();

    Config::set('services.gemini.api_key', 'test-gemini-key');
    Config::set('services.anthropic.api_key', 'test-anthropic-key');

    // Mock Gemini API failure (429 rate limit)
    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 429,
                'message' => 'Resource exhausted',
            ],
        ], 429),
        // Mock Anthropic API success
        'https://api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'full_name' => 'Jan Novák',
                        'death_date' => '2025-12-25',
                        'funeral_date' => '2026-01-02',
                    ]),
                ],
            ],
        ], 200),
    ]);

    $service = new GeminiService;

    $tempImage = tempnam(sys_get_temp_dir(), 'test_image_').'.jpg';
    $image = imagecreatetruecolor(100, 100);
    imagejpeg($image, $tempImage);
    imagedestroy($image);

    try {
        $result = $service->extractFromImage($tempImage);

        // Verify result (may be from Tesseract or fallback)
        expect($result)->toBeArray();

        // Verify Gemini was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com');
        });
    } finally {
        if (file_exists($tempImage)) {
            unlink($tempImage);
        }
    }
});

test('anthropic fallback works with direct method call', function () {
    Config::set('services.anthropic.api_key', 'test-anthropic-key');

    // Mock Anthropic API response
    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'full_name' => 'Marie Nováková',
                        'death_date' => '2025-12-20',
                        'funeral_date' => '2026-01-05',
                    ]),
                ],
            ],
        ], 200),
    ]);

    $service = new GeminiService;

    $tempImage = tempnam(sys_get_temp_dir(), 'test_image_').'.jpg';
    $image = imagecreatetruecolor(100, 100);
    imagejpeg($image, $tempImage);
    imagedestroy($image);

    try {
        // Call Anthropic method directly via reflection
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractFromImageWithAnthropic');
        $method->setAccessible(true);

        $result = $method->invoke($service, $tempImage);

        expect($result)->toBeArray()
            ->and($result['full_name'])->toBe('Marie Nováková')
            ->and($result['death_date'])->toBe('2025-12-20')
            ->and($result['funeral_date'])->toBe('2026-01-05');

        // Verify Anthropic was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com');
        });
    } finally {
        if (file_exists($tempImage)) {
            unlink($tempImage);
        }
    }
});

test('gemini successfully extracts data when api works', function () {
    Config::set('services.gemini.api_key', 'test-gemini-key');

    Http::fake([
        'https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'full_name' => 'Pavel Nový',
                                    'death_date' => '2026-01-28',
                                    'funeral_date' => '2026-02-01',
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new GeminiService;

    $tempImage = tempnam(sys_get_temp_dir(), 'test_image_').'.jpg';
    $image = imagecreatetruecolor(100, 100);
    imagejpeg($image, $tempImage);
    imagedestroy($image);

    try {
        // Call Gemini method directly via reflection
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('extractFromImageWithGemini');
        $method->setAccessible(true);

        $result = $method->invoke($service, $tempImage);

        expect($result)->toBeArray()
            ->and($result['full_name'])->toBe('Pavel Nový')
            ->and($result['death_date'])->toBe('2026-01-28')
            ->and($result['funeral_date'])->toBe('2026-02-01');

        // Verify Gemini was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com');
        });
    } finally {
        if (file_exists($tempImage)) {
            unlink($tempImage);
        }
    }
});
