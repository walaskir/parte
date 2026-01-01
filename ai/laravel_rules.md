# Laravel Development Rules for AI Coding

## Related Files

- **Testing**: @testing.md - Laravel testing patterns and Pest PHP implementation
- **Tech Stack**: @tech_stack.md - Laravel packages, versions, and configuration
- **Process**: @process.md - Laravel development workflow integration
- **Code Quality**: @code_quality.md - Laravel-specific code quality standards
- **Debugging**: @debugging.md - Laravel debugging tools and patterns

## Database and Migrations

### Timestamp Handling

- **NEVER** use `$table->timestamps()`
- **ALWAYS** use explicit timestamp definitions:
  ```php
  $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
  $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP'));
  ```

### Migration Best Practices

- **ALWAYS** make migrations reversible with proper `down()` methods
- **NEVER** use `Model::create()` or Eloquent in migrations - use `DB::table()` instead
- **ALWAYS** add foreign key constraints:
  ```php
  $table->foreignId('user_id')->constrained()->onDelete('cascade');
  ```
- **ALWAYS** add indexes for frequently queried columns:
  ```php
  $table->index(['status', 'created_at']);
  $table->unique(['email', 'deleted_at']);
  ```
- **NEVER** change column types in existing migrations - create new migrations instead
- **ALWAYS** use nullable columns when appropriate:
  ```php
  $table->string('middle_name')->nullable();
  ```

### Migration Filename Format

- **ALWAYS** preserve Laravel's migration filename format: `YYYY_MM_DD_HHMMSS_migration_name.php`
- **NEVER** modify the datetime prefix that Laravel generates
- **ALWAYS** use descriptive, snake_case names for migration files:

  ```bash
  # Correct format (Laravel generated)
  2024_07_27_143052_create_users_table.php
  2024_07_27_143053_add_email_verified_at_to_users_table.php
  2024_07_27_143054_create_posts_table.php

  # Examples of good migration names
  2024_07_27_143055_create_categories_table.php
  2024_07_27_143056_add_status_column_to_posts_table.php
  2024_07_27_143057_create_user_profile_pivot_table.php
  2024_07_27_143058_drop_unused_columns_from_orders_table.php
  ```

### Migration Safety Rules

- **NEVER** run `migrate:fresh`, `migrate:refresh`, or `migrate:reset` on databases with data
- **ONLY** use destructive migration commands (`migrate:fresh`, `migrate:refresh`, `migrate:reset`) on test databases (usually in memory) for testing purpose
- **NEVER** run `migrate:rollback` for all migrations in production or databases with data
- **ONLY** use `migrate:rollback` for specific migrations when actively resolving migration issues or bugs
- **ALWAYS** backup database before running any rollback operations
- **ALWAYS** use `migrate:rollback --step=1` to rollback only the last migration when needed

### Database Schema Design

- **ALWAYS** use appropriate column types:
  ```php
  $table->uuid('id')->primary();                    // For UUIDs
  $table->decimal('price', 10, 2);                  // For money
  $table->json('metadata');                         // For flexible data
  $table->enum('status', ['pending', 'completed']); // For limited options
  ```
- **NEVER** store sensitive data without encryption
- **ALWAYS** use soft deletes for important data:
  ```php
  $table->softDeletes();
  ```

## Eloquent Models

### Model Structure

- **ALWAYS** define fillable or guarded properties:
  ```php
  protected $fillable = ['name', 'email', 'status'];
  // OR
  protected $guarded = ['id', 'created_at', 'updated_at'];
  ```
- **ALWAYS** define relationships with proper return types:
  ```php
  public function posts(): HasMany
  {
      return $this->hasMany(Post::class);
  }
  ```
- **ALWAYS** use accessors/mutators with new Laravel 9+ syntax:
  ```php
  protected function firstName(): Attribute
  {
      return Attribute::make(
          get: fn ($value) => ucfirst($value),
          set: fn ($value) => strtolower($value),
      );
  }
  ```

