<?php

use App\Models\DeathNotice;
use App\Services\DeathNoticeService;
use App\Services\PdfGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('service can get available sources', function () {
    // Seed funeral services
    $this->seed(\Database\Seeders\FuneralServiceSeeder::class);

    $pdfGenerator = new PdfGeneratorService;
    $service = new DeathNoticeService($pdfGenerator);
    $sources = $service->getAvailableSources();

    expect($sources)->toBeArray()
        ->and($sources)->toContain('sadovy-jan', 'pshajdukova', 'psbk');
});

test('death notice model has required fillable fields', function () {
    $notice = new DeathNotice([
        'hash' => 'test12345678',
        'full_name' => 'Jan Novák',
        'funeral_date' => '2024-01-15',
        'source' => 'Test Source',
        'source_url' => 'https://example.com',
    ]);

    expect($notice->hash)->toBe('test12345678')
        ->and($notice->full_name)->toBe('Jan Novák')
        ->and($notice->source)->toBe('Test Source');
});

test('death notice full name accessor works', function () {
    $notice = new DeathNotice([
        'full_name' => 'Jan Novák',
    ]);

    expect($notice->full_name)->toBe('Jan Novák');
});

test('death notice full name accessor returns null for missing value', function () {
    $notice = new DeathNotice([]);

    expect($notice->full_name)->toBeNull();
});

test('death notice hash is unique', function () {
    DeathNotice::create([
        'hash' => 'unique123456',
        'full_name' => 'Jan Novák',
        'source' => 'Test',
        'source_url' => 'https://example.com',
    ]);

    expect(function () {
        DeathNotice::create([
            'hash' => 'unique123456',
            'full_name' => 'Petr Dvořák',
            'source' => 'Test',
            'source_url' => 'https://example.com',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

test('death notice can be created with valid data', function () {
    $notice = DeathNotice::create([
        'hash' => 'abcdef123456',
        'full_name' => 'Marie Svobodová',
        'funeral_date' => '2024-02-20',
        'source' => 'Test Source',
        'source_url' => 'https://example.com/notice',
    ]);

    $this->assertDatabaseHas('death_notices', [
        'hash' => 'abcdef123456',
        'full_name' => 'Marie Svobodová',
    ]);

    expect($notice)->toBeInstanceOf(DeathNotice::class)
        ->and($notice->id)->not->toBeNull();
});
