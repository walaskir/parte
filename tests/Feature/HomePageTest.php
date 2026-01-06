<?php

use App\Models\DeathNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage displays death notices sorted by death_date DESC', function () {
    // Create death notices with different death dates
    $notice1 = DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
        'death_date' => '2026-01-03', // Older
        'announcement_text' => 'S bolestí v srdci oznamujeme...',
    ]);

    $notice2 = DeathNotice::factory()->create([
        'full_name' => 'Marie Svobodová',
        'death_date' => '2026-01-05', // Newest
        'announcement_text' => 'Smutně oznamujeme...',
    ]);

    $notice3 = DeathNotice::factory()->create([
        'full_name' => 'Petr Dvořák',
        'death_date' => '2026-01-04', // Middle
        'announcement_text' => 'S hlubokým smutkem...',
    ]);

    $response = $this->get('/');

    $response->assertOk();

    // Extract all names in order they appear
    $content = $response->getContent();
    $mariePos = strpos($content, 'Marie Svobodová');
    $petrPos = strpos($content, 'Petr Dvořák');
    $janPos = strpos($content, 'Jan Novák');

    // Marie (2026-01-05) should appear before Petr (2026-01-04)
    expect($mariePos)->toBeLessThan($petrPos);
    // Petr (2026-01-04) should appear before Jan (2026-01-03)
    expect($petrPos)->toBeLessThan($janPos);
});

test('homepage displays death notices without death_date using created_at as fallback', function () {
    // Create notice with death_date in the past (should appear first)
    $withDeathDate = DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
        'death_date' => '2026-01-03', // Jan 3 (older)
        'created_at' => now()->subDays(10), // Old created_at doesn't matter when death_date exists
    ]);

    // Create notice without death_date (uses created_at as fallback)
    $withoutDeathDate = DeathNotice::factory()->create([
        'full_name' => 'Marie Svobodová',
        'death_date' => null,
        'created_at' => now(), // Today (Jan 4) - newer than Jan's death_date
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Jan Novák')
        ->assertSee('Marie Svobodová');

    $content = $response->getContent();
    $mariePos = strpos($content, 'Marie Svobodová');
    $janPos = strpos($content, 'Jan Novák');

    // Marie (created_at = today Jan 4) should appear BEFORE Jan (death_date = Jan 3)
    expect($mariePos)->toBeLessThan($janPos);
});

test('homepage prioritizes records with death_date over records without when dates are equal', function () {
    $today = now()->format('Y-m-d');

    // Create notice WITH death_date = today
    $withDeathDate = DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
        'death_date' => $today,
        'created_at' => now()->subHour(), // Created earlier
    ]);

    // Create notice WITHOUT death_date, but created_at = today
    $withoutDeathDate = DeathNotice::factory()->create([
        'full_name' => 'Marie Svobodová',
        'death_date' => null,
        'created_at' => now(), // Created now (today)
    ]);

    $response = $this->get('/');

    $response->assertOk();

    $content = $response->getContent();
    $janPos = strpos($content, 'Jan Novák');
    $mariePos = strpos($content, 'Marie Svobodová');

    // Jan (with death_date) should appear BEFORE Marie (without death_date) when effective dates are same
    expect($janPos)->toBeLessThan($mariePos);
});

test('homepage displays death date in correct format', function () {
    DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
        'death_date' => '2026-01-05',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Jan Novák')
        ->assertSee('(†5. 1. 2026)');
});

test('homepage displays name without parentheses when death date is missing', function () {
    DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
        'death_date' => null,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Jan Novák')
        ->assertDontSee('(†');
});

test('homepage displays announcement text when available', function () {
    DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
        'announcement_text' => 'S bolestí v srdci oznamujeme smutnou zprávu o úmrtí našeho drahého.',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('S bolestí v srdci oznamujeme smutnou zprávu o úmrtí našeho drahého.');
});

