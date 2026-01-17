# Laravel Debugging with Playwright Integration

## Overview

This file provides comprehensive debugging tools and Playwright integration for Laravel applications, enabling powerful end-to-end testing and debugging capabilities.

## Related Files

- **Tech Stack**: @tech_stack.md - Debugging tools, packages, and development environment setup
- **Testing**: @testing.md - Test debugging and browser automation integration
- **Laravel Rules**: @laravel_rules.md - Laravel-specific debugging patterns and logging practices
- **Process**: @process.md - Development workflow integration with debugging tools

## Playwright Setup for Laravel

### Installation and Configuration

```bash
# Install Playwright
npm install -D @playwright/test
npx playwright install

# Install Laravel Dusk for browser testing integration
composer require laravel/dusk --dev
php artisan dusk:install
```

### Playwright Configuration

Create `playwright.config.js`:

```javascript
import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./tests/Browser",
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: "html",
    use: {
        baseURL: process.env.APP_URL || "http://localhost:8000",
        trace: "on-first-retry",
        screenshot: "only-on-failure",
        video: "retain-on-failure",
    },
    projects: [
        {
            name: "chromium",
            use: { ...devices["Desktop Chrome"] },
        },
        {
            name: "firefox",
            use: { ...devices["Desktop Firefox"] },
        },
        {
            name: "webkit",
            use: { ...devices["Desktop Safari"] },
        },
        {
            name: "mobile-chrome",
            use: { ...devices["Pixel 5"] },
        },
    ],
    webServer: {
        command: "php artisan serve",
        url: "http://localhost:8000",
        reuseExistingServer: !process.env.CI,
    },
});
```

## Debug Helper Classes

### Laravel Debug Helper

Create `app/Helpers/DebugHelper.php`:

```php
<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DebugHelper
{
    /**
     * Enhanced debugging with Playwright integration
     */
    public static function debug($data, string $label = 'DEBUG', array $context = []): void
    {
        $debugInfo = [
            'timestamp' => now()->toISOString(),
            'label' => $label,
            'data' => $data,
            'context' => array_merge($context, [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
                'ip' => request()->ip(),
            ]),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];

        // Log to Laravel log
        Log::channel('debug')->info($label, $debugInfo);

        // Store in cache for Playwright access
        Cache::put("debug_data_" . time() . "_" . uniqid(), $debugInfo, 300);

        // Ray integration if available
        if (function_exists('ray')) {
            ray($debugInfo)->label($label);
        }
    }

    /**
     * Database query debugging
     */
    public static function enableQueryLogging(): void
    {
        DB::listen(function ($query) {
            $debugInfo = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
                'connection' => $query->connectionName,
            ];

            self::debug($debugInfo, 'SQL_QUERY');
        });
    }

    /**
     * Performance monitoring
     */
    public static function measurePerformance(callable $callback, string $label = 'PERFORMANCE'): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $callback();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        self::debug([
            'execution_time' => ($endTime - $startTime) * 1000, // milliseconds
            'memory_used' => $endMemory - $startMemory,
            'result_type' => gettype($result),
        ], $label);

        return $result;
    }

    /**
     * API endpoint debugging
     */
    public static function debugApiResponse($response, string $endpoint): void
    {
        self::debug([
            'endpoint' => $endpoint,
            'status_code' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'content' => $response->getContent(),
        ], 'API_RESPONSE');
    }
}
```

## Playwright Test Utilities

### Base Test Class

Create `tests/Browser/PlaywrightTestCase.php`:

```php
<?php

namespace Tests\Browser;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class PlaywrightTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable debug logging for tests
        \App\Helpers\DebugHelper::enableQueryLogging();
    }

    /**
     * Create test data with debugging
     */
    protected function createTestData(): array
    {
        return \App\Helpers\DebugHelper::measurePerformance(function () {
            return [
                'user' => \App\Models\User::factory()->create([
                    'email' => 'test@example.com',
                    'password' => bcrypt('password'),
                ]),
                'posts' => \App\Models\Post::factory()->count(3)->create(),
            ];
        }, 'TEST_DATA_CREATION');
    }
}
```

### JavaScript Debug Utilities

Create `tests/Browser/utils/debug.js`:

