# Laravel SaaS Development Guidelines

A comprehensive documentation repository containing Laravel SaaS development guidelines and AI coding instructions for consistent, production-ready application development.

## 📋 Overview

This repository serves as a centralized reference for Laravel SaaS development best practices, providing standardized tech stack specifications, workflow guidelines, and coding standards for AI assistants and development teams.

## 🗂️ Repository Structure

```
├── CLAUDE.md                    # Main guidance file for Claude Code AI
├── .ai/                         # Modular documentation directory
│   ├── code_quality.md          # Code quality checks and diagnostics
│   ├── debugging.md             # Comprehensive debugging tools and browser automation
│   ├── doc_lastest.md           # Documentation lookup instructions
│   ├── git_version_control.md   # Git workflow and version control
│   ├── laravel_rules.md         # Laravel-specific development rules
│   ├── process.md               # Development process (TDD, assets)
│   ├── project_settings.md      # Project configuration settings
│   ├── self-improvement_rule.md # Self-improvement guidelines
│   ├── testing.md               # Comprehensive testing guidelines with Pest PHP
│   └── tech_stack.md            # Complete Laravel SaaS tech stack
└── README.md                    # This file
```

## 🚀 Quick Start

### For AI Assistants (Claude Code)

1. Read `CLAUDE.md` for the main guidance and index
2. Follow instructions in root directory files for specific requirements
3. **Always** check code quality before completing tasks
   See: @code_quality.md
   Related: @testing.md, @laravel_rules.md
4. **Always** follow TDD and asset compilation workflow
   See: @process.md
   Related: @testing.md, @tech_stack.md

### For Development Teams

1. Review complete technology specifications
   See: @tech_stack.md
   Related: @laravel_rules.md, @project_settings.md
2. Follow Laravel-specific development standards
   See: @laravel_rules.md
   Related: @testing.md, @process.md
3. Implement git workflow and version control practices
   See: @git_version_control.md
   Related: @process.md, @testing.md
4. Use TDD development methodology
   See: @process.md
   Related: @testing.md, @code_quality.md

## 📚 Key Documentation

### 🔧 Development Essentials

- **Code Quality** - Mandatory quality checks with IDE diagnostics
  See: @code_quality.md
  Related: @process.md (workflow integration), @testing.md (test quality)

- **Testing** - Comprehensive testing guidelines with Pest PHP and TDD methodology
  See: @testing.md
  Related: @process.md (TDD workflow), @laravel_rules.md (Laravel testing), @code_quality.md

- **Development Process** - TDD methodology and asset compilation requirements
  See: @process.md
  Related: @testing.md (TDD implementation), @code_quality.md, @tech_stack.md

- **Laravel Rules** - Laravel-specific development standards and best practices
  See: @laravel_rules.md
  Related: @testing.md, @tech_stack.md (Laravel packages), @process.md

### 🛠️ Technical Specifications

- **Tech Stack** - Complete Laravel SaaS technology stack with exact versions
  See: @tech_stack.md
  Related: @laravel_rules.md (implementation), @debugging.md (tools), @doc_lastest.md

- **Project Settings** - Configuration and environment setup
  See: @project_settings.md
  Related: @tech_stack.md, @git_version_control.md

### 🔄 Workflow & Best Practices

- **Debugging** - Comprehensive debugging tools and browser automation for Laravel applications
  See: @debugging.md
  Related: @tech_stack.md (debugging tools), @laravel_rules.md, @testing.md

- **Git & Version Control** - Branching strategy, commit conventions, PR workflow
  See: @git_version_control.md
  Related: @process.md (development workflow), @testing.md (CI/CD)

- **Documentation Lookup** - Accessing up-to-date library documentation
  See: @doc_lastest.md
  Related: @tech_stack.md (library references), @laravel_rules.md

- **Self-Improvement** - Continuous improvement guidelines
  See: @self-improvement_rule.md
  Related: @code_quality.md (improvement triggers), @process.md

## 🎯 Core Technologies

