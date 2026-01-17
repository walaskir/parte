# SEO & Marketing Guidelines

## Overview

This file provides comprehensive SEO (Search Engine Optimization) and digital marketing guidelines for Laravel SaaS applications. Follow these strategies to grow organic traffic, improve search visibility, and enhance user engagement.

## Related Files

- **Tech Stack**: @tech_stack.md - Analytics tools and SEO package integration
- **Laravel Rules**: @laravel_rules.md - SEO-friendly routing and URL structure
- **Process**: @process.md - Marketing workflow integration with development
- **Git Version Control**: @git_version_control.md - SEO metadata version control

## Core SEO Principles

### Foundation Rules

- **ALWAYS** store all user-facing content in language files for internationalization
- **ALWAYS** use descriptive, search-engine-friendly URLs
- **ALWAYS** implement proper meta tags for all pages
- **ALWAYS** optimize images with descriptive alt text
- **NEVER** hardcode SEO metadata in views
- **ALWAYS** use semantic HTML structure
- **ALWAYS** implement schema markup for rich snippets

## Technical SEO Implementation

### URL Structure & Routing

- **ALWAYS** use clean, descriptive URLs without unnecessary parameters:

    ```php
    // Good URL structure
    Route::get('/blog/{slug}', [BlogController::class, 'show']);
    Route::get('/features/{category}', [FeatureController::class, 'index']);

    // Bad URL structure
    Route::get('/page', [PageController::class, 'show']); // ?id=123
    ```

- **ALWAYS** implement proper URL redirects for changed routes:

    ```php
    // In routes/web.php
    Route::redirect('/old-url', '/new-url', 301);

    // Or in controller
    return redirect()->route('new.route', 301);
    ```

- **ALWAYS** use canonical URLs to prevent duplicate content:
    ```blade
    {{-- In layout --}}
    <link rel="canonical" href="{{ url()->current() }}">
    ```

### Meta Tags Management

Create `app/Services/SeoService.php`:

```php
<?php

namespace App\Services;

class SeoService
{
    /**
     * Generate SEO meta tags for pages
     */
    public static function generateMeta(array $data): array
    {
        $defaults = [
            'title' => config('app.name'),
            'description' => config('seo.default_description'),
            'keywords' => config('seo.default_keywords'),
            'image' => asset('images/og-default.jpg'),
            'url' => url()->current(),
            'type' => 'website',
        ];

        return array_merge($defaults, $data);
    }

    /**
     * Generate Open Graph meta tags
     */
    public static function getOpenGraphTags(array $meta): string
    {
        return view('partials.seo.og-tags', ['meta' => $meta])->render();
    }

    /**
     * Generate Twitter Card meta tags
     */
    public static function getTwitterCardTags(array $meta): string
    {
        return view('partials.seo.twitter-tags', ['meta' => $meta])->render();
    }

    /**
     * Generate JSON-LD schema markup
     */
    public static function generateSchema(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
```

Create SEO configuration `config/seo.php`:

```php
<?php

return [
    'default_description' => env('SEO_DEFAULT_DESCRIPTION', 'Your Laravel SaaS platform description'),
    'default_keywords' => env('SEO_DEFAULT_KEYWORDS', 'laravel, saas, platform'),
    'twitter_handle' => env('TWITTER_HANDLE', '@yourhandle'),
    'facebook_app_id' => env('FACEBOOK_APP_ID'),

    'sitemap' => [
        'enable' => true,
        'cache_duration' => 3600, // 1 hour
    ],

    'robots' => [
        'allow_indexing' => env('SEO_ALLOW_INDEXING', true),
    ],
];
```

### Meta Tags Blade Components

Create `resources/views/components/seo/meta.blade.php`:

