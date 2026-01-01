### Laravel Sanctum

- **Package**: `laravel/sanctum:^4.0`
- **Installation**: `composer require laravel/sanctum`
- **Setup**:
  ```bash
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
  php artisan migrate
  ```
- **Configuration** (config/sanctum.php):
  ```php
  'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
      '%s%s',
      'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
      Sanctum::currentApplicationUrlWithPort()
  ))),
  ```

### Laravel Fortify

- **Package**: `laravel/fortify:^1.21`
- **Installation**: `composer require laravel/fortify`
- **Setup**:
  ```bash
  php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
  php artisan migrate
  ```

### Laravel Socialite

- **Package**: `laravel/socialite:^5.15`
- **Extended Providers**: `socialiteproviders/manager:^4.5`
- **Installation**:
  ```bash
  composer require laravel/socialite
  composer require socialiteproviders/manager
  ```
- **Configuration** (config/services.php):
  ```php
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_REDIRECT_URI'),
  ],
  'github' => [
      'client_id' => env('GITHUB_CLIENT_ID'),
      'client_secret' => env('GITHUB_CLIENT_SECRET'),
      'redirect' => env('GITHUB_REDIRECT_URI'),
  ],
  ```
- **Environment Variables**:

  ```bash
  GOOGLE_CLIENT_ID=your_google_client_id
  GOOGLE_CLIENT_SECRET=your_google_client_secret
  GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

  GITHUB_CLIENT_ID=your_github_client_id
  GITHUB_CLIENT_SECRET=your_github_client_secret
  GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback
  ```

- **Controller Example**:

  ```php
  <?php

  namespace App\Http\Controllers\Auth;

  use App\Http\Controllers\Controller;
  use Laravel\Socialite\Facades\Socialite;

  class SocialiteController extends Controller
  {
      public function redirect(string $provider)
      {
          return Socialite::driver($provider)->redirect();
      }

      public function callback(string $provider)
      {
          $socialiteUser = Socialite::driver($provider)->user();
          // Handle user creation/login logic
      }
  }
  ```

  ## Related Files

- **Laravel Rules**: @laravel_rules.md - Implementation patterns for Laravel packages
- **Debugging**: @debugging.md - Debugging tools and development utilities
- **Testing**: @testing.md - Testing tools and frameworks (Pest PHP, Dusk, etc.)
- **Process**: @process.md - Development tools and asset compilation workflow
- **Doc Latest**: @doc_lastest.md - Documentation sources for libraries and packages
- **Project Settings**: @project_settings.md - Environment and configuration requirements

## AI Coding Instructions

When generating code for this stack, always:

1. **Use exact package names and versions** specified below
2. **Follow Laravel conventions** for naming, structure, and patterns
3. **Include proper error handling** and validation
4. **Generate complete, production-ready code** with security considerations
5. **Use type hints and PHPDoc** for better code documentation
6. **Include database migrations** when creating models
7. **Add appropriate tests** using Pest PHP syntax
8. **Configure environment variables** with sensible defaults# Laravel MicroSaaS Tech Stack - AI Development Instructions

This document provides precise technical specifications for AI-assisted Laravel microSaaS development. Use these exact package names, versions, and configurations for consistent code generation.

## Core Framework & Development Environment

### Laravel 12.x