### Query Optimization

- **ALWAYS** use eager loading to prevent N+1 queries:
  ```php
  $users = User::with(['posts', 'profile'])->get();
  ```
- **NEVER** use `all()` or `get()` without proper constraints
- **ALWAYS** use chunking for large datasets:
  ```php
  User::chunk(100, function ($users) {
      foreach ($users as $user) {
          // Process user
      }
  });
  ```
- **ALWAYS** use database transactions for complex operations:
  ```php
  DB::transaction(function () {
      // Multiple database operations
  });
  ```

### Scope Usage

- **ALWAYS** define query scopes for reusable logic:
  ```php
  public function scopeActive($query)
  {
      return $query->where('status', 'active');
  }
  ```

## Controllers and Request Handling

### Controller Best Practices

- **ALWAYS** use single responsibility principle - one action per method
- **ALWAYS** use Form Requests for validation:
  ```php
  public function store(CreateUserRequest $request)
  {
      // Validation is handled automatically
  }
  ```
- **NEVER** put business logic in controllers - use Services or Actions
- **ALWAYS** return appropriate HTTP status codes using constants:

  ```php
  use Symfony\Component\HttpFoundation\Response;

  return response()->json($data, Response::HTTP_CREATED);
  return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
  ```

### Resource Controllers

- **ALWAYS** use resource controllers for RESTful APIs:
  ```php
  Route::apiResource('users', UserController::class);
  ```
- **ALWAYS** use API Resources for consistent JSON responses:
  ```php
  return UserResource::collection($users);
  ```

## Validation and Form Requests

### Form Request Rules

- **ALWAYS** create dedicated Form Request classes:
  ```php
  php artisan make:request CreateUserRequest
  ```
- **ALWAYS** use appropriate validation rules:
  ```php
  public function rules(): array
  {
      return [
          'email' => ['required', 'email', 'unique:users,email'],
          'password' => ['required', 'min:8', 'confirmed'],
          'role' => ['required', Rule::in(['admin', 'user'])],
      ];
  }
  ```
- **ALWAYS** provide custom error messages when needed:
  ```php
  public function messages(): array
  {
      return [
          'email.unique' => 'This email is already registered.',
      ];
  }
  ```

### Custom Validation Rules

- **ALWAYS** create custom rules for complex validation:
  ```php
  php artisan make:rule StrongPassword
  ```

## Security Best Practices

### Authentication and Authorization

- **ALWAYS** use Laravel Sanctum for API authentication
- **ALWAYS** implement proper authorization with Gates and Policies:
  ```php
  Gate::define('update-post', function ($user, $post) {
      return $user->id === $post->user_id;
  });
  ```
- **NEVER** trust user input - always validate and sanitize
- **ALWAYS** use HTTPS in production
- **ALWAYS** implement rate limiting:
  ```php
  Route::middleware('throttle:60,1')->group(function () {
      // API routes
  });
  ```

### Data Protection

- **ALWAYS** use encrypted fields for sensitive data:
  ```php
  protected $casts = [
      'sensitive_data' => 'encrypted',
  ];
  ```
- **NEVER** log sensitive information
- **ALWAYS** use CSRF protection for web routes
- **ALWAYS** implement proper session management

## API Development

### API Design

- **ALWAYS** version your APIs:
  ```php
  Route::prefix('v1')->group(function () {
      // API routes
  });
  ```
- **ALWAYS** use consistent response format:
  ```php
  return response()->json([
      'data' => $resource,
      'message' => 'Success',
      'status' => 'success'
  ]);
  ```
- **ALWAYS** implement proper error handling:

  ```php
  use Symfony\Component\HttpFoundation\Response;

  return response()->json([
      'message' => 'Validation failed',
      'errors' => $validator->errors()
  ], Response::HTTP_UNPROCESSABLE_ENTITY);
  ```

