# AGENTS.md

Guidance for agentic coding assistants working in this repository.

## Non-Negotiable Rules

- MUST follow strict TDD: no production change without a failing test first.
- MUST apply SOLID and Clean Code in every change.
- MUST leave touched code cleaner than before (Boy Scout Rule).
- MUST run the narrowest relevant tests before expanding scope.
- MUST keep `composer cs`, `composer phpstan`, and `composer test` green before finishing substantial work.
- NEVER bypass failing checks by weakening tests or reducing static analysis strictness.
- NEVER add dead code, speculative abstractions, or TODO-driven placeholders in production paths.
- NEVER use unusual commit subject prefixes (no `feat:`, `fix:`, brackets, emojis, ticket noise).

## Mission

- Build a high-quality Composer plugin codebase that favors clarity, safety, and maintainability.
- Follow SOLID principles, Clean Code practices, and strict TDD.
- Write code that would make Uncle Bob (Robert C. Martin) proud: simple, readable, tested, and intentional.

## Core Values

- Keep functions and methods small, focused, and expressive.
- Prefer explicitness over cleverness.
- Eliminate duplication, but do not over-abstract too early.
- Optimize for easy change and safe refactoring.
- Leave the code cleaner than you found it.

## Required Workflow (Strict TDD)

1. Write a failing test first (red).
2. Implement the minimal production code to make it pass (green).
3. Refactor while keeping tests green (refactor).
4. Repeat in very small increments.

Rules:

- Do not add production behavior without a failing test that proves the need.
- Prefer one behavior per test.
- Keep test names descriptive and behavior-focused.
- Run the narrowest possible test loop first, then the full suite.
- If you cannot produce a failing test first, stop and redesign the change until you can.

## Environment and Setup

Prerequisites:

- PHP 8.2+ (CI also runs 8.3 and 8.4)
- Composer v2

Optional local shell setup:

- If using Nix + direnv:
  - `direnv allow`
  - `nix develop`

Install dependencies:

- `composer install --no-interaction --prefer-dist --no-progress`

Validate composer metadata:

- `composer validate --strict`

## Repository Layout

- `src/` production code (namespace `ComposerSubtreePlugin\\`)
- `tests/` PHPUnit tests (namespace `ComposerSubtreePlugin\\Tests\\`)
- `phpstan.neon` static analysis config
- `.php-cs-fixer.dist.php` formatting rules
- `phpunit.xml.dist` test config
- `.github/workflows/ci.yml` CI pipeline

## Build, Lint, Test Commands

This repository has no separate compile/build artifact step.
Use QA checks below as the quality gate.

Primary commands:

- Lint PHP syntax: `composer lint`
- Check formatting (no writes): `composer cs`
- Fix formatting: `composer cs:fix`
- Static analysis: `composer phpstan`
- Run all tests: `composer test`
- Run full QA suite: `composer qa`

CI parity command order:

1. `composer validate --strict`
2. `composer lint`
3. `composer cs`
4. `composer phpstan`
5. `composer test`

## Running a Single Test (Important)

Single test file:

- `composer test -- tests/PluginNameTest.php`

Single test method (recommended fast loop):

- `composer test -- --filter testItReturnsThePackageName tests/PluginNameTest.php`

Direct PHPUnit equivalent (if needed):

- `vendor/bin/phpunit --configuration=phpunit.xml.dist tests/PluginNameTest.php`
- `vendor/bin/phpunit --configuration=phpunit.xml.dist --filter testItReturnsThePackageName tests/PluginNameTest.php`

Tip:

- During TDD, run a single method repeatedly, then run `composer test`, then `composer qa`.

## External Repositories for Integration Testing

Use these small public repositories when testing subtree workflows or fixture setup:

- `composer/pcre` -> `https://github.com/composer/pcre.git` (small utility library, actively maintained)
- `psr/log` -> `https://github.com/php-fig/log.git` (small stable interfaces package, widely used)
- or make a small local repo in /tmp/

Rules for using external repos in tests:

- Prefer shallow clones and pinned refs/tags for deterministic test behavior.
- Do not mutate upstream history; use local temp remotes/branches in test fixtures.
- Keep network-dependent tests isolated and optional when possible.

## Code Style and Conventions

### PHP Baseline

- Use `declare(strict_types=1);` in all PHP files.
- Follow PSR-4 autoloading and current namespace structure.
- Prefer `final` classes by default unless extension is a deliberate requirement.
- Use constructor injection for dependencies.

### Imports and Namespaces

- Use explicit `use` imports; one import per line.
- Remove unused imports.
- Do not use fully-qualified class names inline when an import improves readability.
- Keep namespace declarations aligned with folder paths.

### Formatting

- Formatting is enforced by PHP CS Fixer with `@PER-CS` rules.
- Do not hand-format against fixer output; run `composer cs:fix` when needed.
- Keep diffs minimal and focused.

### Types

- Type all parameters, return values, and properties.
- Avoid `mixed` unless unavoidable and documented by context.
- Prefer value objects and small abstractions over primitive obsession when behavior grows.
- Keep static analysis (`phpstan` level max + strict rules) clean.
- Use parameter promotion in constructor instead of creating
  memembers.
  
### Naming

- Use intention-revealing names.
- Classes: nouns (`SubtreeConfigLoader`).
- Methods: verbs or verb phrases (`loadConfig`, `validatePrefix`).
- Booleans: prefixes like `is`, `has`, `can`, `should`.
- Tests: behavior statements, e.g. `testItRejectsMissingPrefix`.

### Error Handling

- Fail fast with specific exceptions for invalid state.
- Write actionable error messages (what failed and next action).
- Do not swallow exceptions silently.
- Avoid boolean error codes when exceptions communicate failure better.

### Clean Code and SOLID Expectations

- **S**ingle Responsibility: one reason to change per class.
- **O**pen/Closed: extend via composition and new types, not risky edits to stable logic.
- **L**iskov Substitution: maintain behavioral contracts when introducing abstractions.
- **I**nterface Segregation: prefer small focused interfaces.
- **D**ependency Inversion: depend on abstractions at architectural boundaries.

Also apply:

- Keep cyclomatic complexity low.
- Prefer guard clauses over deep nesting.
- Remove dead code quickly.
- Refactor mercilessly after tests are green.
- Optimize for readability over cleverness every time.
- Code should be understandable by a teammate in minutes, not hours.

## Testing Standards

- PHPUnit 10 is the test framework.
- Every bug fix starts with a failing regression test.
- New features require tests before implementation.
- Assert behavior, not internals, unless internals are the contract.
- Use deterministic tests; avoid timing and environment flakiness.

## Git and Commit Guidance

- Commit early and often in small, coherent steps.
- Make small, coherent commits.
- Commit messages should be short, clear, and plain English.
- Do not use unusual prefixes or noisy tags in commit subjects.
- Preferred commit subject style: lowercase imperative phrase, 3-8 words.
- Prefer frequent commits after each meaningful green TDD cycle (red -> green -> refactor).
- Do not batch unrelated changes into a single commit.
- Good examples:
  - `add subtree config validator`
  - `fix dirty tree detection for pull command`
  - `refactor git runner error mapping`

## Files and Directories to Avoid Editing

- Do not edit `vendor/`.
- Do not commit generated cache files.