```blade
@props(['title', 'description', 'keywords' => '', 'image' => null, 'type' => 'website'])

{{-- Primary Meta Tags --}}
<title>{{ $title }} | {{ config('app.name') }}</title>
<meta name="title" content="{{ $title }}">
<meta name="description" content="{{ $description }}">
@if($keywords)
    <meta name="keywords" content="{{ $keywords }}">
@endif

{{-- Open Graph / Facebook --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
@if($image)
    <meta property="og:image" content="{{ $image }}">
@endif

{{-- Twitter --}}
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="{{ url()->current() }}">
<meta property="twitter:title" content="{{ $title }}">
<meta property="twitter:description" content="{{ $description }}">
@if($image)
    <meta property="twitter:image" content="{{ $image }}">
@endif
@if(config('seo.twitter_handle'))
    <meta name="twitter:site" content="{{ config('seo.twitter_handle') }}">
@endif

{{-- Additional SEO Meta --}}
<meta name="robots" content="{{ config('seo.robots.allow_indexing') ? 'index, follow' : 'noindex, nofollow' }}">
<link rel="canonical" href="{{ url()->current() }}">
```

Usage in views:

```blade
{{-- In your blade templates --}}
<x-seo.meta
    title="Dashboard"
    description="Manage your account and settings"
    keywords="dashboard, account, settings"
    image="{{ asset('images/dashboard-preview.jpg') }}"
/>
```

### Schema Markup Implementation

Create schema helpers in `app/Helpers/SchemaHelper.php`:

```php
<?php

namespace App\Helpers;

class SchemaHelper
{
    /**
     * Generate Organization schema
     */
    public static function organization(): array
    {
        return [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => config('app.name'),
            "url" => config('app.url'),
            "logo" => asset('images/logo.png'),
            "sameAs" => [
                "https://twitter.com/yourhandle",
                "https://facebook.com/yourpage",
                "https://linkedin.com/company/yourcompany",
            ],
        ];
    }

    /**
     * Generate WebSite schema
     */
    public static function website(): array
    {
        return [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => config('app.name'),
            "url" => config('app.url'),
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => config('app.url') . "/search?q={search_term_string}",
                "query-input" => "required name=search_term_string",
            ],
        ];
    }

    /**
     * Generate Product schema for SaaS
     */
    public static function saasProduct(array $data): array
    {
        return [
            "@context" => "https://schema.org",
            "@type" => "SoftwareApplication",
            "name" => $data['name'] ?? config('app.name'),
            "applicationCategory" => "BusinessApplication",
            "offers" => [
                "@type" => "Offer",
                "price" => $data['price'] ?? "0",
                "priceCurrency" => $data['currency'] ?? "USD",
            ],
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => $data['rating'] ?? "4.5",
                "reviewCount" => $data['review_count'] ?? "100",
            ],
        ];
    }

    /**
     * Generate Article schema for blog posts
     */
    public static function article(array $data): array
    {
        return [
            "@context" => "https://schema.org",
            "@type" => "Article",
            "headline" => $data['title'],
            "image" => $data['image'] ?? asset('images/default-article.jpg'),
            "datePublished" => $data['published_at']->toIso8601String(),
            "dateModified" => $data['updated_at']->toIso8601String(),
            "author" => [
                "@type" => "Person",
                "name" => $data['author_name'],
            ],
            "publisher" => self::organization(),
        ];
    }
}
```

Usage in blade:

```blade
{{-- In layout or specific pages --}}
<script type="application/ld+json">
    {!! \App\Helpers\SchemaHelper::organization() | json_encode(JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>

<script type="application/ld+json">
    {!! \App\Helpers\SchemaHelper::saasProduct([
        'name' => 'Your SaaS Product',
        'price' => '29.99',
        'currency' => 'USD',
        'rating' => '4.8',
        'review_count' => '250'
    ]) | json_encode(JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
```

### XML Sitemap Generation

Install sitemap package:

```bash
composer require spatie/laravel-sitemap
```