### API Resources

- **ALWAYS** use API Resources for data transformation:
  ```php
  php artisan make:resource UserResource
  ```
- **ALWAYS** hide sensitive data in API responses

## Testing Practices

### Mandatory Testing Requirements

- **ALWAYS** create tests for every new functionality you implement
- **NEVER** commit code without corresponding tests
- **ALWAYS** ensure tests pass before marking functionality as complete
- **ALWAYS** aim for minimum 80% code coverage

### Test Structure

- **ALWAYS** write tests using Pest PHP
- **ALWAYS** use Feature tests for HTTP endpoints
- **ALWAYS** use Unit tests for individual methods
- **ALWAYS** use factories for test data:
  ```php
  $user = User::factory()->create(['email' => 'test@example.com']);
  ```

### Test Organization

- **ALWAYS** test both positive and negative scenarios
- **ALWAYS** test edge cases and boundary conditions
- **NEVER** test Laravel framework functionality
- **ALWAYS** use database transactions in tests:
  ```php
  uses(RefreshDatabase::class);
  ```

### Test Coverage Requirements

- **ALWAYS** test controller methods with all possible responses
- **ALWAYS** test model relationships and scopes
- **ALWAYS** test validation rules (both valid and invalid data)
- **ALWAYS** test business logic in Service/Action classes
- **ALWAYS** test API endpoints with different authentication states
- **ALWAYS** test error scenarios and exception handling

### Test Examples for Common Patterns

```php
// Feature test for API endpoint
test('user can create post with valid data', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Test content',
            'status' => 'published'
        ]);

    $response->assertStatus(Response::HTTP_CREATED)
        ->assertJsonStructure(['data' => ['id', 'title', 'content']]);

    $this->assertDatabaseHas('posts', [
        'title' => 'Test Post',
        'user_id' => $user->id
    ]);
});

// Unit test for service class
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

// Validation test
test('user creation fails with invalid email', function () {
    $response = $this->postJson('/api/users', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'password' => 'password123'
    ]);

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['email']);
});

// Authorization test
test('user cannot access other users posts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user1)
        ->getJson("/api/posts/{$post->id}");

    $response->assertStatus(Response::HTTP_FORBIDDEN);
});
```

## Performance and Caching

### Query Performance

- **ALWAYS** use database indexes for frequently queried columns
- **ALWAYS** use `select()` to limit retrieved columns when needed:
  ```php
  User::select(['id', 'name', 'email'])->get();
  ```
- **ALWAYS** use pagination for large datasets:
  ```php
  User::paginate(20);
  ```

### Caching Strategies

- **ALWAYS** cache expensive operations:
  ```php
  Cache::remember('users.active', 3600, function () {
      return User::active()->get();
  });
  ```
- **ALWAYS** use Redis for session and cache storage in production
- **ALWAYS** implement cache tags for organized invalidation:
  ```php
  Cache::tags(['users', 'posts'])->put('key', $value);
  ```

## Service Layer and Architecture

### Service Classes

- **ALWAYS** create Service classes for complex business logic:
  ```php
  class UserService
  {
      public function createUser(array $data): User
      {
          return DB::transaction(function () use ($data) {
              // Complex user creation logic
          });
      }
  }
  ```
- **ALWAYS** use dependency injection:
  ```php
  public function __construct(
      private UserService $userService,
      private NotificationService $notificationService
  ) {}
  ```

### Action Classes

- **ALWAYS** use Action classes for single-purpose operations:
  ```php
  class CreateUserAction
  {
      public function execute(array $data): User
      {
          // Single responsibility: create user
      }
  }
  ```

## Queue and Job Processing

### Job Design

- **ALWAYS** make jobs idempotent and retryable:
  ```php
  class ProcessPayment implements ShouldQueue
  {
      public $tries = 3;
      public $timeout = 120;
  }
  ```
- **ALWAYS** use proper error handling in jobs
- **ALWAYS** implement job middleware when needed

