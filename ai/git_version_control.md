## Git & Version Control

### General Principles

- Add and commit automatically whenever an entire task is finished
- Use descriptive commit messages that capture the full scope of changes
- Single commit features or bugfixes do not require own branches

### Git Configuration

**Rebase on pull operation**:

```bash
git config --global pull.rebase true
git config --global branch.autosetuprebase always
```

**Basic workflow**:

```bash
git add -A
git commit -m '<commit message>'
git pull
git push
```

## Commit Message Format

We use the **Conventional Commits** specification for all commit messages. This enables automated versioning, changelog generation, and clear communication about changes.

### Standard Format

```
<type>[optional scope]: <description>; refs: #<issue_id>[-<comment_number>]

<type>(<scope>): <description>; no refs  # For non-tracked work
```

### Commit Types

| Type       | Purpose            | Use Case                               | Version Impact           |
| ---------- | ------------------ | -------------------------------------- | ------------------------ |
| `feat`     | New feature        | Adding new functionality               | Minor (0.x.0)            |
| `fix`      | Bug fix            | Fixing bugs (any branch)               | Patch (0.0.x)            |
| `docs`     | Documentation      | README, comments, guides               | None                     |
| `style`    | Formatting         | Code style, whitespace                 | None                     |
| `refactor` | Code restructuring | Improve code without changing behavior | None                     |
| `perf`     | Performance        | Performance improvements               | Patch (0.0.x)            |
| `test`     | Testing            | Adding or updating tests               | None                     |
| `build`    | Build system       | Build scripts, dependencies            | None                     |
| `ci`       | CI/CD              | GitHub Actions, pipelines              | None                     |
| `chore`    | Maintenance        | Development tools, configs             | None                     |
| `revert`   | Revert             | Reverting previous commit              | Depends on reverted type |

### Scope Guidelines

Scopes provide context about what area of the codebase is affected:

**Common Scopes**:

- `auth` - Authentication & authorization
- `api` - API endpoints
- `db` - Database, migrations
- `ui` - User interface components
- `admin` - Admin panel
- `payments` - Payment processing
- `email` - Email notifications
- `queue` - Queue jobs
- `tests` - Test files

**Examples**:

```bash
feat(auth): add OAuth2 social login support; refs: #PROJ-1234
fix(api): resolve rate limiting edge case; refs: #1000-2
docs(readme): update installation instructions; no refs
refactor(payments): simplify subscription cancellation flow; refs: #LIN-567
perf(db): add indexes for user queries; refs: #CLICK-890
```

### Issue Reference Format

**With Issue Tracking**:

```bash
# Standard format
<type>(<scope>): <description>; refs: #<issue_id>

# With comment reference
<type>(<scope>): <description>; refs: #<issue_id>-<comment_number>

# Multiple issues
<type>(<scope>): <description>; refs: #<issue_id_1>, #<issue_id_2>
```

**Without Issue Tracking**:

```bash
<type>(<scope>): <description>; no refs
```

**Supported Issue Trackers**:

- ClickUp: `#CLICK-1234`
- Linear: `#LIN-567`
- Jira: `#PROJ-890`
- GitHub Issues: `#123`
- Redmine: `#1000`

### Commit Message Examples

```bash
# New features
feat(auth): add user dashboard with analytics widgets; refs: #1000
feat(api): implement webhook handling for payments; refs: #LIN-567
feat(ui): add dark mode toggle; no refs

# Bug fixes (replaces both bugfix and hotfix)
fix(auth): resolve authentication timeout issue; refs: #1000-2
fix(dashboard): resolve loading performance for premium users; refs: #1000-3
fix(api): handle null values in user response; refs: #PROJ-890

# Documentation
docs(api): update authentication endpoint documentation; no refs
docs(readme): add deployment instructions; refs: #CLICK-1234

# Code improvements
refactor(payments): simplify subscription logic; refs: #567
perf(db): optimize user query with eager loading; refs: #890
style(ui): apply consistent button styling; no refs

# Tests
test(auth): add unit tests for login validation; refs: #1000
test(api): add integration tests for webhooks; no refs

# Development & tooling
chore(deps): upgrade Laravel to 12.x; no refs
ci(github): add automated deployment workflow; refs: #DEV-123
build(vite): optimize production build config; no refs
```

### Breaking Changes

For breaking changes, add `BREAKING CHANGE:` in the commit body or use `!` after the type:

```bash
feat(api)!: change authentication response format; refs: #1000

BREAKING CHANGE: API now returns user object in data.user instead of data
```

This triggers a **major version bump** (x.0.0).

### Commit Message Rules

**REQUIRED**:

- ✅ Use lowercase for type and scope
- ✅ Use imperative mood ("add" not "added" or "adds")
- ✅ Keep description under 72 characters
- ✅ Include issue reference or `no refs`
- ✅ Start description with lowercase letter
- ✅ No period at the end of description

**FORBIDDEN**:

- ❌ **NEVER mention AI assistance** ("generated by Claude", "AI-assisted")
- ❌ No generic messages ("fix bug", "update code")
- ❌ No technical implementation details in subject line
- ❌ No commit message bodies (use PR description instead)

**Best Practices**:

- Focus on **what** changed and **why**, not **how**
- Be specific and descriptive
- Keep messages professional and implementation-agnostic
- Use scopes consistently across the project
- Reference related issues when applicable

