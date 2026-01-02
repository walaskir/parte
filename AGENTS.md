# AGENTS.md

Instructions for AI coding agents (Claude, Cursor, Copilot) working in this Laravel 12 "parte" application.
Goal: Safe, consistent, and predictable changes to the death notice scraping and archival system.

---

## 1. Build / Test / Lint Commands

### Setup
```bash
composer install              # Install PHP dependencies
npm install                   # Install JS/CSS dependencies (if needed)
php artisan key:generate      # Generate app key (one-time)
php artisan migrate           # Run migrations
```

### Testing (Pest)
```bash
./vendor/bin/pest                                           # Run all tests
./vendor/bin/pest tests/Feature/GeminiServiceTest.php      # Single file
./vendor/bin/pest --filter="specific test name"            # Single test (RECOMMENDED after changes)
php artisan test                                            # Alternative (use Pest directly instead)
```
**ALWAYS run relevant tests before finalizing changes.**

### Linting / Formatting
```bash
./vendor/bin/pint --dirty     # Format changed files (run before commit)
./vendor/bin/pint             # Format all files
```
**ALWAYS run Pint before finalizing changes.**

### Assets (Vite)
```bash
npm run dev                   # Development server / watch
npm run build                 # Production build
```

---

## 2. Project Structure & Domain

**Framework:** Laravel 12.x, PHP 8.4+, Pest testing, Laravel Horizon (Redis queue)

**Core Domain Files:**
- `app/Models/DeathNotice.php` - Death notice model
- `app/Models/FuneralService.php` - Funeral service sources
- `app/Services/DeathNoticeService.php` - Orchestrates scraping, storage, PDF
- `app/Services/Scrapers/*Scraper.php` - Individual scrapers (PSBKScraper, PSHajdukovaScraper, SadovyJanScraper)
- `app/Services/GeminiService.php` - OCR + AI extraction (Tesseract → Gemini → Anthropic fallback)
- `app/Jobs/ExtractImageParteJob.php` - Extract name + funeral date from images
- `app/Jobs/ExtractDeathDateJob.php` - Extract death date
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
- Opening brace on same line
- Max ~120 chars per line
- Use short array syntax `[]`
- Type hints everywhere: parameters, return types, properties

### Naming
- Classes: `PascalCase` (`DeathNoticeService`)
- Methods/variables: `camelCase` (`generatePdf`, `$funeralDate`)
- Booleans: `is...`, `has...`, `should...`
- Use semantic names, not generic (`$foo`, `$bar`)

### Example
```php
public function downloadNotices(?array $sources = null): array
{
    // ...
}
```

---

## 4. Domain-Specific Rules

### Death Notice Hash
- **Field:** `full_name` (single field, not split into first/last)
- **Hash:** First 12 chars of `sha256(full_name + funeral_date + source_url)`
- **DB:** `string('hash', 12)->unique()->index()`
- Always check hash existence before insert to prevent duplicates

### Date Parsing (Carbon)
```php
Carbon::setLocale('cs');  // Czech locale for month names
try {
    return Carbon::createFromFormat('j.n.Y', $dateText)->format('Y-m-d');  // Handles "2.1.2026" and "21. 12. 2025"
} catch (\Exception $e) {
    Log::warning("Failed to parse date: {$dateText}", ['error' => $e->getMessage()]);
    return null;
}
```

**Regex Priority (in `GeminiService::parseParteText()`):**
1. Polish month names: "dnia 26 grudnia 2025 zmarła"
2. Numeric dates with keywords: "zemřel dne 25.12.2025"
3. Fallback: extract all dates, use heuristics

---

## 5. Media & External Services

### PDF Handling
- **Media Library:** Spatie Media Library with custom `HashPathGenerator`
- **Storage:** `storage/app/parte/{hash}/`
- **PDF Generation:** Spatie Browsershot (`Browsershot::html($html)->pdf()`)

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

### OCR & AI Extraction
- **Flow:** Tesseract OCR → Gemini AI → Anthropic Claude (fallback)
- **APIs:** `config('services.gemini.api_key')`, `config('services.anthropic.api_key')`
- **Limits:** Gemini free tier 1500 req/day
- Always delete temp files after successful processing

### Queue Jobs (Horizon/Redis)
- All jobs implement `ShouldQueue`
- Retry logic: `$tries = 3`, `$backoff = 60`, `$timeout = 180`
- **CRITICAL:** `QUEUE_CONNECTION=redis` (NOT database) for Horizon compatibility

---

## 6. Error Handling

- Wrap risky operations in `try/catch` (HTTP, Browsershot, DB transactions)
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
- Use `Http::fake()` for scrapers (no real HTTP requests)
- Use `RefreshDatabase` trait for clean DB state
- Test happy paths, failure paths, edge cases
- Run tests after every change: `./vendor/bin/pest --filter="test name"`

---

## 8. Git Workflow

- **Commits:** Only create when user explicitly requests
- **Hooks:** Never use `--no-verify` without permission
- **Force Push:** Never to main/master without explicit approval
- **Commit Messages:** NEVER mention AI assistance ("generated by Claude", etc.)

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

## 10. General Rules for Agents

- **Scope:** Change only what's needed for the task - no blanket refactors
- **Consistency:** Match existing code style and conventions
- **Communication:** Explain non-trivial changes in summary
- **Uncertainty:** Ask clarifying questions rather than guessing

---

**This AGENTS.md has priority over generic Laravel SaaS instructions in `ai/` directory.**