### Queue Configuration

- **ALWAYS** use Redis for queue processing in production
- **ALWAYS** monitor queue performance with Horizon
- **ALWAYS** configure multiple queues based on priority levels:

  ```php
  // config/queue.php
  'connections' => [
      'redis' => [
          'driver' => 'redis',
          'connection' => 'default',
          'queue' => env('REDIS_QUEUE', 'default'),
          'retry_after' => 90,
          'block_for' => null,
      ],
  ],

  // Multiple queue configuration
  'queues' => [
      'critical' => 'critical',      // Emails, notifications
      'high' => 'high',              // User actions, payments
      'default' => 'default',        // Regular tasks
      'low' => 'low',                // Reports, cleanup
  ],
  ```

### Queue Priority Management

- **ALWAYS** assign appropriate queue priorities:

  ```php
  // Critical priority - immediate processing (emails)
  Mail::to($user)->queue(new WelcomeEmail($user))->onQueue('critical');
  SendEmailNotification::dispatch($user, $message)->onQueue('critical');

  // High priority - important user actions
  ProcessPayment::dispatch($payment)->onQueue('high');
  GenerateInvoice::dispatch($order)->onQueue('high');

  // Default priority - regular tasks
  GenerateReport::dispatch($data)->onQueue('default');
  ProcessImage::dispatch($image)->onQueue('default');

  // Low priority - background tasks
  CleanupOldFiles::dispatch()->onQueue('low');
  UpdateAnalytics::dispatch()->onQueue('low');
  ```

### Queue Worker Configuration

- **ALWAYS** configure workers for each priority level:
  ```bash
  # Start workers with different priorities
  php artisan queue:work redis --queue=critical --timeout=60 --tries=3
  php artisan queue:work redis --queue=high,default --timeout=120 --tries=3
  php artisan queue:work redis --queue=low --timeout=300 --tries=2
  ```
- **ALWAYS** use Horizon for queue management in production:
  ```php
  // config/horizon.php
  'environments' => [
      'production' => [
          'supervisor-1' => [
              'connection' => 'redis',
              'queue' => ['critical'],
              'balance' => 'simple',
              'processes' => 3,
              'tries' => 3,
              'timeout' => 60,
          ],
          'supervisor-2' => [
              'connection' => 'redis',
              'queue' => ['high', 'default'],
              'balance' => 'auto',
              'processes' => 5,
              'tries' => 3,
              'timeout' => 120,
          ],
          'supervisor-3' => [
              'connection' => 'redis',
              'queue' => ['low'],
              'balance' => 'simple',
              'processes' => 2,
              'tries' => 2,
              'timeout' => 300,
          ],
      ],
  ],
  ```

### Queue Monitoring and Management

- **ALWAYS** monitor queue health and performance:

  ```php
  // Monitor failed jobs
  php artisan queue:failed
  php artisan queue:retry all
  php artisan queue:flush

  // Monitor queue size and status
  php artisan horizon:status
  php artisan horizon:terminate
  ```

- **ALWAYS** implement queue health checks:

  ```php
  // In a scheduled command or health check endpoint
  use Illuminate\Support\Facades\Redis;

  public function checkQueueHealth(): array
  {
      $redis = Redis::connection();

      return [
          'critical_queue_size' => $redis->llen('queues:critical'),
          'high_queue_size' => $redis->llen('queues:high'),
          'default_queue_size' => $redis->llen('queues:default'),
          'low_queue_size' => $redis->llen('queues:low'),
          'failed_jobs_count' => DB::table('failed_jobs')->count(),
      ];
  }
  ```

- **ALWAYS** set up queue alerts for critical queues:
  ```php
  // In a monitoring service or scheduled command
  if ($this->checkQueueHealth()['critical_queue_size'] > 100) {
      // Send alert - critical queue is backing up
      Notification::route('slack', config('slack.webhook'))
          ->notify(new QueueBackupAlert('critical', $queueSize));
  }
  ```
