<?php

use App\Services\AbacusAiVisionService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Helper to test private parseTextExtraction() method via reflection
 */
function invokeParseTextExtraction(string $jsonContent): array
{
    $service = new AbacusAiVisionService('dummy_key', 'https://example.com');
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseTextExtraction');
    $method->setAccessible(true);

    return $method->invoke($service, $jsonContent);
}

test('parseTextExtraction handles valid opening_quote', function () {
    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => 'Kto Cię zna, ten Cię czcić musi',
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Z głębokim żalem zawiadamiamy...',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBe('Kto Cię zna, ten Cię czcić musi')
        ->and($result['death_date'])->toBe('2026-01-05')
        ->and($result['funeral_date'])->toBe('2026-01-10')
        ->and($result['announcement_text'])->toBe('Z głębokim żalem zawiadamiamy...');
});

test('parseTextExtraction handles missing opening_quote', function () {
    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Z głębokim żalem zawiadamiamy...',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBeNull()
        ->and($result['announcement_text'])->toBe('Z głębokim żalem zawiadamiamy...');
});

test('parseTextExtraction handles null opening_quote', function () {
    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => null,
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Z głębokim żalem zawiadamiamy...',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBeNull();
});

test('parseTextExtraction handles empty string opening_quote', function () {
    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => '',
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Z głębokim żalem zawiadamiamy...',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBe('');
});

test('parseTextExtraction warns when opening_quote exceeds 500 characters', function () {
    Log::spy();

    $longQuote = str_repeat('This is a very long opening quote. ', 20); // ~700 characters

    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => $longQuote,
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Z głębokim żalem zawiadamiamy...',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['opening_quote'])->toBe($longQuote)
        ->and(strlen($result['opening_quote']))->toBeGreaterThan(500);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Opening quote suspiciously long, might be full announcement', \Mockery::type('array'));
});

test('parseTextExtraction accepts opening_quote exactly 500 characters without warning', function () {
    Log::spy();

    $exactQuote = str_repeat('x', 500); // Exactly 500 characters

    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => $exactQuote,
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Z głębokim żalem zawiadamiamy...',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['opening_quote'])->toBe($exactQuote)
        ->and(strlen($result['opening_quote']))->toBe(500);

    Log::shouldNotHaveReceived('warning');
});

test('parseTextExtraction removes markdown code blocks', function () {
    $jsonContent = "```json\n".json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => 'Quote here',
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
        'announcement_text' => 'Announcement',
    ])."\n```";

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['opening_quote'])->toBe('Quote here');
});

test('parseTextExtraction throws exception when full_name is missing', function () {
    $jsonContent = json_encode([
        'opening_quote' => 'Quote here',
        'death_date' => '2026-01-05',
        'announcement_text' => 'Announcement',
    ]);

    invokeParseTextExtraction($jsonContent);
})->throws(\Exception::class, 'Failed to parse text extraction response');

test('parseTextExtraction throws exception for invalid JSON', function () {
    $jsonContent = 'This is not valid JSON';

    invokeParseTextExtraction($jsonContent);
})->throws(\Exception::class, 'Failed to parse text extraction response');

test('parseTextExtraction handles missing announcement_text', function () {
    $jsonContent = json_encode([
        'full_name' => 'Jan Kowalski',
        'opening_quote' => 'Quote here',
        'death_date' => '2026-01-05',
        'funeral_date' => '2026-01-10',
    ]);

    $result = invokeParseTextExtraction($jsonContent);

    expect($result)->toBeArray()
        ->and($result['full_name'])->toBe('Jan Kowalski')
        ->and($result['announcement_text'])->toBe(''); // Defaults to empty string
});

test('cleanFullName removes śp. prefix variants', function () {
    $service = new AbacusAiVisionService('test', 'test');
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('cleanFullName');
    $method->setAccessible(true);

    expect($method->invoke($service, 'śp. Stanislav Raszka'))->toBe('Stanislav Raszka')
        ->and($method->invoke($service, 'Śp. Jan Novák'))->toBe('Jan Novák')
        ->and($method->invoke($service, 'sp. Maria Kowalska'))->toBe('Maria Kowalska')
        ->and($method->invoke($service, 'ś.p. Josef Dvořák'))->toBe('Josef Dvořák')
        ->and($method->invoke($service, 'Ś.p. Anna Dvořáková'))->toBe('Anna Dvořáková');
});

test('cleanFullName handles names without prefix', function () {
    $service = new AbacusAiVisionService('test', 'test');
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('cleanFullName');
    $method->setAccessible(true);

    expect($method->invoke($service, 'Jan Novák'))->toBe('Jan Novák')
        ->and($method->invoke($service, 'Maria Kowalska'))->toBe('Maria Kowalska');
});

test('cleanFullName only removes first prefix', function () {
    $service = new AbacusAiVisionService('test', 'test');
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('cleanFullName');
    $method->setAccessible(true);

    // Edge case: multiple prefixes (should only remove first)
    expect($method->invoke($service, 'śp. śp. Jan Novák'))->toBe('śp. Jan Novák');
});