- **Package**: `laravel/laravel:^12.0`
- **PHP Version**: `>=8.4`
- **Installation Command**: `composer create-project laravel/laravel project-name`
- **Development Environment**: Laravel Valet for Linux
  - **Installation**: See [Valet Linux documentation](https://cpriego.github.io/valet-linux/)
  - **Local Access**: Applications accessible via `https://project-name.test` or `http://project-name.test`
  - **URL Pattern**: Directory name + `.test` suffix (e.g., `project11` → `https://project11.test`)
  - **HTTPS Support**: Automatic SSL certificate generation for local development
- **Key Configuration**:
  ```php
  // config/app.php
  'timezone' => env('APP_TIMEZONE', 'UTC'),
  'locale' => env('APP_LOCALE', 'en'),
  ```
- **Required Extensions**: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML
- **Recommended Extensions**: Redis, Imagick, Intl

### PHP 8.4+

- **Minimum Version**: `8.4.0`
- **Recommended Version**: `8.4.1` or latest patch
- **php.ini Configuration**:

  ```ini
  [PHP]
  memory_limit = 512M
  max_execution_time = 300

  [opcache]
  opcache.enable = 1
  opcache.memory_consumption = 256
  opcache.max_accelerated_files = 20000
  ```

## Admin Panel & UI Framework

### Filament 3.x

- **Package**: `filament/filament:^3.2`
- **Installation**: `composer require filament/filament`
- **Setup Command**: `php artisan filament:install --panels`
- **Required Configuration**:
  ```php
  // config/filament.php
  'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),
  'assets_path' => null,
  'cache_path' => base_path('bootstrap/cache/filament'),
  ```
- **Key Packages**:
  - `filament/spatie-laravel-media-library-plugin` - Media management
  - `filament/spatie-laravel-settings-plugin` - Settings management
  - `filament/spatie-laravel-translatable-plugin` - Multi-language support
- **Multi-tenancy Setup**:
  ```bash
  php artisan vendor:publish --tag=filament-config
  php artisan make:filament-tenant Tenant
  ```

## Error Tracking & Monitoring

### Flare (FlareApp.io)

- **Package**: `spatie/laravel-ignition:^2.4`
- **Flare Package**: `spatie/flare-client-php:^1.7`
- **Configuration**:
  ```php
  // config/flare.php
  'key' => env('FLARE_KEY'),
  'reporting' => [
      'anonymize_ips' => true,
      'collect_git_information' => true,
      'maximum_number_of_collected_exceptions' => 200,
  ],
  ```
- **Environment Setup**:
  ```bash
  FLARE_KEY=your_flare_key_here
  ```

### Sentry

- **Package**: `sentry/sentry-laravel:^4.2`
- **Installation**: `composer require sentry/sentry-laravel`
- **Configuration**:
  ```php
  // config/sentry.php
  'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
  'release' => env('SENTRY_RELEASE'),
  'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
  'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),
  'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.2),
  ```
- **Environment Setup**:
  ```bash
  SENTRY_LARAVEL_DSN=your_sentry_dsn_here
  SENTRY_TRACES_SAMPLE_RATE=0.2
  ```

## Date & Time Manipulation

### Carbon

- **Package**: `nesbot/carbon:^3.8` (included with Laravel by default)
- **Purpose**: Enhanced PHP DateTime with fluent API
- **Key Features**:
  - Human-readable date differences
  - Timezone management
  - Localization support
  - Date arithmetic and formatting
- **Usage Examples**:

  ```php
  use Carbon\Carbon;

  // Current time
  $now = Carbon::now();
  $utc = Carbon::now('UTC');

  // Parsing dates
  $date = Carbon::parse('2024-01-15 14:30:00');
  $fromTimestamp = Carbon::createFromTimestamp(1705234200);

  // Date arithmetic
  $nextWeek = Carbon::now()->addWeek();
  $lastMonth = Carbon::now()->subMonth();
  $endOfDay = Carbon::now()->endOfDay();

  // Formatting
  $formatted = Carbon::now()->format('Y-m-d H:i:s');
  $human = Carbon::now()->diffForHumans(); // "5 minutes ago"
  $iso = Carbon::now()->toISOString();

  // Timezone conversion
  $userTimezone = Carbon::now()->setTimezone('America/New_York');
  $utcTime = Carbon::now('America/New_York')->utc();

  // Localization
  Carbon::setLocale('es');
  $spanish = Carbon::now()->isoFormat('LLLL');
  ```

- **Model Integration**:

  ```php
  // In Eloquent models
  protected $casts = [
      'created_at' => 'datetime',
      'updated_at' => 'datetime',
      'published_at' => 'datetime',
      'expires_at' => 'datetime',
  ];

  // Accessor example
  protected function publishedAtFormatted(): Attribute
  {
      return Attribute::make(
          get: fn ($value) => $this->published_at?->format('M j, Y'),
      );
  }

  // Query scopes with Carbon
  public function scopePublishedToday($query)
  {
      return $query->whereDate('published_at', Carbon::today());
  }

  public function scopeExpiringSoon($query)
  {
      return $query->whereBetween('expires_at', [
          Carbon::now(),
          Carbon::now()->addDays(7)
      ]);
  }
  ```

- **Configuration** (config/app.php):
  ```php
  'timezone' => env('APP_TIMEZONE', 'UTC'),
  'locale' => env('APP_LOCALE', 'en'),
  'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
  ```
- **Environment Variables**:
  ```bash
  APP_TIMEZONE=UTC
  APP_LOCALE=en
  ```
- **Best Practices**:
  - Always store dates in UTC in database
  - Convert to user timezone for display
  - Use Carbon for all date manipulations
  - Leverage Carbon's immutable methods for safety
  - Use proper date casting in Eloquent models

### CarbonImmutable

- **Purpose**: Immutable version of Carbon for safer date handling
- **Usage**:

  ```php
  use Carbon\CarbonImmutable;

  $date = CarbonImmutable::now();
  $newDate = $date->addDay(); // Original $date unchanged

  // Preferred for value objects and DTOs
  class Period
  {
      public function __construct(
          public readonly CarbonImmutable $start,
          public readonly CarbonImmutable $end,
      ) {}
  }
  ```

## Development & Debugging Tools

### Spatie Ray

- **Package**: `spatie/laravel-ray:^1.37`
- **Installation**: `composer require spatie/laravel-ray --dev`
- **Desktop App**: Download from https://myray.app/
- **Configuration**:
  ```php
  // config/ray.php
  'enable' => env('RAY_ENABLED', true),
  'host' => env('RAY_HOST', 'localhost'),
  'port' => env('RAY_PORT', 23517),
  'remote_path' => env('RAY_REMOTE_PATH'),
  'local_path' => env('RAY_LOCAL_PATH'),
  ```
- **Usage Examples**:
  ```php
  ray($variable);
  ray()->table($array);
  ray()->sql();
  ray()->showQueries();
  ray()->count('loop iterations');
  ```

## Package Management & Development Tools

### Composer

- **Purpose**: PHP dependency management
- **Configuration**: Optimized for production with classmap optimization
- **Key Commands**:
  - `composer install --no-dev --optimize-autoloader` (production)
  - `composer dump-autoload -o` (optimization)

### Laravel Pint

- **Purpose**: Code style fixer built on PHP-CS-Fixer
- **Configuration**: Laravel preset with custom rules
- **Usage**: Automated code formatting in CI/CD pipeline

### Laravel Sail

- **Purpose**: Docker development environment
- **Services**: MySQL/PostgreSQL, Redis, Mailpit, MinIO
- **Benefits**: Consistent development environment across team members

## Database & Caching

### PostgreSQL 15+ (Recommended)

- **Version**: `15.8` or higher
- **Driver**: `pdo_pgsql`
- **Configuration**:
  ```bash
  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=5432
  DB_DATABASE=laravel_saas
  DB_USERNAME=laravel
  DB_PASSWORD=password
  ```
- **Optimization**:
  ```sql
  -- postgresql.conf
  shared_buffers = 256MB
  effective_cache_size = 1GB
  work_mem = 4MB
  maintenance_work_mem = 64MB
  ```

### MySQL 8.0+ (Alternative)

- **Version**: `8.0.35` or higher
- **Configuration**:
  ```bash
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=laravel_saas
  DB_USERNAME=laravel
  DB_PASSWORD=password
  ```

### Redis 7.x

- **Version**: `7.2` or higher
- **Package**: `predis/predis:^2.2`
- **Configuration**:

  ```bash
  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=null
  REDIS_PORT=6379
  REDIS_DB=0

  CACHE_DRIVER=redis
  QUEUE_CONNECTION=redis
  SESSION_DRIVER=redis
  ```

- **Memory Configuration**:
  ```redis
  # redis.conf
  maxmemory 512mb
  maxmemory-policy allkeys-lru
  ```

## Asset Management & Frontend

### Vite

- **Package**: `vite:^5.4`
- **Laravel Plugin**: `laravel-vite-plugin:^1.0`
- **Configuration** (vite.config.js):

  ```javascript
  import { defineConfig } from "vite";
  import laravel from "laravel-vite-plugin";
  import react from "@vitejs/plugin-react";

  export default defineConfig({
    plugins: [
      laravel({
        input: ["resources/css/app.css", "resources/js/app.js"],
        refresh: true,
      }),
      react(), // Include if using React
    ],
    server: {
      hmr: {
        host: "localhost",
      },
    },
  });
  ```

- **Commands**:
  ```bash
  npm run dev        # Development with HMR
  npm run build      # Production build
  npm run preview    # Preview production build
  ```

### Frontend Framework Options

#### Option 1: Traditional Laravel Frontend (Blade + Alpine.js)

- **Blade Templates**: Server-side rendered views
- **Alpine.js**: `alpinejs:^3.14`
- **Livewire**: `livewire/livewire:^3.5`
- **Installation**:
  ```bash
  npm install alpinejs
  composer require livewire/livewire
  ```
- **Basic Setup**:
  ```javascript
  // resources/js/app.js
  import Alpine from "alpinejs";
  window.Alpine = Alpine;
  Alpine.start();
  ```

#### Option 2: React.js Integration

- **React**: `react:^18.3`, `react-dom:^18.3`
- **Vite Plugin**: `@vitejs/plugin-react:^4.3`
- **TypeScript** (optional): `typescript:^5.5`, `@types/react:^18.3`
- **Installation**:
  ```bash
  npm install react react-dom @vitejs/plugin-react
  npm install -D @types/react @types/react-dom typescript # if using TypeScript
  ```
- **Basic Component Structure**:

  ```jsx
  // resources/js/Components/Welcome.jsx
  import React from "react";

  export default function Welcome({ name }) {
    return <h1>Hello, {name}!</h1>;
  }
  ```

#### Option 3: Inertia.js (Recommended for React + Laravel)

- **Server Package**: `inertiajs/inertia-laravel:^1.3`
- **Client Package**: `@inertiajs/react:^1.2`
- **Installation**:
  ```bash
  composer require inertiajs/inertia-laravel
  npm install @inertiajs/react
  php artisan inertia:middleware
  ```
- **Configuration**:
  ```php
  // app/Http/Kernel.php
  'web' => [
      \App\Http\Middleware\HandleInertiaRequests::class,
  ],
  ```
- **Page Component Example**:

  ```jsx
  // resources/js/Pages/Dashboard.jsx
  import { Head } from "@inertiajs/react";

  export default function Dashboard({ user }) {
    return (
      <>
        <Head title="Dashboard" />
        <h1>Welcome, {user.name}!</h1>
      </>
    );
  }
  ```

#### Option 4: Filament PHP (Admin Panel + Frontend)

- **Package**: `filament/filament:^3.2`
- **Purpose**: Full-stack admin panel with optional frontend capabilities
- **Installation**:
  ```bash
  composer require filament/filament
  php artisan filament:install --panels
  ```
- **Multi-Panel Setup** (Admin + Frontend):
  ```bash
  php artisan make:filament-panel admin
  php artisan make:filament-panel app
  ```
- **Configuration** (config/filament.php):
  ```php
  'panels' => [
      'admin' => [
          'id' => 'admin',
          'path' => '/admin',
          'login' => \Filament\Http\Livewire\Auth\Login::class,
          'domain' => null,
      ],
      'app' => [
          'id' => 'app',
          'path' => '/',
          'login' => \App\Filament\App\Pages\Auth\Login::class,
          'domain' => null,
      ],
  ],
  ```
- **Frontend Page Example**:

  ```php
  <?php
  // app/Filament/App/Pages/Dashboard.php
  namespace App\Filament\App\Pages;

  use Filament\Pages\Page;

  class Dashboard extends Page
  {
      protected static ?string $navigationIcon = 'heroicon-o-home';
      protected static string $view = 'filament.app.pages.dashboard';
      protected static ?string $title = 'Dashboard';

      public function mount(): void
      {
          // Page logic
      }
  }
  ```

- **Custom Frontend Components**:

  ```php
  // app/Filament/App/Widgets/StatsOverview.php
  use Filament\Widgets\StatsOverviewWidget as BaseWidget;
  use Filament\Widgets\StatsOverviewWidget\Stat;

  class StatsOverview extends BaseWidget
  {
      protected function getStats(): array
      {
          return [
              Stat::make('Total Users', '1,234')
                  ->description('32k increase')
                  ->descriptionIcon('heroicon-m-arrow-trending-up')
                  ->color('success'),
              Stat::make('Revenue', '$12,345')
                  ->description('7% increase')
                  ->descriptionIcon('heroicon-m-arrow-trending-up')
                  ->color('success'),
          ];
      }
  }
  ```

- **Benefits for SaaS**:
  - **Rapid Development**: Pre-built components and layouts
  - **Consistent UI**: Professional design system out of the box
  - **Admin + User Panels**: Separate interfaces for different user types
  - **Built-in Authentication**: User management and permissions
  - **Responsive Design**: Mobile-first approach
  - **Extensible**: Custom components and themes
  - **Laravel Integration**: Seamless Eloquent and Livewire integration
- **Use Cases**:
  - **Admin Dashboard**: Content management, user administration
  - **User Portal**: Customer dashboard, account management
  - **SaaS Frontend**: Complete application interface
  - **Multi-tenant Apps**: Separate panels per tenant
- **Key Features**:
  - **Form Builder**: Dynamic forms with validation
  - **Table Builder**: Data tables with filtering and sorting
  - **Navigation**: Multi-level menus and breadcrumbs
  - **Notifications**: Toast messages and alerts
  - **Widgets**: Dashboard components and charts
  - **Actions**: Buttons, modals, and bulk operations
  - **Themes**: Dark mode and custom styling

### Tailwind CSS

- **Package**: `tailwindcss:^3.4`
- **Installation**:
  ```bash
  npm install -D tailwindcss postcss autoprefixer
  npx tailwindcss init -p
  ```
- **Configuration** (tailwind.config.js):
  ```javascript
  /** @type {import('tailwindcss').Config} */
  export default {
    content: [
      "./resources/**/*.blade.php",
      "./resources/**/*.js",
      "./resources/**/*.jsx",
      "./app/Filament/**/*.php",
      "./vendor/filament/**/*.blade.php",
    ],
    theme: {
      extend: {},
    },
    plugins: [
      require("@tailwindcss/forms"),
      require("@tailwindcss/typography"),
    ],
  };
  ```
- **CSS Import** (resources/css/app.css):
  ```css
  @tailwind base;
  @tailwind components;
  @tailwind utilities;
  ```

### Icon Sets

#### Heroicons (Primary - Tailwind UI Compatible)

- **Package**: `@heroicons/react:^2.1` (for React)
- **Blade Directive**: `blade-heroicons/blade-heroicons:^2.4` (for Blade)
- **Installation**:

  ```bash
  # For React projects
  npm install @heroicons/react

  # For Blade templates
  composer require blade-heroicons/blade-heroicons
  ```

- **Usage in React**:

  ```jsx
  import { UserIcon, CogIcon } from "@heroicons/react/24/outline";
  import { UserIcon as UserIconSolid } from "@heroicons/react/24/solid";

  function Dashboard() {
    return (
      <div>
        <UserIcon className="h-6 w-6 text-gray-500" />
        <UserIconSolid className="h-5 w-5 text-blue-500" />
      </div>
    );
  }
  ```

- **Usage in Blade**:

  ```blade
  {{-- Outline icons --}}
  <x-heroicon-o-user class="w-6 h-6 text-gray-500" />
  <x-heroicon-o-cog class="w-5 h-5" />

  {{-- Solid icons --}}
  <x-heroicon-s-user class="w-4 h-4 text-blue-500" />
  <x-heroicon-s-star class="w-5 h-5 text-yellow-400" />

  {{-- Mini icons (20x20) --}}
  <x-heroicon-m-plus class="w-5 h-5" />
  ```

- **Filament Integration**:

  ```php
  // Filament resource
  public static function getNavigationIcon(): string
  {
      return 'heroicon-o-users';
  }

  // Filament action
  Tables\Actions\Action::make('edit')
      ->icon('heroicon-m-pencil-square')
  ```

#### Lucide (Secondary - Modern Alternative)

- **Package**: `lucide-react:^0.446` (for React)
- **Blade Package**: `mallardduck/blade-lucide-icons:^1.4` (for Blade)
- **Installation**:

  ```bash
  # For React projects
  npm install lucide-react

  # For Blade templates
  composer require mallardduck/blade-lucide-icons
  ```

- **Usage in React**:

  ```jsx
  import { User, Settings, ChevronRight, Database } from "lucide-react";

  function Sidebar() {
    return (
      <nav>
        <User size={20} className="text-gray-600" />
        <Settings size={16} strokeWidth={1.5} />
        <ChevronRight size={24} className="text-blue-500" />
        <Database size={18} color="#10b981" />
      </nav>
    );
  }
  ```

- **Usage in Blade**:

  ```blade
  {{-- Basic usage --}}
  <x-lucide-user class="w-6 h-6 text-gray-500" />
  <x-lucide-settings class="w-5 h-5" />

  {{-- With attributes --}}
  <x-lucide-chevron-right class="w-4 h-4 text-blue-500" stroke-width="2" />
  <x-lucide-database class="w-6 h-6" stroke="currentColor" />
  ```

- **Filament Integration**:
  ```php
  // Using Lucide icons in Filament
  Tables\Actions\Action::make('view')
      ->icon('lucide-eye')
      ->url(fn ($record) => route('view', $record))
  ```

### Icon Usage Guidelines for AI Development

#### **Icon Selection Priority**:

1. **Heroicons** - Primary choice for standard UI elements (navigation, forms, actions)
2. **Lucide** - Secondary choice for specialized icons or when Heroicons doesn't have the needed icon
3. **Custom SVGs** - Only for brand-specific or unique requirements

#### **Consistent Sizing**:

```css
/* Standard icon sizes */
.icon-xs {
  @apply w-3 h-3;
} /* 12px */
.icon-sm {
  @apply w-4 h-4;
} /* 16px */
.icon-md {
  @apply w-5 h-5;
} /* 20px */
.icon-lg {
  @apply w-6 h-6;
} /* 24px */
.icon-xl {
  @apply w-8 h-8;
} /* 32px */
```

#### **React Component Pattern**:

```jsx
// Icon wrapper component for consistency
import { forwardRef } from "react";
import { cn } from "@/lib/utils";

const Icon = forwardRef(
  ({ className, size = 20, strokeWidth = 1.5, ...props }, ref) => {
    return (
      <svg
        ref={ref}
        className={cn("inline-block", className)}
        width={size}
        height={size}
        strokeWidth={strokeWidth}
        {...props}
      />
    );
  },
);
```

#### **Blade Component Pattern**:

```blade
{{-- resources/views/components/icon.blade.php --}}
@props([
    'name' => 'user',
    'type' => 'heroicon-o',
    'size' => 'md'
])

@php
$sizeClasses = [
    'xs' => 'w-3 h-3',
    'sm' => 'w-4 h-4',
    'md' => 'w-5 h-5',
    'lg' => 'w-6 h-6',
    'xl' => 'w-8 h-8'
];
@endphp

<x-dynamic-component
    :component="$type . '-' . $name"
    {{ $attributes->merge(['class' => $sizeClasses[$size]]) }}
/>

{{-- Usage: <x-icon name="user" type="heroicon-o" size="lg" class="text-blue-500" /> --}}
```

#### **Filament Icon Configuration**:

```php
// config/filament.php - Global icon overrides
'icons' => [
    'panels::sidebar.collapse-button' => 'heroicon-m-chevron-left',
    'panels::sidebar.expand-button' => 'heroicon-m-chevron-right',
    'panels::topbar.close-sidebar-button' => 'heroicon-m-x-mark',
    'panels::user-menu.profile-item' => 'heroicon-m-user',
    'panels::user-menu.logout-item' => 'heroicon-m-arrow-right-on-rectangle',
],
```

## Queue & Job Processing

### Laravel Horizon

- **Package**: `laravel/horizon:^5.25`
- **Installation**:
  ```bash
  composer require laravel/horizon
  php artisan horizon:install
  php artisan migrate
  ```
- **Configuration** (config/horizon.php):
  ```php
  'environments' => [
      'production' => [
          'supervisor-1' => [
              'connection' => 'redis',
              'queue' => ['default'],
              'balance' => 'auto',
              'autoScalingStrategy' => 'time',
              'maxProcesses' => 10,
              'maxTime' => 0,
              'maxJobs' => 0,
              'memory' => 128,
              'tries' => 1,
              'timeout' => 60,
          ],
      ],
  ],
  ```
- **Commands**:
  ```bash
  php artisan horizon        # Start Horizon
  php artisan horizon:pause  # Pause workers
  php artisan horizon:status # Check status
  ```

### Laravel Octane

- **Package**: `laravel/octane:^2.5`
- **Installation**:
  ```bash
  composer require laravel/octane
  php artisan octane:install --server=frankenphp
  ```
- **Configuration** (config/octane.php):
  ```php
  'server' => env('OCTANE_SERVER', 'frankenphp'),
  'listeners' => [
      WorkerStarting::class => [
          EnsureUploadedFilesAreValid::class,
          EnsureUploadedFilesCanBeMoved::class,
      ],
  ],
  ```
- **Commands**:
  ```bash
  php artisan octane:start --workers=4 --task-workers=6
  php artisan octane:reload
  php artisan octane:stop
  ```

### Pest PHP

- **Package**: `pestphp/pest:^2.35`
- **Laravel Plugin**: `pestphp/pest-plugin-laravel:^2.4`
- **Installation**:
  ```bash
  composer require pestphp/pest --dev
  composer require pestphp/pest-plugin-laravel --dev
  php artisan pest:install
  ```
- **Configuration** (tests/Pest.php):

  ```php
  <?php

  use Illuminate\Foundation\Testing\RefreshDatabase;

  uses(
      Tests\TestCase::class,
      RefreshDatabase::class,
  )->in('Feature');

  uses(Tests\TestCase::class)->in('Unit');
  ```

- **Test Example**:

  ```php
  <?php

  test('user can register', function () {
      $response = $this->post('/register', [
          'name' => 'John Doe',
          'email' => 'john@example.com',
          'password' => 'password',
          'password_confirmation' => 'password',
      ]);

      $response->assertRedirect('/dashboard');
      $this->assertAuthenticated();
  });
  ```

- **Commands**:
  ```bash
  ./vendor/bin/pest               # Run all tests
  ./vendor/bin/pest --coverage   # Run with coverage
  ./vendor/bin/pest --parallel    # Run tests in parallel
  ```

### Laravel Dusk

- **Package**: `laravel/dusk:^8.2`
- **Installation**: `composer require laravel/dusk --dev`
- **Setup**: `php artisan dusk:install`
- **Configuration**: Integrates with Pest via `pestphp/pest-plugin-laravel-dusk`

## Deployment & Infrastructure

### DigitalOcean

- **Droplet Configuration**:
  - **Development**: Basic Droplet ($6/month, 1GB RAM, 1 vCPU)
  - **Production**: Premium Intel ($24/month, 4GB RAM, 2 vCPU)
  - **Scaling**: Regular Intel ($48/month, 8GB RAM, 4 vCPU)
- **Required Services**:

  ```bash
  # Managed Database
  doctl databases create laravel-saas --engine postgresql --size db-s-1vcpu-1gb

  # Spaces (S3 Compatible)
  AWS_ACCESS_KEY_ID=your_spaces_key
  AWS_SECRET_ACCESS_KEY=your_spaces_secret
  AWS_DEFAULT_REGION=nyc3
  AWS_BUCKET=your-app-storage
  AWS_URL=https://nyc3.digitaloceanspaces.com
  ```

### Laravel Cloud

- **Deployment Configuration**:
  ```yaml
  # .laravel-cloud.yml
  name: laravel-saas
  php_version: "8.4"
  web:
    commands:
      - "php artisan migrate --force"
      - "php artisan config:cache"
      - "php artisan route:cache"
      - "php artisan view:cache"
  ```
- **Environment Variables**:

  ```bash
  APP_ENV=production
  APP_DEBUG=false
  APP_KEY=base64:your_app_key
  APP_URL=https://your-app.laravel-cloud.com

  DB_CONNECTION=mysql
  DB_HOST=managed_db_host
  DB_DATABASE=laravel_saas
  ```

- **Pricing Tiers**:
  - **Sandbox**: $0/month (development)
  - **Starter**: $20/month (small production apps)
  - **Professional**: $100/month (growing apps)
  - **Enterprise**: $500/month (high-traffic apps)

## Security & Authentication

### Laravel Sanctum

- **Purpose**: API authentication for SPA and mobile applications
- **Features**:
  - Token-based authentication
  - SPA authentication
  - API rate limiting

### Laravel Fortify

- **Purpose**: Authentication backend implementation
- **Features**:
  - Registration, login, password reset
  - Two-factor authentication
  - Email verification

### Laravel Socialite

- **Purpose**: OAuth authentication with third-party providers
- **Installation**: `composer require laravel/socialite`
- **Supported Providers**:
  - **Google**: OAuth 2.0 integration for Google accounts
  - **Facebook**: Meta/Facebook social login
  - **X (Twitter)**: OAuth 1.0a and 2.0 support
  - **GitHub**: Developer-focused authentication
  - **LinkedIn**: Professional network integration
  - **Apple**: Sign in with Apple ID
- **Extended Providers** (via Socialite Providers):
  - **Discord**: Gaming community integration
  - **Slack**: Workspace authentication
  - **Microsoft**: Azure AD and personal Microsoft accounts
  - **Spotify**: Music platform integration
  - **Twitch**: Streaming platform authentication
- **Key Features**:
  - **Seamless Registration**: Auto-create accounts from social profiles
  - **Account Linking**: Connect multiple social accounts to single user
  - **Profile Data Sync**: Import user information (name, email, avatar)
  - **Secure Token Handling**: OAuth token management and refresh
  - **Fallback Authentication**: Traditional email/password as backup
- **SaaS-Specific Benefits**:
  - **Reduced Friction**: Lower barrier to entry for new users
  - **Higher Conversion**: Social login increases signup rates
  - **Trust Building**: Leverages established platform credibility
  - **User Experience**: Familiar authentication flow
- **Implementation Patterns**:
  - **One-Click Registration**: Direct signup via social providers
  - **Progressive Registration**: Collect additional info post-social login
  - **Team Invitations**: Social login for workspace/team access
  - **Multi-Provider Support**: Let users choose preferred platform

## Performance & Optimization

### Laravel Octane

- **Purpose**: Application performance enhancement
- **Supported Servers**: Swoole, RoadRunner, FrankenPHP
- **Benefits**: Significant performance improvements for high-traffic applications

### Laravel Telescope

- **Purpose**: Debug assistant and application insights
- **Features**:
  - Request monitoring
  - Query analysis
  - Job tracking
  - Exception monitoring

## Payment Processing (SaaS-Specific)

### Laravel Cashier (Stripe)

- **Package**: `laravel/cashier:^15.4`
- **Installation**:
  ```bash
  composer require laravel/cashier
  php artisan vendor:publish --tag="cashier-migrations"
  php artisan migrate
  ```
- **Configuration** (config/services.php):
  ```php
  'stripe' => [
      'model' => App\Models\User::class,
      'key' => env('STRIPE_KEY'),
      'secret' => env('STRIPE_SECRET'),
      'webhook' => [
          'secret' => env('STRIPE_WEBHOOK_SECRET'),
          'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
      ],
  ],
  ```
- **User Model Setup**:

  ```php
  <?php

  use Laravel\Cashier\Billable;

  class User extends Authenticatable
  {
      use Billable;

      // Model implementation
  }
  ```

- **Environment Variables**:
  ```bash
  STRIPE_KEY=pk_test_your_stripe_public_key
  STRIPE_SECRET=sk_test_your_stripe_secret_key
  STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
  ```
- **Subscription Example**:

  ```php
  // Create subscription
  $user->newSubscription('default', 'price_monthly')
      ->create($paymentMethodId);

  // Check subscription
  if ($user->subscribed('default')) {
      // User has active subscription
  }
  ```

## Email & Communication

### Laravel Mail

- **Configuration** (config/mail.php):
  ```php
  'mailers' => [
      'smtp' => [
          'transport' => 'smtp',
          'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
          'port' => env('MAIL_PORT', 587),
          'encryption' => env('MAIL_ENCRYPTION', 'tls'),
          'username' => env('MAIL_USERNAME'),
          'password' => env('MAIL_PASSWORD'),
      ],
  ],
  ```

### Laravel Notifications

- **Generate Notification**:
  ```bash
  php artisan make:notification InvoicePaid
  ```
- **Notification Example**:

  ```php
  <?php

  namespace App\Notifications;

  use Illuminate\Notifications\Notification;
  use Illuminate\Notifications\Messages\MailMessage;

  class InvoicePaid extends Notification
  {
      public function via($notifiable): array
      {
          return ['mail', 'database', TelegramChannel::class];
      }

      public function toMail($notifiable): MailMessage
      {
          return (new MailMessage)
              ->line('Your invoice has been paid!')
              ->action('View Invoice', url('/invoices/'.$this->invoice->id));
      }
  }
  ```

### Telegram Notifications

- **Package**: `laravel-notification-channels/telegram:^5.0`
- **Installation**: `composer require laravel-notification-channels/telegram`
- **Configuration**:
  ```bash
  TELEGRAM_BOT_TOKEN=your_bot_token
  TELEGRAM_CHAT_ID=your_chat_id
  ```
- **Notification Method**:

  ```php
  use NotificationChannels\Telegram\TelegramChannel;
  use NotificationChannels\Telegram\TelegramMessage;

  public function via($notifiable): array
  {
      return [TelegramChannel::class];
  }

  public function toTelegram($notifiable)
  {
      return TelegramMessage::create()
          ->to(env('TELEGRAM_CHAT_ID'))
          ->content("🚨 *Alert*: New user registered!\n\nUser: {$this->user->name}")
          ->button('View User', url('/admin/users/'.$this->user->id));
  }
  ```

## Development Workflow

## Development Workflow & CI/CD

### GitHub Actions Configuration

- **File**: `.github/workflows/laravel.yml`

  ```yaml
  name: Laravel

  on:
    push:
      branches: [main, develop]
    pull_request:
      branches: [main]

  jobs:
    laravel-tests:
      runs-on: ubuntu-latest

      services:
        postgres:
          image: postgres:15
          env:
            POSTGRES_PASSWORD: postgres
            POSTGRES_DB: testing
          options: >-
            --health-cmd pg_isready
            --health-interval 10s
            --health-timeout 5s
            --health-retries 5

      steps:
        - uses: shivammathur/setup-php@v2
          with:
            php-version: "8.4"

        - uses: actions/checkout@v4

        - name: Copy .env
          run: php -r "file_exists('.env') || copy('.env.example', '.env');"

        - name: Install Dependencies
          run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist

        - name: Generate key
          run: php artisan key:generate

        - name: Directory Permissions
          run: chmod -R 777 storage bootstrap/cache

        - name: Execute tests (Unit and Feature tests) via Pest
          env:
            DB_CONNECTION: pgsql
            DB_HOST: 127.0.0.1
            DB_PORT: 5432
            DB_DATABASE: testing
            DB_USERNAME: postgres
            DB_PASSWORD: postgres
          run: vendor/bin/pest --coverage
  ```

### Pre-commit Hooks

- **Package**: `brianium/paratest:^7.4` (for parallel testing)
- **Pre-commit script** (.git/hooks/pre-commit):

  ```bash
  #!/bin/bash

  # Run Laravel Pint
  ./vendor/bin/pint --test
  if [ $? -ne 0 ]; then
      echo "❌ Code style issues found. Run './vendor/bin/pint' to fix."
      exit 1
  fi

  # Run Pest tests
  ./vendor/bin/pest
  if [ $? -ne 0 ]; then
      echo "❌ Tests failed."
      exit 1
  fi

  echo "✅ All checks passed!"
  ```

### Environment Configuration

- **Development** (.env.example):

  ```bash
  APP_NAME="Laravel SaaS"
  APP_ENV=local
  APP_KEY=
  APP_DEBUG=true
  APP_URL=http://localhost

  LOG_CHANNEL=stack
  LOG_DEPRECATIONS_CHANNEL=null
  LOG_LEVEL=debug

  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=5432
  DB_DATABASE=laravel_saas
  DB_USERNAME=laravel
  DB_PASSWORD=password

  CACHE_DRIVER=redis
  FILESYSTEM_DISK=local
  QUEUE_CONNECTION=redis
  SESSION_DRIVER=redis
  SESSION_LIFETIME=120

  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=null
  REDIS_PORT=6379

  MAIL_MAILER=smtp
  MAIL_HOST=mailpit
  MAIL_PORT=1025
  MAIL_USERNAME=null
  MAIL_PASSWORD=null
  MAIL_ENCRYPTION=null
  MAIL_FROM_ADDRESS="hello@example.com"
  MAIL_FROM_NAME="${APP_NAME}"

  VITE_APP_NAME="${APP_NAME}"
  ```

## Essential Commands for AI Development

### Project Setup Commands

```bash
# Create new Laravel project
composer create-project laravel/laravel laravel-saas
cd laravel-saas

# Install development tools
composer require filament/filament
composer require laravel/cashier
composer require sentry/sentry-laravel
composer require spatie/laravel-ray --dev
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev

# Setup Filament
php artisan filament:install --panels

# Setup testing
php artisan pest:install

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Install icon packages
npm install @heroicons/react lucide-react
composer require blade-heroicons/blade-heroicons mallardduck/blade-lucide-icons

# Install frontend dependencies
npm install
npm install react react-dom @inertiajs/react
npm run dev
```

### Daily Development Commands

```bash
# Start development servers
php artisan serve
npm run dev

# Run tests
./vendor/bin/pest
./vendor/bin/pest --coverage

# Code formatting
./vendor/bin/pint

# Queue workers
php artisan queue:work
php artisan horizon

# Clear caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Production Deployment Checklist

### Security Configuration

```bash
# Production environment variables
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your_generated_app_key

# HTTPS enforcement
APP_URL=https://yourdomain.com
FORCE_HTTPS=true

# Session security
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Database connection pooling
DB_CONNECTION=pgsql
DB_HOST=your_managed_db_host
DB_SSLMODE=require
```

### Performance Optimization

```ini
# php.ini - Enable OPcache
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0

# Laravel optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Composer optimization
composer install --no-dev --optimize-autoloader
```

This comprehensive tech stack provides AI coding assistants with exact specifications, installation commands, configuration examples, and production-ready patterns for building Laravel microSaaS applications.

### Laravel Pulse

- **Package**: `laravel/pulse:^1.2`
- **Installation**:
  ```bash
  composer require laravel/pulse
  php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
  php artisan migrate
  ```
- **Configuration** (config/pulse.php):
  ```php
  'recorders' => [
      Recorders\Servers::class => [
          'server_name' => env('PULSE_SERVER_NAME', gethostname()),
          'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
      ],
      Recorders\Database::class => [
          'sample_rate' => env('PULSE_DB_SAMPLE_RATE', 1),
          'threshold' => env('PULSE_DB_THRESHOLD', 1000),
      ],
      Recorders\CacheInteractions::class => [
          'sample_rate' => env('PULSE_CACHE_SAMPLE_RATE', 1),
      ],
  ],
  ```

### Microsoft Clarity

- **Integration**: JavaScript tracking in Blade layout
- **Configuration** (resources/views/layouts/app.blade.php):
  ```html
  <head>
    <!-- Microsoft Clarity -->
    <script type="text/javascript">
      (function (c, l, a, r, i, t, y) {
        c[a] =
          c[a] ||
          function () {
            (c[a].q = c[a].q || []).push(arguments);
          };
        t = l.createElement(r);
        t.async = 1;
        t.src = "https://www.clarity.ms/tag/" + i;
        y = l.getElementsByTagName(r)[0];
        y.parentNode.insertBefore(t, y);
      })(
        window,
        document,
        "clarity",
        "script",
        "{{ env('CLARITY_PROJECT_ID') }}",
      );
    </script>
  </head>
  ```
- **Environment Variable**:
  ```bash
  CLARITY_PROJECT_ID=your_clarity_project_id
  ```
- **Custom Events**:
  ```javascript
  // Track custom events
  clarity("event", "subscription_upgraded");
  clarity("set", "user_plan", "premium");
  ```

## Documentation

### Laravel API Documentation

- **Tool**: Scribe or Laravel API Documentation Generator
- **Purpose**: Automated API documentation generation

This tech stack provides a robust foundation for developing, deploying, and maintaining a Laravel-based microSaaS application with modern development practices and comprehensive tooling coverage.