- **ALWAYS** use appropriate job middleware for rate limiting:

  ```php
  use Illuminate\Queue\Middleware\RateLimited;
  use Illuminate\Queue\Middleware\WithoutOverlapping;

  public function middleware()
  {
      return [
          new RateLimited('emails'),
          new WithoutOverlapping($this->user->id),
      ];
  }
  ```

## Error Handling and Logging

### Exception Handling

- **ALWAYS** create custom exceptions for business logic:
  ```php
  class PaymentFailedException extends Exception
  {
      public function report(): bool
      {
          // Custom reporting logic
          return false;
      }
  }
  ```
- **ALWAYS** log appropriate information for debugging
- **NEVER** expose sensitive information in error messages

### Logging Best Practices

- **ALWAYS** use appropriate log levels
- **ALWAYS** include context in log messages:
  ```php
  Log::info('User created', ['user_id' => $user->id]);
  ```

## Localization and Language Files

### Language File Management

- **ALWAYS** store all user-facing text in language files
- **NEVER** hardcode text strings in controllers, views, or components
- **ALWAYS** use translation keys instead of raw text

### Language File Structure

- **ALWAYS** organize language files by feature/module:

  ```php
  // resources/lang/en/auth.php
  return [
      'failed' => 'These credentials do not match our records.',
      'password' => 'The provided password is incorrect.',
      'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
  ];

  // resources/lang/en/users.php
  return [
      'created' => 'User created successfully.',
      'updated' => 'User updated successfully.',
      'deleted' => 'User deleted successfully.',
      'not_found' => 'User not found.',
  ];
  ```

### Translation Usage

- **ALWAYS** use the `__()` helper or `trans()` function:

  ```php
  // In controllers
  return response()->json([
      'message' => __('users.created'),
      'data' => $user
  ]);

  // In validation messages
  public function messages(): array
  {
      return [
          'email.required' => __('validation.required', ['attribute' => 'email']),
          'email.unique' => __('users.email_taken'),
      ];
  }

  // In notifications
  public function toMail($notifiable): MailMessage
  {
      return (new MailMessage)
          ->subject(__('notifications.payment_received'))
          ->line(__('notifications.payment_success'))
          ->action(__('notifications.view_invoice'), $this->url);
  }
  ```

### Translation in Views

- **ALWAYS** use translation helpers in Blade templates:

  ```blade
  {{-- Blade views --}}
  <h1>{{ __('dashboard.welcome') }}</h1>
  <p>{{ __('dashboard.user_greeting', ['name' => $user->name]) }}</p>

  {{-- Form labels --}}
  <label for="email">{{ __('forms.email') }}</label>
  <button type="submit">{{ __('forms.submit') }}</button>

  {{-- Error messages --}}
  @error('email')
      <span class="error">{{ __('validation.email') }}</span>
  @enderror
  ```

### Translation in React/JavaScript

- **ALWAYS** pass translations to frontend components:
  ```php
  // In Inertia controller
  return Inertia::render('Dashboard', [
      'user' => $user,
      'translations' => [
          'welcome' => __('dashboard.welcome'),
          'logout' => __('auth.logout'),
      ]
  ]);
  ```
  ```jsx
  // In React components
  export default function Dashboard({ user, translations }) {
    return (
      <div>
        <h1>{translations.welcome}</h1>
        <button>{translations.logout}</button>
      </div>
    );
  }
  ```

### API Response Messages

- **ALWAYS** use language files for API responses:

  ```php
  use Symfony\Component\HttpFoundation\Response;

  // Success responses
  return response()->json([
      'message' => __('api.success'),
      'data' => $resource
  ], Response::HTTP_OK);

  // Error responses
  return response()->json([
      'message' => __('api.validation_failed'),
      'errors' => $validator->errors()
  ], Response::HTTP_UNPROCESSABLE_ENTITY);

  // Not found responses
  return response()->json([
      'message' => __('api.resource_not_found')
  ], Response::HTTP_NOT_FOUND);
  ```

