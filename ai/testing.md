# Laravel Testing Guidelines

## Overview

This file provides comprehensive testing guidelines for Laravel applications using Pest PHP, including Test-Driven Development (TDD) methodology, test structure, and best practices.

## Related Files

- **Process**: @process.md - TDD workflow and Red-Green-Refactor cycle implementation
- **Laravel Rules**: @laravel_rules.md - Laravel-specific testing patterns and requirements
- **Code Quality**: @code_quality.md - Quality assurance integration with testing
- **Debugging**: @debugging.md - Test debugging tools and browser automation
- **Tech Stack**: @tech_stack.md - Testing tools and package specifications
- **Git Version Control**: @git_version_control.md - CI/CD pipeline and automated testing

## Mandatory Testing Requirements

### Core Testing Rules

- **ALWAYS** create tests for every new functionality you implement
- **NEVER** commit code without corresponding tests
- **ALWAYS** ensure tests pass before marking functionality as complete
- **ALWAYS** aim for minimum 80% code coverage
- **ALWAYS** use Test-Driven Development methodology during coding

### Test-Driven Development (TDD) Workflow

- **FIRST** write failing tests for the functionality you're about to implement
- **THEN** write the minimum code needed to make the tests pass
- **FINALLY** refactor the code while keeping tests green
- **NEVER** implement functionality without corresponding tests

Follow the Red-Green-Refactor cycle:

1. **Red**: Write a failing test
2. **Green**: Write minimal code to make the test pass
3. **Refactor**: Improve code quality while maintaining passing tests

## Pest PHP Setup and Configuration

### Installation

```bash
# Install Pest PHP and Laravel plugin
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

### Configuration

Create or update `tests/Pest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');
```

### Environment Configuration

Add to `.env.testing`:

```bash
# Testing Database
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Disable external services in testing
MAIL_MAILER=log
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
SESSION_DRIVER=array

# Testing Configuration
APP_ENV=testing
APP_DEBUG=true
LOG_CHANNEL=stack
```

## Test Structure and Organization

### Test Types

- **ALWAYS** use Feature tests for HTTP endpoints and user workflows
- **ALWAYS** use Unit tests for individual methods and classes
- **ALWAYS** use Browser tests for complex user interactions (with Dusk/Playwright)

### Test Organization

```
tests/
├── Feature/           # HTTP endpoints, user workflows
│   ├── Auth/         # Authentication tests
│   ├── Api/          # API endpoint tests
│   └── Web/          # Web route tests
├── Unit/             # Individual class/method tests
│   ├── Models/       # Model tests
│   ├── Services/     # Service class tests
│   └── Helpers/      # Helper function tests
└── Browser/          # End-to-end browser tests
```

### Test Data Management

- **ALWAYS** use factories for test data:

```php
$user = User::factory()->create(['email' => 'test@example.com']);
$posts = Post::factory()->count(3)->create();
```

- **ALWAYS** use database transactions in tests:

```php
uses(RefreshDatabase::class);
```

## Test Examples and Patterns

### Feature Test Example

```php
<?php

test('user can register with valid data', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
    ]);
});

test('user registration fails with invalid email', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
```

### API Test Example

```php
<?php

use Symfony\Component\HttpFoundation\Response;

test('api returns user data for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/api/user');

    $response->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email'],
        ]);
});

test('api returns unauthorized for unauthenticated user', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});
```

### Unit Test Example

```php
<?php

test('user service creates user with encrypted password', function () {
    $userService = new UserService();

    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123'
    ];

    $user = $userService->createUser($userData);

    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect(Hash::check('password123', $user->password))->toBeTrue();
});
```

### Authorization Test Example

```php
<?php

test('user cannot access other users posts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user1)
        ->getJson("/api/posts/{$post->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});
```

## Test Coverage Requirements

### Mandatory Test Coverage

- **ALWAYS** test controller methods with all possible responses
- **ALWAYS** test model relationships and scopes
- **ALWAYS** test validation rules (both valid and invalid data)
- **ALWAYS** test business logic in Service/Action classes
- **ALWAYS** test API endpoints with different authentication states
- **ALWAYS** test error scenarios and exception handling

### Testing Scenarios

- **ALWAYS** test both positive and negative scenarios
- **ALWAYS** test edge cases and boundary conditions
- **NEVER** test Laravel framework functionality
- **ALWAYS** test custom business logic thoroughly

## Essential Testing Commands

### Daily Testing Commands

```bash
# Run all tests
./vendor/bin/pest

# Run tests with coverage
./vendor/bin/pest --coverage

# Run tests in parallel
./vendor/bin/pest --parallel

# Run specific test file
./vendor/bin/pest tests/Feature/AuthTest.php

# Run tests with watch mode (TDD)
./vendor/bin/pest --watch

# Run tests with detailed output
./vendor/bin/pest --verbose
```

### CI/CD Testing Commands

```bash
# Run tests for CI with coverage and reporting
./vendor/bin/pest --coverage --coverage-html=coverage --coverage-clover=coverage.xml

# Run tests with parallel execution for faster CI
./vendor/bin/pest --parallel --processes=4
```

## Advanced Testing Patterns

### Testing with Factories

```php
// Create related models
$user = User::factory()
    ->has(Post::factory()->count(3))
    ->create();

// Create with specific states
$user = User::factory()
    ->verified()
    ->admin()
    ->create();
```

### Testing Database Transactions

```php
test('complex operation maintains data integrity', function () {
    DB::transaction(function () {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        // Test complex business logic
        expect($order->total)->toBeGreaterThan(0);
    });
});
```

### Testing Queue Jobs

```php
test('job is dispatched when user registers', function () {
    Queue::fake();

    $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Queue::assertPushed(SendWelcomeEmail::class);
});
```

## Pre-commit Testing

### Git Hooks Configuration

Create `.git/hooks/pre-commit`:

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

### GitHub Actions Configuration

```yaml
name: Laravel Tests

on: [push, pull_request]

jobs:
    laravel-tests:
        runs-on: ubuntu-latest

        steps:
            - uses: actions/checkout@v4

            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.4"

            - name: Install Dependencies
              run: composer install --no-interaction --prefer-dist

            - name: Generate Application Key
              run: php artisan key:generate

            - name: Run Tests
              run: ./vendor/bin/pest --coverage
```

This comprehensive testing setup ensures high-quality, well-tested Laravel applications following TDD principles and best practices.