### Framework & Backend

- **Laravel 12.x** with PHP 8.4+
- **Filament 3.x** for admin panels
- **PostgreSQL 15+** or MySQL 8.0+ for database
- **Redis 7.x** for caching and queues
- **Pest PHP** for testing with TDD methodology

### Frontend & Assets

- **Vite** for asset building
- **Tailwind CSS 3.4+** for styling
- **Heroicons** (primary) and **Lucide** (secondary) for icons
- **React.js + Inertia.js** or **Blade + Alpine.js + Livewire**

### Authentication & Security

- **Laravel Sanctum** for API authentication
- **Laravel Fortify** for authentication backend
- **Laravel Socialite** for OAuth providers

### Performance & Monitoring

- **Laravel Octane** for performance enhancement
- **Laravel Horizon** for queue management
- **Laravel Pulse** for application monitoring
- **Spatie Ray** for debugging and development
- **Browser Automation** with Playwright for E2E testing

## ⚡ Essential Commands

```bash
# Project Setup
composer create-project laravel/laravel project-name
composer require filament/filament laravel/cashier
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
php artisan filament:install --panels
php artisan pest:install

# Daily Development
npm run dev              # ALWAYS run first for frontend work
php artisan serve        # Start Laravel server
./vendor/bin/pest        # Run tests (write tests first!)
./vendor/bin/pint        # Code formatting
php artisan horizon      # Queue processing
```

## 🔴 Critical Requirements

### For AI Development

1. **Code Quality**: Always run `mcp__ide__getDiagnostics` before task completion
2. **Test-Driven Development**: Write failing tests first, then implement
3. **Asset Compilation**: Keep `npm run dev` running during frontend work
4. **Documentation**: Use Context7 MCP server for up-to-date library docs

### For Team Development

1. **Branch Strategy**: Follow git workflow and version control practices
   See: @.ai/git_version_control.md
2. **Commit Messages**: Use conventional commits with issue references
3. **Code Reviews**: Mandatory PR reviews before merging
4. **Testing**: Comprehensive test coverage with Pest PHP and TDD methodology
   See: @.ai/testing.md

## 🏗️ Development Workflow

### 1. Setup Phase

```bash
git clone <repository>
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 2. Development Phase

```bash
npm run dev              # Start asset compilation
php artisan serve        # Start development server
./vendor/bin/pest --watch  # TDD with auto-testing
```

### 3. Quality Assurance

```bash
./vendor/bin/pest        # Run all tests
./vendor/bin/pint        # Code formatting
php artisan optimize:clear # Clear caches
```

## 🎯 Target Applications

This documentation is optimized for:

- **SaaS Applications** with subscription models
- **Multi-tenant Systems** with team/workspace features
- **API-driven Applications** with frontend flexibility
- **High-performance Apps** requiring scalability

## 📈 Best Practices

### Security

- Use environment variables for sensitive data
- Implement proper validation and sanitization
- Follow Laravel security best practices
- Use HTTPS in production

### Performance

- Implement proper caching strategies
- Use database indexing effectively
- Optimize queries and use eager loading
- Configure OPcache for production

### Code Quality

- Follow SOLID principles
- Use type hints and PHPDoc
- Implement comprehensive testing
- Maintain clean, readable code

## 🤝 Contributing

1. Follow the git workflow and version control practices
   See: @git_version_control.md | Related: @process.md
2. Ensure all tests pass before submitting PR
   See: @testing.md | Related: @code_quality.md, @laravel_rules.md
3. Follow the TDD development process
   See: @process.md | Related: @testing.md
4. Update documentation when adding new features

## 📝 License

This documentation repository is designed for team and AI assistant guidance. Adapt as needed for your specific project requirements.

---

**🔗 Quick Navigation**:

- For AI guidance: See @CLAUDE.md
- For technical specifications: See @tech_stack.md
- For testing guidelines: See @testing.md
- For debugging tools: See @debugging.md
- For development workflow: See @process.md
- For code quality: See @code_quality.md