Create sitemap command `app/Console/Commands/GenerateSitemap.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Generate XML sitemap';

    public function handle(): void
    {
        $sitemap = Sitemap::create();

        // Add static pages
        $sitemap->add(Url::create('/')
            ->setPriority(1.0)
            ->setChangeFrequency('daily'));

        $sitemap->add(Url::create('/features')
            ->setPriority(0.9)
            ->setChangeFrequency('weekly'));

        $sitemap->add(Url::create('/pricing')
            ->setPriority(0.9)
            ->setChangeFrequency('weekly'));

        // Add blog posts
        \App\Models\Post::published()->get()->each(function ($post) use ($sitemap) {
            $sitemap->add(Url::create("/blog/{$post->slug}")
                ->setLastModificationDate($post->updated_at)
                ->setChangeFrequency('monthly')
                ->setPriority(0.7));
        });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully!');
    }
}
```

Schedule sitemap generation in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('sitemap:generate')->daily();
}
```

### Robots.txt Configuration

Create dynamic robots.txt route in `routes/web.php`:

```php
Route::get('/robots.txt', function () {
    $content = view('robots', [
        'allow_indexing' => config('seo.robots.allow_indexing'),
        'sitemap_url' => url('sitemap.xml'),
    ])->render();

    return response($content, 200)
        ->header('Content-Type', 'text/plain');
});
```

Create `resources/views/robots.blade.php`:

```blade
User-agent: *
@if($allow_indexing)
Allow: /
Disallow: /admin/
Disallow: /api/
Disallow: /dashboard/
@else
Disallow: /
@endif

Sitemap: {{ $sitemap_url }}
```

## On-Page SEO Optimization

### Content Optimization

- **ALWAYS** use current year in title tags for freshness:

    ```php
    // In controller
    $meta['title'] = "Best Laravel SaaS Platform " . date('Y');
    ```

- **ALWAYS** implement semantic keyword integration:

    ```php
    // Use related keywords naturally in content
    $keywords = [
        'primary' => 'Laravel SaaS',
        'secondary' => ['SaaS platform', 'cloud software', 'subscription management'],
        'long_tail' => ['Laravel multi-tenant SaaS', 'SaaS billing system'],
    ];
    ```

- **ALWAYS** optimize meta descriptions for click-through rate:
    ```php
    // 150-160 characters, include primary keyword and call-to-action
    $meta['description'] = "Build your SaaS faster with our Laravel platform. " .
                           "Start your 14-day free trial today - no credit card required.";
    ```

### Image Optimization

Create image optimization helper `app/Helpers/ImageHelper.php`:

```php
<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    /**
     * Generate responsive image srcset
     */
    public static function responsiveSrcset(string $imagePath, array $sizes = [320, 640, 768, 1024, 1280]): string
    {
        $srcset = [];
        foreach ($sizes as $width) {
            $srcset[] = asset("images/{$width}/{$imagePath}") . " {$width}w";
        }
        return implode(', ', $srcset);
    }

    /**
     * Get optimized image alt text
     */
    public static function generateAltText(string $filename, ?string $context = null): string
    {
        $alt = str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME));
        $alt = ucwords($alt);

        if ($context) {
            $alt .= " - {$context}";
        }

        return $alt;
    }

    /**
     * Convert image to WebP format
     */
    public static function convertToWebP(string $imagePath): string
    {
        // Implementation for WebP conversion
        // Use intervention/image or similar package
        return str_replace(['.jpg', '.png'], '.webp', $imagePath);
    }
}
```

Usage in blade templates:

```blade
<img
    src="{{ asset('images/feature.jpg') }}"
    srcset="{{ \App\Helpers\ImageHelper::responsiveSrcset('feature.jpg') }}"
    sizes="(max-width: 768px) 100vw, 50vw"
    alt="{{ \App\Helpers\ImageHelper::generateAltText('feature.jpg', 'Dashboard Overview') }}"
    loading="lazy"
    width="1200"
    height="630"