```javascript
// Playwright debugging utilities
export class DebugUtils {
    constructor(page) {
        this.page = page;
    }

    // Capture application state
    async captureAppState(label = "APP_STATE") {
        const state = await this.page.evaluate(() => {
            return {
                url: window.location.href,
                title: document.title,
                localStorage: { ...localStorage },
                sessionStorage: { ...sessionStorage },
                cookies: document.cookie,
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight,
                },
            };
        });

        console.log(`[${label}]`, state);
        return state;
    }

    // Monitor network requests
    async enableNetworkMonitoring() {
        this.page.on("request", (request) => {
            console.log(`[REQUEST] ${request.method()} ${request.url()}`);
        });

        this.page.on("response", (response) => {
            console.log(`[RESPONSE] ${response.status()} ${response.url()}`);
        });
    }

    // Capture console logs
    async enableConsoleLogging() {
        this.page.on("console", (msg) => {
            console.log(`[BROWSER_CONSOLE] ${msg.type()}: ${msg.text()}`);
        });
    }

    // Wait for Laravel to be ready
    async waitForLaravel() {
        await this.page.waitForFunction(() => {
            return window.Laravel !== undefined;
        });
    }

    // Debug form interactions
    async debugForm(selector) {
        const formData = await this.page.evaluate((sel) => {
            const form = document.querySelector(sel);
            if (!form) return null;

            const data = new FormData(form);
            const result = {};
            for (let [key, value] of data.entries()) {
                result[key] = value;
            }
            return {
                action: form.action,
                method: form.method,
                data: result,
            };
        }, selector);

        console.log("[FORM_DEBUG]", formData);
        return formData;
    }
}
```

## Environment Configuration

### Debug Environment Variables

Add to `.env.testing`:

```bash
# Debug Configuration
LOG_CHANNEL=stack
LOG_LEVEL=debug
RAY_ENABLED=true

# Playwright Configuration
PLAYWRIGHT_DEBUG=true
PLAYWRIGHT_HEADLESS=false
PLAYWRIGHT_SLOW_MO=100

# Database for testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Disable external services in testing
MAIL_MAILER=log
QUEUE_CONNECTION=sync
```

### Logging Configuration

Update `config/logging.php`:

```php
'channels' => [
    // ... existing channels

    'debug' => [
        'driver' => 'daily',
        'path' => storage_path('logs/debug.log'),
        'level' => 'debug',
        'days' => 7,
    ],

    'playwright' => [
        'driver' => 'daily',
        'path' => storage_path('logs/playwright.log'),
        'level' => 'debug',
        'days' => 3,
    ],
],
```

## Debugging Commands

### Artisan Debug Commands

Create `app/Console/Commands/DebugPlaywrightCommand.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DebugPlaywrightCommand extends Command
{
    protected $signature = 'debug:playwright {--clear : Clear debug cache}';
    protected $description = 'Manage Playwright debugging data';

    public function handle(): void
    {
        if ($this->option('clear')) {
            $this->clearDebugCache();
            return;
        }

        $this->displayDebugData();
    }

    private function clearDebugCache(): void
    {
        $keys = Cache::get('debug_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('debug_keys');

        $this->info('Debug cache cleared.');
    }

    private function displayDebugData(): void
    {
        $keys = Cache::get('debug_keys', []);

        if (empty($keys)) {
            $this->info('No debug data available.');
            return;
        }

        foreach ($keys as $key) {
            $data = Cache::get($key);
            if ($data) {
                $this->line("=== {$data['label']} ===");
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                $this->line('');
            }
        }
    }
}
```

## Usage Examples

### Basic Playwright Test with Debugging

```javascript
import { test, expect } from "@playwright/test";
import { DebugUtils } from "./utils/debug.js";

test("user login with debugging", async ({ page }) => {
    const debug = new DebugUtils(page);

    // Enable monitoring
    await debug.enableNetworkMonitoring();
    await debug.enableConsoleLogging();

    // Navigate and capture state
    await page.goto("/login");
    await debug.captureAppState("LOGIN_PAGE_LOADED");

    // Fill form with debugging
    await page.fill('[name="email"]', "test@example.com");
    await page.fill('[name="password"]', "password");
    await debug.debugForm("form");

    // Submit and verify
    await page.click('[type="submit"]');
    await debug.waitForLaravel();
    await debug.captureAppState("AFTER_LOGIN");

    await expect(page).toHaveURL("/dashboard");
});
```

### Laravel Controller with Debug Integration

```php
<?php

namespace App\Http\Controllers;

use App\Helpers\DebugHelper;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        DebugHelper::debug($request->all(), 'DASHBOARD_REQUEST');

        $data = DebugHelper::measurePerformance(function () {
            return [
                'user' => auth()->user(),
                'stats' => $this->getUserStats(),
            ];
        }, 'DASHBOARD_DATA_FETCH');

        return view('dashboard', $data);
    }
}
```

## Essential Commands

```bash
# Run Playwright tests with debugging
npx playwright test --debug
npx playwright test --headed --slow-mo=1000

# Generate Playwright test
npx playwright codegen http://localhost:8000

# View test results
npx playwright show-report

# Laravel debugging commands
php artisan debug:playwright
php artisan debug:playwright --clear

# Start development with debugging
npm run dev
php artisan serve
```

This debugging setup provides comprehensive tools for Laravel application debugging with Playwright integration, enabling powerful end-to-end testing and debugging capabilities.
