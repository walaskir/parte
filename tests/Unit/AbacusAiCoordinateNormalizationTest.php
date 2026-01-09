<?php

use App\Services\AbacusAiVisionService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Helper to test private/protected methods via reflection
 * Uses a mock service instance that doesn't require Laravel config
 */
function invokeNormalizeCoordinates(array $bounds, int $imageWidth, int $imageHeight): array
{
    // Create service with dummy values to avoid config dependency
    $service = new AbacusAiVisionService('dummy_key', 'https://example.com');
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('normalizeCoordinates');
    $method->setAccessible(true);

    return $method->invoke($service, $bounds, $imageWidth, $imageHeight);
}

test('normalizeCoordinates converts pixel coordinates to percentages', function () {
    Log::spy(); // Mock Log facade

    // Image dimensions: 2480x3508 (A4 at 300 DPI)
    $imageWidth = 2480;
    $imageHeight = 3508;

    // Pixel coordinates from Gemini 3 Flash
    $pixelCoords = [
        'x' => 620,
        'y' => 877,
        'width' => 1240,
        'height' => 1754,
    ];

    $result = invokeNormalizeCoordinates($pixelCoords, $imageWidth, $imageHeight);

    expect($result)->toBeArray()
        ->and($result['x'])->toBe(25.0) // 620 / 2480 * 100
        ->and($result['y'])->toBe(25.0) // 877 / 3508 * 100
        ->and($result['width'])->toBe(50.0) // 1240 / 2480 * 100
        ->and($result['height'])->toBe(50.0); // 1754 / 3508 * 100
});

test('normalizeCoordinates returns percentages unchanged', function () {
    // Image dimensions (irrelevant for percentage input)
    $imageWidth = 2480;
    $imageHeight = 3508;

    // Already percentage coordinates (< 100)
    $percentCoords = [
        'x' => 25.5,
        'y' => 30.75,
        'width' => 45.2,
        'height' => 52.3,
    ];

    $result = invokeNormalizeCoordinates($percentCoords, $imageWidth, $imageHeight);

    expect($result)->toBe($percentCoords);
});

test('normalizeCoordinates handles edge case with value 100', function () {
    $imageWidth = 2000;
    $imageHeight = 3000;

    // Edge case: exactly 100 (should be treated as percentage)
    $edgeCoords = [
        'x' => 100.0,
        'y' => 50.0,
        'width' => 25.0,
        'height' => 100.0,
    ];

    $result = invokeNormalizeCoordinates($edgeCoords, $imageWidth, $imageHeight);

    expect($result)->toBe($edgeCoords);
});

test('normalizeCoordinates handles mixed coordinates (converts all if any > 100)', function () {
    Log::spy(); // Mock Log facade

    $imageWidth = 1000;
    $imageHeight = 2000;

    // Mixed: some pixels (> 100), some percentages (<= 100)
    // BUT: If ANY value is > 100, ALL values are converted as pixels
    $mixedCoords = [
        'x' => 250, // pixel
        'y' => 40, // looks like percentage, but treated as pixel
        'width' => 500, // pixel
        'height' => 60, // looks like percentage, but treated as pixel
    ];

    $result = invokeNormalizeCoordinates($mixedCoords, $imageWidth, $imageHeight);

    // Since x=250 and width=500 are > 100, ALL values are treated as pixels
    expect($result)->toBeArray()
        ->and($result['x'])->toBe(25.0) // 250 / 1000 * 100
        ->and($result['y'])->toBe(2.0) // 40 / 2000 * 100 (NOT kept as 40!)
        ->and($result['width'])->toBe(50.0) // 500 / 1000 * 100
        ->and($result['height'])->toBe(3.0); // 60 / 2000 * 100 (NOT kept as 60!)
});

test('normalizeCoordinates rounds to 2 decimal places', function () {
    Log::spy(); // Mock Log facade

    $imageWidth = 2480;
    $imageHeight = 3508;

    // Pixel coordinates that will produce decimals
    $pixelCoords = [
        'x' => 621, // 621 / 2480 * 100 = 25.040322...
        'y' => 878, // 878 / 3508 * 100 = 25.028517...
        'width' => 1241, // 1241 / 2480 * 100 = 50.040322...
        'height' => 1755, // 1755 / 3508 * 100 = 50.028517...
    ];

    $result = invokeNormalizeCoordinates($pixelCoords, $imageWidth, $imageHeight);

    expect($result)->toBeArray()
        ->and($result['x'])->toBe(25.04)
        ->and($result['y'])->toBe(25.03)
        ->and($result['width'])->toBe(50.04)
        ->and($result['height'])->toBe(50.03);
});
