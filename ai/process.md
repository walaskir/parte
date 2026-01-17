# Development Process Instructions

## Coding Application Workflow

### 1. Asset Compilation

- **ALWAYS** run `npm run dev` during active development to compile assets in real-time
- **NEVER** forget to start the asset compilation process before making frontend changes
- Keep the `npm run dev` process running throughout your development session for automatic asset recompilation
- **ALTERNATIVELY** run `npm run build` when code sections are completed to build production-ready assets

### 2. Test-Driven Development (TDD)

- **ALWAYS** use Test-Driven Development methodology during coding
- **FIRST** write failing tests for the functionality you're about to implement
- **THEN** write the minimum code needed to make the tests pass
- **FINALLY** refactor the code while keeping tests green
- **NEVER** implement functionality without corresponding tests
- Follow the Red-Green-Refactor cycle:
    1. **Red**: Write a failing test
    2. **Green**: Write minimal code to make the test pass
    3. **Refactor**: Improve code quality while maintaining passing tests

## Related Files

- **Testing**: @testing.md - Complete TDD implementation guide with Pest PHP
- **Code Quality**: @code_quality.md - Quality checks integration with TDD workflow
- **Laravel Rules**: @laravel_rules.md - Laravel-specific development patterns
- **Tech Stack**: @tech_stack.md - Development tools and asset compilation setup
- **Git Version Control**: @git_version_control.md - Workflow integration with version control