## Branch Strategy

### Branch Types

- `feature` - new features and enhancements
- `bugfix` - regular bug fixes on master/main branch
- `hotfix` - emergency fixes on release branches
- `release` - production release branches
- `dev` - development tools and infrastructure

### Naming Conventions

**Feature/Bug branches**:

```
<branch type>/#<issue_id>[-<comment_number>][_v<implementation_version>]
```

**Examples**:

```
feature/#CLICK-1234    # ClickUp task
bugfix/#LIN-567        # Linear issue
hotfix/#PROJ-890       # Jira ticket
feature/#123           # GitHub issue
dev/#maintenance       # Non-tracked work
no refs                # Non-tracked work
```

**Release branches**:

```
release/<version number>
```

## Pull/Merge Request Workflow

### Standard Process

1. **Create Feature Branch**: From updated master/main branch
2. **Develop & Commit**: Follow commit message conventions
3. **Push Branch**: `git push -u origin <branch-name>`
4. **Create PR/MR**: Include description, references, and checklist
5. **Code Review**: Address feedback and update branch
6. **Merge**: Use appropriate merge strategy
7. **Cleanup**: Delete feature branch after successful merge

### PR/MR Template

```markdown
## Description

Brief description of changes and motivation.

## Changes Made

- List specific changes
- Include any breaking changes
- Note any new dependencies

## Testing

- [ ] Unit tests added/updated
- [ ] Integration tests passing
- [ ] Manual testing completed

## References

Refs: #<issue_number>

## Checklist

- [ ] Code follows project conventions
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No secrets or sensitive data committed
```

### Merge Strategies

- **Squash Merge**: For feature branches (clean history)
- **Merge Commit**: For significant features requiring commit history
- **Rebase Merge**: For maintaining linear history (when safe)

### Review Requirements

- **Minimum Reviews**: 1 for features, 2 for critical changes
- **Required Checks**: All CI/CD pipelines must pass
- **Approval Process**: Lead developer approval for architectural changes

## Branch Protection & Security

### Protected Branches Configuration

**Main/Master Branch**:

- Require pull request reviews before merging
- Require status checks to pass before merging
- Require branches to be up to date before merging
- Restrict pushes that create files over 100MB
- Restrict force pushes
- Restrict deletions

**Release Branches**:

- Same protection as main branch
- Require administrator approval for emergency hotfixes
- Lock branch after release deployment

### Status Checks Requirements

- **Automated Tests**: All test suites must pass
- **Code Quality**: Linting, formatting, and static analysis
- **Security Scans**: Dependency vulnerability checks
- **Build Verification**: Successful build completion

### Code Review Guidelines

**Reviewer Responsibilities**:

- Verify code functionality and logic
- Check adherence to coding standards
- Ensure proper error handling and edge cases
- Validate security implications
- Confirm documentation is updated

**Review Criteria**:

- Code readability and maintainability
- Performance impact assessment
- Security vulnerability evaluation
- Test coverage adequacy
- Breaking change identification

### Emergency Procedures

**Hotfix Process**:

1. Create hotfix branch from affected release branch
2. Implement minimal fix with thorough testing
3. Require 2+ senior developer approvals
4. Deploy to staging for validation
5. Merge to release and main branches
6. Tag new patch version immediately

## Related Files

- **Process**: @process.md - Development workflow integration with git practices
- **Testing**: @testing.md - CI/CD pipeline configuration and automated testing
- **Project Settings**: @project_settings.md - Repository configuration and setup requirements

## Automation & CI/CD Integration

### Automated Versioning

**Semantic Versioning with Git Tags**:

```bash
# Replace manual versioning.php with automated tagging
git tag -a v1.2.3 -m "Release version 1.2.3"
git push origin v1.2.3
```

**Automated Version Bumping**:

- Use conventional commits to determine version increments
- `feat:` → minor version bump
- `fix:` → patch version bump
- `BREAKING CHANGE:` → major version bump

### CI/CD Pipeline Integration

**Automated Workflows**:

```yaml
# Example GitHub Actions workflow
on:
  pull_request:
    branches: [main]
  push:
    branches: [main]
    tags: ["v*"]

jobs:
  test:
    - Run test suites
    - Code quality checks
    - Security scans

  build:
    - Build artifacts
    - Generate documentation

  deploy:
    - Deploy to staging (on PR)
    - Deploy to production (on tag)
```

### Git Hooks

**Pre-commit Hooks**:

- Validate commit message format
- Run linting and formatting
- Check for secrets or sensitive data
- Ensure tests pass locally

**Pre-push Hooks**:

- Verify branch naming conventions
- Confirm proper issue references
- Validate that main branch is protected

### Automation Tools

**Recommended Tools**:

- **Conventional Changelog**: Generate changelogs from commits
- **Release Please**: Automate releases based on conventional commits
- **Semantic Release**: Fully automated version management
- **Commitizen**: Interactive commit message assistance
- **Husky**: Git hooks management

### Migration Strategy

**From Manual to Automated**:

1. Implement conventional commits alongside existing system
2. Set up automated testing and quality checks
3. Configure semantic versioning with git tags
4. Gradually phase out manual `versioning.php` script
5. Full automation with release management tools
