# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Purpose

This is a documentation repository containing Laravel SaaS development guidelines and AI coding instructions. It serves as a centralized reference for consistent Laravel development practices and provides standardized tech stack specifications for AI assistants.

## Documentation Index

All specialized instructions are organized in the `.ai` directory for modular access:

### Core Development Guidelines

- **Code Quality** - Critical code quality checks and diagnostics requirements
  See: @code_quality.md
  Related: @process.md (TDD workflow), @testing.md (test requirements)

- **Testing** - Comprehensive testing guidelines with Pest PHP and TDD methodology
  See: @testing.md
  Related: @process.md (TDD workflow), @code_quality.md (quality checks), @laravel_rules.md (testing patterns)

- **Development Process** - Mandatory TDD workflow and asset compilation requirements
  See: @process.md
  Related: @testing.md (TDD implementation), @code_quality.md (quality assurance), @tech_stack.md (development tools)

- **Laravel Rules** - Laravel-specific development standards and best practices
  See: @laravel_rules.md
  Related: @testing.md (Laravel testing), @tech_stack.md (Laravel packages), @process.md (Laravel development)

### Technical Specifications

- **Tech Stack** - Complete Laravel SaaS technology stack with exact package versions
  See: @tech_stack.md
  Related: @laravel_rules.md (Laravel implementation), @debugging.md (development tools), @doc_lastest.md (library documentation)

- **Project Settings** - Project configuration and environment settings
  See: @project_settings.md
  Related: @tech_stack.md (configuration requirements), @git_version_control.md (project setup)

### Workflow & Tooling

- **Debugging** - Comprehensive debugging tools and browser automation for Laravel applications
  See: @debugging.md
  Related: @tech_stack.md (debugging tools), @laravel_rules.md (Laravel debugging patterns), @testing.md (debugging tests)

- **Git & Version Control** - Git workflow, branching strategy, and commit conventions
  See: @git_version_control.md
  Related: @process.md (development workflow), @testing.md (git hooks), @project_settings.md (repository setup)

- **Documentation Lookup** - Instructions for accessing up-to-date library documentation
  See: @doc_lastest.md
  Related: @tech_stack.md (library references), @laravel_rules.md (Laravel documentation)

- **Self-Improvement** - Guidelines for continuous improvement and optimization
  See: @self-improvement_rule.md
  Related: @code_quality.md (improvement triggers), @process.md (workflow optimization)

## Essential Quick Commands

### Project Setup

```bash
# Create new Laravel project
composer create-project laravel/laravel project-name
cd project-name

# Install core packages
composer require filament/filament laravel/cashier
composer require pestphp/pest pestphp/pest-plugin-laravel --dev

# Setup tools
php artisan filament:install --panels
php artisan pest:install
php artisan key:generate
php artisan migrate

# Install frontend dependencies
npm install
npm install @heroicons/react lucide-react
npm run dev
```

### Daily Development

```bash
# Start development (ALWAYS run npm run dev first!)
npm run dev
php artisan serve

# Testing (TDD - write tests first!)
./vendor/bin/pest
./vendor/bin/pest --coverage

# Code formatting
./vendor/bin/pint

# Queue processing
php artisan horizon

# Cache management
php artisan optimize:clear
```

## Important Reminders

🔴 **CRITICAL**: Always follow the instructions in each `.ai` file - they contain mandatory requirements, not suggestions.

🔴 **TDD**: Write tests first, implement after
See: @process.md | Related: @testing.md

🔴 **Code Quality**: Run diagnostics before completing tasks
See: @code_quality.md | Related: @testing.md, @laravel_rules.md

🔴 **Asset Compilation**: Keep `npm run dev` running during frontend work
See: @process.md | Related: @tech_stack.md

---

_This repository provides the foundation for consistent Laravel SaaS development with modern tooling and best practices._