>
```

### Link Structure

- **ALWAYS** use internal linking strategy:

    ```blade
    {{-- Link to related content --}}
    <a href="{{ route('features.detail', 'analytics') }}"
       title="{{ __('Learn more about analytics features') }}">
        {{ __('Discover our analytics') }}
    </a>
    ```

- **ALWAYS** implement breadcrumbs for navigation:
    ```blade
    <nav aria-label="Breadcrumb">
        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a itemprop="item" href="{{ route('home') }}">
                    <span itemprop="name">Home</span>
                </a>
                <meta itemprop="position" content="1" />
            </li>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a itemprop="item" href="{{ route('blog.index') }}">
                    <span itemprop="name">Blog</span>
                </a>
                <meta itemprop="position" content="2" />
            </li>
        </ol>
    </nav>
    ```

## Performance Optimization for SEO

### Page Speed Optimization

- **ALWAYS** implement caching strategies:

    ```php
    // In controller
    $posts = Cache::remember('blog.posts', 3600, function () {
        return Post::published()->latest()->get();
    });
    ```

- **ALWAYS** use CDN for static assets:

    ```php
    // config/filesystems.php
    'cdn' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
    ],
    ```

- **ALWAYS** compress images before serving:
    ```bash
    # Install optimization tools
    composer require intervention/image
    npm install imagemin imagemin-webp
    ```

### Core Web Vitals Optimization

- **ALWAYS** implement lazy loading for images:

    ```blade
    <img src="{{ asset('images/feature.jpg') }}"
         loading="lazy"
         decoding="async">
    ```

- **ALWAYS** optimize Largest Contentful Paint (LCP):

    ```blade
    {{-- Preload critical resources --}}
    <link rel="preload" href="{{ asset('images/hero.jpg') }}" as="image">
    <link rel="preload" href="{{ mix('css/app.css') }}" as="style">
    ```

- **ALWAYS** minimize Cumulative Layout Shift (CLS):
    ```blade
    {{-- Specify image dimensions --}}
    <img src="..." width="1200" height="630" alt="...">
    ```

## Content Strategy for SEO

### Long-Tail Keywords

- **ALWAYS** target specific, lower-competition keywords:

    ```php
    // Example keyword targeting
    $keywords = [
        'how to build a saas with laravel',
        'laravel subscription billing tutorial',
        'multi-tenant laravel application guide',
    ];
    ```

- **ALWAYS** create content clusters:
    ```
    Pillar Content: "Complete Guide to Laravel SaaS Development"
    ├── "Laravel Multi-Tenancy Implementation"
    ├── "SaaS Billing with Laravel Cashier"
    ├── "Authentication in Laravel SaaS"
    └── "Performance Optimization for Laravel SaaS"
    ```

### Content Readability

- **ALWAYS** structure content with clear headings (H1-H6):

    ```blade
    <article>
        <h1>{{ $post->title }}</h1>

        <h2>Introduction</h2>
        <p>...</p>

        <h2>Main Content</h2>
        <h3>Subsection 1</h3>
        <p>...</p>

        <h3>Subsection 2</h3>
        <p>...</p>
    </article>
    ```

- **ALWAYS** use short paragraphs and bullet points
- **ALWAYS** include visual content (images, videos, diagrams)
- **ALWAYS** implement table of contents for long-form content

### Multilingual SEO

- **ALWAYS** implement hreflang tags for international targeting:

    ```blade
    <link rel="alternate" hreflang="en" href="{{ url('en/page') }}" />
    <link rel="alternate" hreflang="es" href="{{ url('es/page') }}" />
    <link rel="alternate" hreflang="fr" href="{{ url('fr/page') }}" />
    <link rel="alternate" hreflang="x-default" href="{{ url('page') }}" />
    ```

- **ALWAYS** localize URLs:
    ```php
    // routes/web.php
    Route::prefix('{locale}')->group(function () {
        Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
    });
    ```

## Analytics & Monitoring

### Google Analytics Integration

Add to layout:

```blade
{{-- Google Analytics 4 --}}
@if(config('services.google_analytics.id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('services.google_analytics.id') }}', {
            'cookie_flags': 'SameSite=None;Secure'
        });
    </script>
@endif
```

### Microsoft Clarity Integration

```blade
{{-- Microsoft Clarity --}}
@if(config('services.clarity.project_id'))
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "{{ config('services.clarity.project_id') }}");
    </script>
