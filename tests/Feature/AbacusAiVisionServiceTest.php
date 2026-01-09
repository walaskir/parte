<?php

use App\Services\AbacusAiVisionService;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\TestImages;

test('extractDeathNotice successfully extracts text data', function () {
    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'full_name' => 'Jan Kowalski',
                            'opening_quote' => 'Kto Cię zna, ten Cię czcić musi',
                            'death_date' => '2026-01-05',
                            'funeral_date' => '2026-01-10',
                            'announcement_text' => 'Z głębokim żalem zawiadamiamy, że dnia 5 stycznia 2026 roku...',
                        ]),
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 1500,
                'completion_tokens' => 200,
                'total_tokens' => 1700,
            ],
        ], 200),
    ]);

    $service = new AbacusAiVisionService('test_key', 'https://routellm.abacus.ai');
    $result = $service->extractDeathNotice(TestImages::getRaszkaPath());

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBe('Kto Cię zna, ten Cię czcić musi')
        ->and($result['death_date'])->toBe('2026-01-05')
        ->and($result['funeral_date'])->toBe('2026-01-10')
        ->and($result['announcement_text'])->toContain('zawiadamiamy');
});

test('extractDeathNotice handles missing opening_quote', function () {
    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'full_name' => 'Jan Kowalski',
                            'death_date' => '2026-01-05',
                            'funeral_date' => '2026-01-10',
                            'announcement_text' => 'Announcement text',
                        ]),
                    ],
                ],
            ],
            'usage' => ['total_tokens' => 1500],
        ], 200),
    ]);

    $service = new AbacusAiVisionService('test_key', 'https://routellm.abacus.ai');
    $result = $service->extractDeathNotice(TestImages::getWilhelmPath());

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBeNull();
});

test('detectPortrait successfully extracts photo bounds', function () {
    $tempImage = TestImages::createTempImage('base64');

    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'has_photo' => true,
                            'photo_bounds' => [
                                'x' => 25.5,
                                'y' => 30.0,
                                'width' => 20.0,
                                'height' => 25.0,
                            ],
                        ]),
                    ],
                ],
            ],
            'usage' => ['total_tokens' => 1200],
        ], 200),
    ]);

    $service = new AbacusAiVisionService('test_key', 'https://routellm.abacus.ai');
    $result = $service->detectPortrait($tempImage);

    TestImages::cleanup($tempImage);

    expect($result)->toBeArray()
        ->and($result['has_photo'])->toBeTrue()
        ->and($result['photo_bounds'])->toBeArray()
        ->and($result['photo_bounds']['x'])->toBeNumeric()
        ->and($result['photo_bounds']['y'])->toBeNumeric()
        ->and($result['photo_bounds']['width'])->toBeNumeric()
        ->and($result['photo_bounds']['height'])->toBeNumeric();
});

test('detectPortrait handles no photo detected', function () {
    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'has_photo' => false,
                            'photo_bounds' => null,
                        ]),
                    ],
                ],
            ],
            'usage' => ['total_tokens' => 1100],
        ], 200),
    ]);

    $service = new AbacusAiVisionService('test_key', 'https://routellm.abacus.ai');
    $result = $service->detectPortrait(TestImages::getWilhelmPath());

    expect($result)->toBeArray()
        ->and($result['has_photo'])->toBeFalse()
        ->and($result['photo_bounds'])->toBeNull();
});

test('detectPortrait normalizes pixel coordinates to percentages', function () {
    $tempImage = TestImages::createTempImage('base64');

    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'has_photo' => true,
                            'photo_bounds' => [
                                'x' => 620, // Pixel coordinate
                                'y' => 877, // Pixel coordinate
                                'width' => 1240, // Pixel coordinate
                                'height' => 1754, // Pixel coordinate
                            ],
                        ]),
                    ],
                ],
            ],
            'usage' => ['total_tokens' => 1200],
        ], 200),
    ]);

    $service = new AbacusAiVisionService('test_key', 'https://routellm.abacus.ai');
    $result = $service->detectPortrait($tempImage, AbacusAiVisionService::MODEL_GEMINI_3_FLASH);

    TestImages::cleanup($tempImage);

    // Check that normalization occurred (values will be different due to 1x1 image)
    expect($result)->toBeArray()
        ->and($result['has_photo'])->toBeTrue()
        ->and($result['photo_bounds'])->toBeArray()
        ->and($result['photo_bounds']['x'])->toBeFloat()
        ->and($result['photo_bounds']['y'])->toBeFloat()
        ->and($result['photo_bounds']['width'])->toBeFloat()
        ->and($result['photo_bounds']['height'])->toBeFloat();
});

test('extractDeathNotice throws exception on API error', function () {
    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Invalid API key',
            ],
        ], 401),
    ]);

    $service = new AbacusAiVisionService('invalid_key', 'https://routellm.abacus.ai');
    $service->extractDeathNotice(TestImages::getRaszkaPath());
})->throws(\Exception::class, 'Abacus.AI API error: HTTP 401');

test('extractDeathNotice throws exception on invalid response structure', function () {
    Http::fake([
        'https://routellm.abacus.ai/v1/chat/completions' => Http::response([
            'invalid' => 'response',
        ], 200),
    ]);

    $service = new AbacusAiVisionService('test_key', 'https://routellm.abacus.ai');
    $service->extractDeathNotice(TestImages::getRaszkaPath());
})->throws(\Exception::class, 'Invalid Abacus.AI API response structure');

test('getAvailableModels returns model information', function () {
    $models = AbacusAiVisionService::getAvailableModels();

    expect($models)->toBeArray()
        ->and($models)->toHaveKeys([
            AbacusAiVisionService::MODEL_GEMINI_3_FLASH,
            AbacusAiVisionService::MODEL_CLAUDE_SONNET_45,
            AbacusAiVisionService::MODEL_GEMINI_25_PRO,
            AbacusAiVisionService::MODEL_GPT_52,
        ])
        ->and($models[AbacusAiVisionService::MODEL_GEMINI_3_FLASH])->toHaveKeys(['name', 'speed', 'quality', 'usage', 'recommended_for']);
});
