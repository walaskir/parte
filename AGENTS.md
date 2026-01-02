# AGENTS.md

Instructions for AI coding agents (Claude, Cursor, Copilot) working in this Laravel 12 "parte" application.
Goal: Safe, consistent, and predictable changes to the death notice scraping and archival system.

---

## 1. Build / Test / Lint Commands

### Setup
```bash
composer install && npm install
php artisan key:generate && php artisan migrate
```

### Testing (Pest)
```bash
./vendor/bin/pest                                      # Run all tests
./vendor/bin/pest tests/Feature/GeminiServiceTest.php # Single file
./vendor/bin/pest --filter="specific test name"       # Single test (RECOMMENDED)
```
**ALWAYS run relevant tests before finalizing changes.**

### Linting / Formatting
```bash
./vendor/bin/pint --dirty     # Format changed files (run before commit)
```
**ALWAYS run Pint before finalizing changes.**

---

## 2. Project Structure & Domain

**Framework:** Laravel 12.x, PHP 8.4+, Pest testing, Laravel Horizon (Redis queue)

**Core Domain Files:**
- `app/Models/DeathNotice.php` - Death notice model with announcement_text field
- `app/Services/DeathNoticeService.php` - Orchestrates scraping, storage, PDF generation
- `app/Services/GeminiService.php` - AI extraction (Gemini Vision → Anthropic Vision → regex fallback)
- `app/Services/Scrapers/*Scraper.php` - Individual scrapers (PSBKScraper, PSHajdukovaScraper, SadovyJanScraper)
- `app/Jobs/ExtractImageParteJob.php` - Extract name + funeral date + announcement_text from images
- `app/Jobs/ExtractDeathDateJob.php` - Extract death date + announcement_text
- `app/Console/Commands/DownloadDeathNotices.php` - `php artisan parte:download`
- `app/Console/Commands/ProcessExistingPartesCommand.php` - `php artisan parte:process-existing`

**Don't add new subsystems without explicit approval.**

---

## 3. PHP Code Style

### Imports
- Group `use` statements: `App\...` → `Illuminate\...` → vendors (`Spatie\...`, `Carbon\...`)
- Remove unused imports

### Formatting
- 4 spaces indentation (no tabs)
- Opening brace on same line, max ~120 chars per line
- Use short array syntax `[]`
- Type hints everywhere: parameters, return types, properties

### Naming
- Classes: `PascalCase` (`DeathNoticeService`)
- Methods/variables: `camelCase` (`generatePdf`, `$funeralDate`)
- Booleans: `is...`, `has...`, `should...`
- Use semantic names, not generic (`$foo`, `$bar`)

---

## 4. Domain-Specific Rules

### Death Notice Hash
- **Field:** `full_name` (single field, not split into first/last)
- **Hash:** First 12 chars of `sha256(full_name + funeral_date + source_url)`
- Always check hash existence before insert to prevent duplicates

### Announcement Text Extraction
- **Flow:** AI-first approach (Gemini Vision → Anthropic Vision → regex fallback)
- **Content:** Complete announcement INCLUDING funeral details (date, time, location)
- **Validation:** Warn if < 50 chars or literal "null" string
- **Storage:** Text field, whitespace collapsed to single spaces

### Date Parsing (Carbon)
```php
Carbon::setLocale('cs');  // Czech locale for month names
try {
    return Carbon::createFromFormat('j.n.Y', $dateText)->format('Y-m-d');
} catch (\Exception $e) {
    Log::warning("Failed to parse date: {$dateText}", ['error' => $e->getMessage()]);
    return null;
}
```

---

## 5. Media & External Services

### PDF → JPG Conversion (Imagick)
```php
$imagick = new Imagick();
$imagick->setResolution(300, 300);        // DPI 300 for OCR quality
$imagick->readImage($pdfPath.'[0]');      // Read first page only
$imagick->setImageFormat('jpeg');
$imagick->setImageCompressionQuality(90);
$imagick->writeImage($jpgPath);
$imagick->clear();                         // Always cleanup
$imagick->destroy();
```

### AI Extraction (CRITICAL)
- **Flow:** Gemini Vision API → Anthropic Vision API → Tesseract OCR regex (emergency fallback)
- **APIs:** `config('services.gemini.api_key')`, `config('services.anthropic.api_key')`
- **Limits:** Gemini free tier 1500 req/day - monitor quota, Anthropic is primary fallback
- **Prompt:** Extracts complete announcement WITH funeral details, fixes OCR errors
- Always delete temp files after successful processing

### Queue Jobs (Horizon/Redis)
- All jobs implement `ShouldQueue`
- Retry logic: `$tries = 3`, `$backoff = 60`, `$timeout = 180`
- **CRITICAL:** `QUEUE_CONNECTION=redis` (NOT database) for Horizon compatibility

---

## 6. Error Handling

- Wrap risky operations in `try/catch` (HTTP, Browsershot, DB transactions, AI API calls)
- Log errors: `Log::error()` or `Log::warning()` with context
- Never use empty `catch` blocks
- Return meaningful exit codes from artisan commands

---

## 7. Testing (Pest)

### Writing Tests
```php
test('death notice can be created with valid data', function () {
    $notice = DeathNotice::factory()->create();
    expect($notice)->toBeInstanceOf(DeathNotice::class);
});
```

### Best Practices
- Use `Http::fake()` for scrapers and AI API calls (no real HTTP requests)
- Use `RefreshDatabase` trait for clean DB state
- Test happy paths, failure paths, edge cases
- Run tests after every change: `./vendor/bin/pest --filter="test name"`

---

## 8. Git Workflow

- **Commits:** Only create when user explicitly requests
- **Hooks:** Never use `--no-verify` without permission
- **Force Push:** Never to main/master without explicit approval
- **Commit Messages:** NEVER mention AI assistance ("generated by Claude", "AI-assisted", etc.)

---

## 9. Laravel-Specific Guidelines

### Laravel 12 Structure
- No `app/Http/Middleware/` - register in `bootstrap/app.php`
- Commands auto-register from `app/Console/Commands/`
- Use `php artisan make:` for new files

### Best Practices
- Use Eloquent over raw queries (`Model::query()`, not `DB::`)
- Eager load to prevent N+1 queries
- Use `config('app.name')`, NOT `env('APP_NAME')` (except in config files)
- Form Requests for validation, not inline in controllers
- Named routes: `route('name')` over hardcoded URLs

---

**This AGENTS.md has priority over generic Laravel SaaS instructions in `ai/` directory.**
