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
./vendor/bin/pest                                         # Run all tests
./vendor/bin/pest tests/Feature/VisionOcrServiceTest.php # Single file
./vendor/bin/pest --filter="specific test name"          # Single test (RECOMMENDED)
```

**ALWAYS run relevant tests before finalizing changes.**

### Linting / Formatting

```bash
./vendor/bin/pint --dirty     # Format changed files (run before commit)
```

**ALWAYS run Pint before finalizing changes.**

### Re-extract Portraits

```bash
php artisan parte:process-existing --extract-portraits         # Only missing portraits
php artisan parte:process-existing --extract-portraits --force # Re-extract ALL
```

---

## 2. Project Structure & Domain

**Framework:** Laravel 12.x, PHP 8.4+, Pest testing, Laravel Horizon (Redis queue)

**Core Domain Files:**

- `app/Models/DeathNotice.php` - Death notice model with announcement_text field
- `app/Services/DeathNoticeService.php` - Orchestrates scraping, storage, PDF generation
- `app/Services/PdfGeneratorService.php` - PDF generation using Imagick (images) and DomPDF (HTML)
- `app/Services/VisionOcrService.php` - AI Vision extraction (ZhipuAI GLM-4V → Anthropic Claude fallback)
- `app/Services/Scrapers/*Scraper.php` - Individual scrapers (PSBKScraper, PSHajdukovaScraper, SadovyJanScraper)
- `app/Jobs/ExtractImageParteJob.php` - Extract name + funeral date + announcement_text from images (queue: extraction)
- `app/Jobs/ExtractDeathDateAndAnnouncementJob.php` - Extract death date + announcement_text (queue: extraction)
- `app/Console/Commands/DownloadDeathNotices.php` - `php artisan parte:download`
- `app/Console/Commands/ProcessExistingPartesCommand.php` - `php artisan parte:process-existing [--extract-portraits] [--force]`

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

- **Flow:** AI-first approach (configurable provider with fallback)
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

### PDF Generation (PdfGeneratorService)

**Service:** `app/Services/PdfGeneratorService.php` handles all PDF generation

**Image → PDF (Imagick):**

```php
// Uses Imagick for high-quality image-to-PDF conversion
$this->pdfGenerator->convertImageToPdf($imagePath, $outputPath);

// Implementation details:
// - 300 DPI quality (maximum quality for OCR)
// - JPEG compression quality 85
// - A4 format (2480x3508 pixels at 300 DPI)
// - Centers image on white canvas
// - Auto-creates output directories
// - Typical output: <600KB for standard images
```

**HTML → PDF (DomPDF):**

```php
// Uses DomPDF for HTML-based PDFs (death notice announcements)
$this->pdfGenerator->convertHtmlToPdf($html, $outputPath, [
    'top' => 20,
    'right' => 20,
    'bottom' => 20,
    'left' => 20,
]);

// Implementation details:
// - A4 paper format
// - Built-in compression enabled
// - Remote resources disabled (security)
// - DPI 96 (readable + smaller file size)
```

**Download & Convert:**

```php
// Downloads image from URL and converts to PDF
$this->pdfGenerator->downloadAndConvertToPdf($imageUrl, $outputPath);

// Features:
// - 3 retry attempts with exponential backoff
// - 30-second HTTP timeout
// - Guaranteed temp file cleanup
// - Comprehensive error logging
```

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

### AI Extraction (CRITICAL - Version 2.0)

**BREAKING CHANGE (v2.0):** Configuration syntax has changed!

#### New Configuration (v2.0+)

- **Text Provider:** `VISION_TEXT_PROVIDER=abacusai/gemini-3-flash` in `.env`
    - Format: `provider/model` or `provider` (model optional)
    - Providers: `abacusai`, `gemini`, `zhipuai`, `anthropic`
    - Models (Abacus.AI): `gemini-3-flash`, `claude-sonnet-4.5`, `gemini-2.5-pro`, `gpt-5.2`
- **Text Fallback:** `VISION_TEXT_FALLBACK=zhipuai` (optional)
- **Photo Provider:** `VISION_PHOTO_PROVIDER=abacusai/claude-sonnet-4.5` in `.env`
- **Photo Fallback:** `VISION_PHOTO_FALLBACK=anthropic` (optional)

#### Old Configuration (DEPRECATED - v1.x)

- ⚠️ **DEPRECATED:** `VISION_PROVIDER` and `VISION_FALLBACK_PROVIDER`
- **Production:** Throws exception if old syntax detected
- **Local/Testing:** Auto-converts with warning

#### Provider-Specific Configuration

- **Abacus.AI Unified API:**
    - API Key: `ABACUSAI_API_KEY` (.env)
    - Base URL: `https://routellm.abacus.ai` (default)
    - Models: Multiple providers through single API (Gemini, Claude, GPT)
    - Auto-normalizes pixel coordinates → percentages
- **Google Gemini API:**
    - API Key: `GEMINI_API_KEY` (.env)
    - Model: `gemini-3-flash-preview`
    - Endpoint: `https://generativelanguage.googleapis.com/v1beta`
- **ZhipuAI API:**
    - API Key: `ZHIPUAI_API_KEY` (.env)
    - Model: `glm-4.6v-flash`
    - Endpoint: `https://open.bigmodel.cn/api/paas/v4`
- **Anthropic API:**
    - API Key: `ANTHROPIC_API_KEY` (.env)
    - Model: `claude-3-5-sonnet-20241022`
    - Endpoint: `https://api.anthropic.com/v1/messages`

#### Extraction Features

- **Separate Providers:** Different providers for text extraction vs photo detection
- **Opening Quote Field (NEW v2.0):** Extracts poetic quotes separately from announcement
    - Validation: Warns if > 500 characters
    - Field: `opening_quote` (nullable TEXT)
    - Example: "Będę żyć dalej w sercach tych, którzy mnie kochali"
- **Prompt:** Extracts complete announcement WITH funeral details, fixes OCR errors
- **Photo Detection:** AI detects portrait photos, returns bounding box coordinates as percentages
- **Portrait Extraction:** Automated cropping via `PortraitExtractionService` using Imagick
- **Portrait Storage:** Saved separately to 'portrait' media collection (max 400x400px JPEG, quality 85)
- **Portrait Toggle:** Can be disabled via `EXTRACT_PORTRAITS=false` in `.env` (default: true)
- **Non-Critical:** Portrait extraction failures don't fail the entire job (logged as warnings)
- **Timeout:** Job timeout 300s (5 minutes), HTTP timeout 90s
- **Sequential Processing:** Jobs run one at a time on `extraction` queue (maxJobs=1)
- Always delete temp files after successful processing

### Queue Jobs (Horizon/Redis)

- All jobs implement `ShouldQueue`
- Retry logic: `$tries = 3`, `$backoff = 60`, `$timeout = 300`
- **Extraction Jobs:** Use dedicated `extraction` queue with `maxJobs=1` for sequential processing
- **CRITICAL:** `QUEUE_CONNECTION=redis` (NOT database) for Horizon compatibility

---

## 6. Error Handling

- Wrap risky operations in `try/catch` (HTTP, Imagick, DB transactions, AI API calls)
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

## 9. Filament Admin Panel

### Structure

- **Route:** `/admin`
- **Resource:** `app/Filament/Resources/DeathNoticeResource.php`
- **Pages:** `app/Filament/Resources/DeathNoticeResource/Pages/`
- **Widgets:** `app/Filament/Widgets/` (dashboard), `app/Filament/Resources/DeathNoticeResource/Widgets/` (resource)
- **Config:** `config/filament.php` (admin emails)
- **Provider:** `app/Providers/Filament/AdminPanelProvider.php`

### Access Control

- Uses `FilamentUser` interface on `User` model
- Admin emails configured via `config('filament.admin_emails')` from `FILAMENT_ADMIN_EMAILS` env var
- Seeder: `php artisan db:seed --class=AdminUserSeeder`

### Icons (Filament v5 Breaking Change)

- **DO:** `->icon(Heroicon::OutlinedDocumentText)`
- **DON'T:** `->icon('heroicon-o-document-text')`
- Import: `use Filament\Support\Icons\Heroicon;`
- Navigation icon type: `protected static string|\BackedEnum|null $navigationIcon`

### Widget Patterns

**Dashboard widget (unfiltered):**

```php
class DeathNoticeStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = DeathNotice::query()->selectRaw('...')->first();
        return [Stat::make('Label', $value)];
    }
}
```

**Resource list widget (filtered):**

```php
class DeathNoticeFilteredStats extends BaseWidget
{
    use InteractsWithPageTable;  // Access filtered query
    protected ?string $pollingInterval = null;  // NOT static

    protected function getTablePage(): string
    {
        return ListDeathNotices::class;
    }

    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery();
        // ... use $baseQuery for filtered stats
    }
}
```

**Resource page with widget:**

```php
class ListDeathNotices extends ListRecords
{
    use ExposesTableToWidgets;  // Required for widget access

    protected function getHeaderWidgets(): array
    {
        return [DeathNoticeFilteredStats::class];
    }
}
```

### XSS Prevention

Always escape URLs in Placeholder content:

```php
Placeholder::make('pdf_preview')
    ->content(fn ($record) => new HtmlString(
        '<a href="'.e($record->getFirstMediaUrl('default')).'"...'
    ));
```

---

## 10. Laravel-Specific Guidelines

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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.17
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function \_\_construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks

- Add useful array shape type definitions for arrays when appropriate.

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

## Livewire

- Use the `search-docs` tool to find exact version-specific documentation for how to write Livewire and Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` Artisan command to create new components.
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend; they're like regular HTTP requests. Always validate form data and run authorization checks in Livewire actions.

## Livewire Best Practices

- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle Hook Examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>

## Testing Livewire

<code-snippet name="Example Livewire Component Test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>

<code-snippet name="Testing Livewire Component Exists on Page" lang="php">
    $this->get('/posts/create')
    ->assertSeeLivewire(CreatePost::class);
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== pest/core rules ===

## Pest

### Testing

- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests

- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
  <code-snippet name="Basic Pest Test Example" lang="php">
  it('is true', function () {
  expect(true)->toBeTrue();
  });
  </code-snippet>

### Running Tests

- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions

- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
  <code-snippet name="Pest Example Asserting postJson Response" lang="php">
  it('returns all', function () {
  $response = $this->postJson('/api/docs', []);

                                                                    $response->assertSuccessful();

    });
    </code-snippet>

### Mocking

- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets

- Use datasets in Pest to simplify tests that have a lot of duplicated data. This is often the case when testing validation rules, so consider this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>

=== pest/v4 rules ===

## Pest 4

- Pest 4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing

- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest 4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);

});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing

- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode

- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities

- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated | Replacement |
|------------+--------------|
| bg-opacity-_ | bg-black/_ |
| text-opacity-_ | text-black/_ |
| border-opacity-_ | border-black/_ |
| divide-opacity-_ | divide-black/_ |
| ring-opacity-_ | ring-black/_ |
| placeholder-opacity-_ | placeholder-black/_ |
| flex-shrink-_ | shrink-_ |
| flex-grow-_ | grow-_ |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