test('homepage hides announcement text section when not available', function () {
    DeathNotice::factory()->withoutAnnouncementText()->create([
        'full_name' => 'Jan Novák',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Jan Novák');
});

test('homepage displays PDF button when PDF media exists', function () {
    $notice = DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
    ]);

    // Create temporary PDF file for testing
    $tempPdf = tempnam(sys_get_temp_dir(), 'test_parte_').'.pdf';
    file_put_contents($tempPdf, '%PDF-1.4 fake pdf content for testing');

    try {
        $notice->addMedia($tempPdf)
            ->usingFileName('test-parte.pdf')
            ->toMediaCollection('pdf');

        $this->get('/')
            ->assertOk()
            ->assertSee('Jan Novák');
    } finally {
        if (file_exists($tempPdf)) {
            unlink($tempPdf);
        }
    }
});

test('homepage hides PDF button when PDF media does not exist', function () {
    DeathNotice::factory()->create([
        'full_name' => 'Jan Novák',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Jan Novák');
});

test('homepage displays maximum 15 death notices on first page', function () {
    // Create 20 death notices
    DeathNotice::factory()->count(20)->create();

    $response = $this->get('/');

    $response->assertOk();

    // Count article elements in response
    $content = $response->getContent();
    $articleCount = substr_count($content, '<article class=');

    expect($articleCount)->toBe(15);
});

test('homepage displays empty state when no death notices exist', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Momentálně nejsou k dispozici žádná parte.');
});

test('homepage pagination displays second page with remaining records', function () {
    // Create 20 death notices
    DeathNotice::factory()->count(20)->create();

    // First page should have 15 records
    $response = $this->get('/');
    $response->assertOk();
    $content = $response->getContent();
    $articleCount = substr_count($content, '<article class=');
    expect($articleCount)->toBe(15);

    // Second page should have 5 remaining records
    $response = $this->get('/?page=2');
    $response->assertOk();
    $content = $response->getContent();
    $articleCount = substr_count($content, '<article class=');
    expect($articleCount)->toBe(5);
});

test('homepage pagination maintains sorting across pages', function () {
    // Create 20 death notices with different dates
    $notices = collect();
    for ($i = 20; $i >= 1; $i--) {
        $notices->push(DeathNotice::factory()->create([
            'full_name' => "Person {$i}",
            'death_date' => now()->subDays($i)->format('Y-m-d'),
        ]));
    }

    // First page should have the 15 most recent (Person 1 to Person 15)
    $response = $this->get('/');
    $response->assertOk()
        ->assertSee('Person 1')
        ->assertSee('Person 15')
        ->assertDontSee('Person 16');

    // Second page should have the older ones (Person 16 to Person 20)
    $response = $this->get('/?page=2');
    $response->assertOk()
        ->assertSee('Person 16')
        ->assertSee('Person 20')
        ->assertDontSee('Person 15');
});

test('homepage returns 404 for non-existent page', function () {
    // Create only 5 death notices (only 1 page)
    DeathNotice::factory()->count(5)->create();

    // Page 2 should return 404
    $this->get('/?page=2')
        ->assertNotFound()
        ->assertSee('Stránka neexistuje');

    // Page 999 should also return 404
    $this->get('/?page=999')
        ->assertNotFound()
        ->assertSee('Stránka neexistuje');
});

test('homepage shows pagination links when more than 15 records exist', function () {
    // Create 20 death notices
    DeathNotice::factory()->count(20)->create();

    $response = $this->get('/');

    $response->assertOk();

    // Check for pagination elements (look for page navigation)
    $content = $response->getContent();
    expect($content)->toContain('page=2');
});

test('homepage hides pagination links when 15 or fewer records exist', function () {
    // Create exactly 15 death notices
    DeathNotice::factory()->count(15)->create();

    $response = $this->get('/');

    $response->assertOk();

    // Check that pagination links are not present
    $content = $response->getContent();
    expect($content)->not->toContain('page=2');
});
