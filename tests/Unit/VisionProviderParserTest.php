<?php

use App\Services\VisionOcrService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Helper to test private parseProvider() method via reflection
 */
function invokeParseProvider(string $providerString): array
{
    // Mock required config to avoid exceptions
    config(['services.vision.text_provider' => 'gemini']);
    config(['services.vision.photo_provider' => 'gemini']);
    config(['services.gemini.api_key' => 'dummy_key']);

    $service = app(VisionOcrService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseProvider');
    $method->setAccessible(true);

    return $method->invoke($service, $providerString);
}

test('parseProvider parses abacusai with model', function () {
    $result = invokeParseProvider('abacusai/gemini-3-flash');

    expect($result)->toBe(['abacusai', 'gemini-3-flash']);
});

test('parseProvider parses abacusai with claude model', function () {
    $result = invokeParseProvider('abacusai/claude-sonnet-4.5');

    expect($result)->toBe(['abacusai', 'claude-sonnet-4.5']);
});

test('parseProvider parses provider without model', function () {
    $result = invokeParseProvider('gemini');

    expect($result)->toBe(['gemini', null]);
});

test('parseProvider parses zhipuai without model', function () {
    $result = invokeParseProvider('zhipuai');

    expect($result)->toBe(['zhipuai', null]);
});

test('parseProvider handles empty string', function () {
    $result = invokeParseProvider('');

    expect($result)->toBe(['', null]);
});

test('parseProvider trims whitespace', function () {
    $result = invokeParseProvider('  abacusai / gemini-3-flash  ');

    expect($result)->toBe(['abacusai', 'gemini-3-flash']);
});

test('parseProvider handles multiple slashes (uses first split)', function () {
    $result = invokeParseProvider('abacusai/models/gemini-3-flash');

    expect($result)->toBe(['abacusai', 'models/gemini-3-flash']);
});

test('parseProvider preserves model version numbers', function () {
    $result = invokeParseProvider('abacusai/gemini-2.5-pro');

    expect($result)->toBe(['abacusai', 'gemini-2.5-pro']);
});

test('parseProvider handles anthropic provider', function () {
    $result = invokeParseProvider('anthropic');

    expect($result)->toBe(['anthropic', null]);
});