### Pluralization

- **ALWAYS** use Laravel's pluralization features:

  ```php
  // resources/lang/en/messages.php
  return [
      'items_count' => '{0} No items|{1} One item|[2,*] :count items',
      'time_ago' => '{1} :value minute ago|[2,*] :value minutes ago',
  ];

  // Usage
  echo trans_choice('messages.items_count', $count, ['count' => $count]);
  ```

### Multi-language Support

- **ALWAYS** prepare for internationalization from the start:

  ```php
  // config/app.php
  'locale' => env('APP_LOCALE', 'en'),
  'fallback_locale' => 'en',
  'available_locales' => ['en', 'es', 'fr', 'de'],
  ```

  ```php
  // Middleware for language switching
  class SetLocale
  {
      public function handle($request, Closure $next)
      {
          if ($request->has('lang')) {
              App::setLocale($request->lang);
              session(['locale' => $request->lang]);
          } elseif (session('locale')) {
              App::setLocale(session('locale'));
          }

          return $next($request);
      }
  }
  ```

### Language File Organization Best Practices

- **ALWAYS** use consistent key naming:
  ```php
  // Good structure
  'user' => [
      'actions' => [
          'create' => 'Create User',
          'edit' => 'Edit User',
          'delete' => 'Delete User',
      ],
      'messages' => [
          'created' => 'User created successfully',
          'updated' => 'User updated successfully',
          'deleted' => 'User deleted successfully',
      ],
      'fields' => [
          'name' => 'Name',
          'email' => 'Email Address',
          'password' => 'Password',
      ]
  ]
  ```

## General Programming Principles

### Code Quality

- **ALWAYS** follow SOLID principles
- **ALWAYS** use meaningful variable and method names
- **ALWAYS** keep methods small and focused (max 20 lines)
- **ALWAYS** use type hints and return types:
  ```php
  public function getUser(int $id): ?User
  {
      return User::find($id);
  }
  ```
- **NEVER** use magic numbers - define constants or use Symfony Response constants
- **ALWAYS** use `Symfony\Component\HttpFoundation\Response` constants for HTTP status codes:

  ```php
  use Symfony\Component\HttpFoundation\Response;

  // Common HTTP status codes
  Response::HTTP_OK                    // 200
  Response::HTTP_CREATED               // 201
  Response::HTTP_NO_CONTENT            // 204
  Response::HTTP_BAD_REQUEST           // 400
  Response::HTTP_UNAUTHORIZED          // 401
  Response::HTTP_FORBIDDEN             // 403
  Response::HTTP_NOT_FOUND             // 404
  Response::HTTP_METHOD_NOT_ALLOWED    // 405
  Response::HTTP_UNPROCESSABLE_ENTITY  // 422
  Response::HTTP_INTERNAL_SERVER_ERROR // 500
  ```

- **ALWAYS** document complex logic with comments

### Laravel 12 Specific Features

- **ALWAYS** use new Collection methods and improvements
- **ALWAYS** leverage enhanced validation features
- **ALWAYS** use improved database query builder features
- **ALWAYS** implement new security enhancements
- **ALWAYS** use performance improvements in Eloquent

### Code Organization

- **ALWAYS** group related functionality in modules/packages
- **ALWAYS** use consistent naming conventions
- **ALWAYS** implement proper namespacing
- **NEVER** create God classes or methods

## Development Workflow

### Version Control

- **ALWAYS** use meaningful commit messages
- **ALWAYS** create feature branches for new development
- **NEVER** commit sensitive information
- **ALWAYS** use git hooks for code quality checks

### Environment Management

- **ALWAYS** use different configurations for different environments
- **NEVER** hardcode environment-specific values
- **ALWAYS** use Laravel's environment configuration features