@endif
```

### Search Console Integration

- **ALWAYS** verify site ownership in Google Search Console
- **ALWAYS** submit sitemap to Search Console
- **ALWAYS** monitor crawl errors and fix them promptly
- **ALWAYS** track keyword rankings and click-through rates

## Local SEO (if applicable)

### Local Business Schema

```php
// For local businesses
SchemaHelper::localBusiness([
    'name' => 'Your Business Name',
    'address' => [
        'streetAddress' => '123 Main St',
        'addressLocality' => 'City',
        'addressRegion' => 'State',
        'postalCode' => '12345',
        'addressCountry' => 'US',
    ],
    'telephone' => '+1-234-567-8900',
    'openingHours' => 'Mo-Fr 09:00-17:00',
]);
```

## SEO Checklist for New Features

### Before Launching New Pages

- [ ] Implement proper meta tags (title, description, keywords)
- [ ] Add Open Graph and Twitter Card metadata
- [ ] Include schema markup where applicable
- [ ] Optimize images with alt text and lazy loading
- [ ] Implement clean, descriptive URLs
- [ ] Add internal links to related content
- [ ] Test page speed and Core Web Vitals
- [ ] Verify mobile responsiveness
- [ ] Submit updated sitemap to search engines
- [ ] Set up analytics tracking

### Content Publishing Checklist

- [ ] Target specific keywords naturally
- [ ] Use proper heading hierarchy (H1-H6)
- [ ] Optimize meta description for CTR
- [ ] Include relevant internal and external links
- [ ] Add structured data markup
- [ ] Optimize all images
- [ ] Implement social sharing buttons
- [ ] Test content readability
- [ ] Verify canonical URLs
- [ ] Check for broken links

## Essential SEO Commands

```bash
# Generate sitemap
php artisan sitemap:generate

# Clear SEO cache
php artisan cache:forget seo.*

# Test meta tags
curl -I https://yoursite.com/page

# Validate schema markup
# Visit: https://validator.schema.org/

# Check page speed
# Visit: https://pagespeed.web.dev/
```

## Environment Variables

```bash
# SEO Configuration
SEO_DEFAULT_DESCRIPTION="Your default site description"
SEO_DEFAULT_KEYWORDS="laravel, saas, platform"
SEO_ALLOW_INDEXING=true

# Analytics
GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX
CLARITY_PROJECT_ID=xxxxxxxxxx

# Social Media
TWITTER_HANDLE=@yourhandle
FACEBOOK_APP_ID=your_fb_app_id
```

## Monitoring & Maintenance

### Regular SEO Audits

- **WEEKLY**: Check Search Console for errors
- **WEEKLY**: Monitor page speed with PageSpeed Insights
- **MONTHLY**: Review keyword rankings
- **MONTHLY**: Analyze competitor SEO strategies
- **QUARTERLY**: Conduct comprehensive SEO audit
- **QUARTERLY**: Update outdated content with current year

### Tools Integration

- **Google Search Console**: Monitor search performance
- **Google Analytics**: Track user behavior and conversions
- **Microsoft Clarity**: Understand user interactions
- **Ahrefs/SEMrush**: Keyword research and competitor analysis
- **Screaming Frog**: Technical SEO audits

This comprehensive SEO framework ensures your Laravel SaaS application is optimized for search engines, providing maximum visibility and organic traffic growth.
