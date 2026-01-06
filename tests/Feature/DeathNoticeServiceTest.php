<?php

use App\Models\DeathNotice;
use App\Services\DeathNoticeService;
use App\Services\PdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('service can get available sources', function () {
    $pdfGenerator = new PdfGeneratorService;
    $service = new DeathNoticeService($pdfGenerator);
    $sources = $service->getAvailableSources();

    expect($sources)->toBeArray()
        ->and($sources)->toContain('sadovy-jan', 'pshajdukova', 'psbk');
});

test('death notice model has required fillable fields', function () {
    $notice = new DeathNotice([
        'hash' => 'test12345678',
        'first_name' => 'Jan',
        'last_name' => 'Novák',
        'funeral_date' => '2024-01-15',
        'source' => 'Test Source',
        'source_url' => 'https://example.com',
    ]);

    expect($notice->hash)->toBe('test12345678')
        ->and($notice->first_name)->toBe('Jan')
        ->and($notice->last_name)->toBe('Novák')
        ->and($notice->source)->toBe('Test Source');
});

test('death notice full name accessor works', function () {
    $notice = new DeathNotice([
        'first_name' => 'Jan',
        'last_name' => 'Novák',
    ]);

    expect($notice->full_name)->toBe('Jan Novák');
});

test('death notice hash is unique', function () {
    DeathNotice::create([
        'hash' => 'unique123456',
        'first_name' => 'Jan',
        'last_name' => 'Novák',
        'source' => 'Test',
        'source_url' => 'https://example.com',
    ]);

    expect(function () {
        DeathNotice::create([
            'hash' => 'unique123456',
            'first_name' => 'Petr',
            'last_name' => 'Dvořák',
            'source' => 'Test',
            'source_url' => 'https://example.com',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('death notice can be created with valid data', function () {
    $notice = DeathNotice::create([
        'hash' => 'abcdef123456',
        'first_name' => 'Marie',
        'last_name' => 'Svobodová',
        'funeral_date' => '2024-02-20',
        'source' => 'Test Source',
        'source_url' => 'https://example.com/notice',
    ]);

    $this->assertDatabaseHas('death_notices', [
        'hash' => 'abcdef123456',
        'first_name' => 'Marie',
        'last_name' => 'Svobodová',
    ]);

    expect($notice)->toBeInstanceOf(DeathNotice::class)
        ->and($notice->id)->not->toBeNull();
});
