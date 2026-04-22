# Composer Subtree Plugin Plan

> Historical planning document.
>
> The active architecture decision is to store subtree metadata on
> `repositories[].composer-subtree-plugin` for `type=path` entries.
>
> Use `REPOSITORY-METADATA-MIGRATION-PLAN.md` as the implementation source of
> truth for the current migration work.

## Goal

Create an open-source Composer plugin (new repository) that makes Git subtree
workflows first-class for PHP projects, with a safe "hands-on-rails" developer
experience.

Core outcomes:

1. `composer subtree:pull` / `composer subtree:push` commands.
2. `composer update <path-subtree-package>` automatically performs `git subtree pull`
   before Composer dependency resolution.
3. Support the Composer version family Composer
   v2, including current stable releases.

## Important Technical Constraint (Must Be Accepted)


- standard `repositories[].type = "path"` for package resolution,
- plugin config in `repositories[].composer-subtree-plugin` on `type=path` entries,
- command/event hooks for subtree pull/push automation.


---

## Plugin Scope

### In Scope

- Commands:
  - `composer subtree:status`
  - `composer subtree:pull [name|all]`
  - `composer subtree:push [name|all]`
  - `composer subtree:doctor`
- Update hook:
  - `composer update <package>` triggers subtree pull when the target package is
    located under a `type=path` repository entry that includes
    `composer-subtree-plugin` metadata, then proceeds with normal update.
- Config-driven path repository map.
- Safety checks (dirty tree, missing remotes, invalid prefix).
- Compatibility with Composer 2.2+ and latest Composer 2.x.

### Out of Scope


- Automatic PR creation or upstream repository governance.
- Composer core patching/forking.

---

## Configuration Contract

Consumer project (`composer.json`) uses path repositories with plugin metadata on
each subtree-enabled repository entry.

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/payroll",
      "composer-subtree-plugin": {
        "remote": "git@github.com:Behavior-Analyst-Professional-Services/hoopla-payroll.git",
        "branch": "master",
        "squash": false
      },
      "options": {
        "symlink": true,
        "reference": "config"
      }
    }
  ]
}
```

Rules:

- `url` is the local subtree folder (prefix) used by commands.
- `remote` and `branch` define upstream sync target.
- `squash` controls pull behavior per subtree.

---

## UX Contract

### Commands

- `composer subtree:add <upstream url> <upstream branch> [prefix] --squash`
  - updates `composer.json.repositories` for the matching path repository entry
  - persists subtree config values under `composer-subtree-plugin`: `remote`, `branch`, `squash`
  - Runs `get subtree add --prefix=....`
  - squash defaults to false 
  - default prefix is `packages/<repo-name>`

- `composer subtree:status`
  - shows each subtree target (path), remote, branch,
  - indicates local dirty state under prefix,
  - indicates whether configured remote exists.

- `composer subtree:pull [name|all] `
  - default target: `all`,
  - fails on dirty working tree,
  - runs `git fetch` + `git subtree pull --prefix=... ...`.

- `composer subtree:push [name|all]`
  - default target: `all`,
  - fails on dirty tree,
  - pushes subtree changes to upstream branch.

- `composer subtree:doctor` (future)
  - validates config schema,
  - validates prefix exists,
  - validates subtree-enabled prefix contains a valid package composer.json,
  - validates `git subtree` support is available,
  - validates remote URL format and branch reachability.

### `composer update` integration

- On `composer update`, plugin inspects requested package list.
- If one or more requested packages resolve to path repositories with
  `composer-subtree-plugin` metadata, plugin performs subtree pull for those
  entries before update continues.
- If `composer update` runs with no explicit package list, plugin does nothing
  by default (safer, predictable).

---

## Architecture

### Package Layout (new repository)

```text
composer-subtree-plugin/
  src/
    Plugin.php
    CommandProvider.php
    Command/
      SubtreeStatusCommand.php
      SubtreePullCommand.php
      SubtreePushCommand.php
      SubtreeDoctorCommand.php
    Config/
      SubtreeConfig.php
      SubtreeConfigLoader.php
      SubtreeConfigValidator.php
    Git/
      GitProcessRunner.php
      SubtreeService.php
    Event/
      UpdateHookSubscriber.php
  tests/
    Unit/
    Integration/
  composer.json
  README.md
  LICENSE
```

### Core Components

- `Plugin`: implements `PluginInterface`, `Capable`, and `EventSubscriberInterface`.
- `CommandProvider`: registers `subtree:*` commands.
- `SubtreeConfigLoader`: reads and normalizes subtree-enabled path repositories.
- `SubtreeConfigValidator`: schema and semantic validation.
- `SubtreeService`: high-level pull/push/status operations.
- `GitProcessRunner`: shell execution with safe quoting and consistent error
  handling.
- `UpdateHookSubscriber`: handles pre-command-run update interception.

### Compatibility Design

- Composer constraint: `"composer-plugin-api": "^2.2"`.
- PHP constraint for plugin package: `"php": "^8.2"`.
- Target runtime parity with this project (`hoopla-backend`): PHP 8.2+ and
  Composer v2.
- Avoid using Composer internal APIs added after 2.2 unless guarded.
- Runtime feature detection (`method_exists`) for input mutation paths.

---

## Implementation Plan (Exact)

## Phase 1 - Repository Bootstrap

1. Create new plugin repository.
2. Add `composer.json` with:
   - `type: composer-plugin`
   - `require: composer-plugin-api ^2.2`
   - `autoload.psr-4`
   - `extra.class` pointing to plugin entry class
3. Add MIT license, README skeleton, CI skeleton.

Deliverable: installable empty plugin package.

## Phase 2 - Config Model and Validation

1. Implement loader for subtree-enabled path repository entries.
2. Implement strict validation:
   - required keys: `remote`, `branch`
   - optional keys: `squash`
3. Normalize logical name, defaults, and booleans.
4. Add clear actionable validation errors.

Deliverable: `composer subtree:doctor` can validate config.

## Phase 3 - Git Execution Layer

1. Implement process runner.
2. Implement commands:
   - status checks,
   - dirty tree detection,
   - remote verification,
   - pull and push operations.
3. Add dry-run support for push.

Deliverable: deterministic git operations with consistent error handling.

## Phase 4 - CLI Commands

1. Implement `subtree:status`.
2. Implement `subtree:pull`.
3. Implement `subtree:push`.
4. Implement `subtree:doctor`.
5. Add command help text and examples.

Deliverable: all public command UX available.

## Phase 5 - `composer update` Hook

1. Subscribe to `pre-command-run`.
2. Detect `update` command.
3. Resolve targeted package arguments.
4. If targeted package resolves to subtree-enabled path repo(s), run pull first.
5. Continue update flow unchanged after successful pull.

Deliverable: `composer update <path-subtree-package>` pre-sync behavior.


---

## Exact Acceptance Criteria

The project is accepted only when all criteria below pass.

### A. Functional Criteria

1. `composer subtree:doctor` exits `0` for valid config and non-zero for invalid
   config.
2. `composer subtree:status` lists every configured subtree with path, remote,
   and branch.
3. `composer subtree:pull payroll` executes equivalent of
   `git subtree pull --prefix=packages/payroll <remote> <branch>` and exits `0`
   on success.
4. `composer subtree:push payroll` executes equivalent of
   `git subtree push --prefix=packages/payroll <remote> <branch>` and exits `0`
   on success.
5. `composer subtree:pull all` processes all configured subtrees in deterministic
   order (sorted by logical name).

### B. Safety Criteria

1. Pull/push fails with clear message when repository has uncommitted changes,
   unless `--allow-dirty` is passed.
2. Pull/push fails with clear message if prefix directory is missing.
3. Pull/push fails with clear message if remote is unreachable.
4. No command performs destructive git actions (`reset --hard`, forced checkout,
   force push).

### C. Update Hook Criteria

1. For a package located under a subtree-enabled path repository, running
   `composer update <that-package>` triggers subtree pull before solver runs.
2. Running `composer update` with no package args does not auto-pull by default.
3. If pre-pull fails, update aborts with non-zero exit and actionable error.

### D. Compatibility Criteria

1. Plugin installs and activates with Composer 2.2 LTS.
2. Plugin installs and activates with latest Composer 2.x.
4. All command tests pass on supported matrix:
   - Composer 2.2 + PHP 8.2 and 8.3
   - Composer latest 2.x + PHP 8.2, 8.3, and 8.4
5. README documents any known matrix exclusions (if any).

### E. DX Criteria

1. README includes copy-paste setup for consumer projects.
2. README includes command examples for all subcommands.
3. Error messages always include next action (example command to run).
4. `composer help subtree:pull` and `composer help subtree:push` provide
   complete option descriptions.

### F. Quality Criteria

1. Unit tests cover config parsing, validation, package-to-path matching, and command
   option parsing.
2. Integration tests verify real git subtree flows in temporary repos.
3. CI is green on all compatibility jobs.
4. First tagged stable release is published with changelog.

---

## CI Matrix (Minimum)

- `job_1`: PHP 8.2 + Composer 2.2.x
- `job_2`: PHP 8.3 + Composer 2.2.x
- `job_3`: PHP 8.2 + Composer latest 2.x
- `job_4`: PHP 8.3 + Composer latest 2.x
- `job_5`: PHP 8.4 + Composer latest 2.x

Each job runs:

1. static checks,
2. unit tests,
3. integration tests (subtree pull/push in fixture repos).

---

## Release Strategy

- `v0.x`: early adopters, API may change.
- `v1.0.0`: all acceptance criteria satisfied.
- Semantic versioning:
  - major: breaking config/command changes,
  - minor: new backward-compatible commands/options,
  - patch: bug fixes.

Support policy:

- Active support for latest Composer 2.x.
- Best-effort support for Composer 2.2 LTS.
- No Composer 1 support.

---

## Consumer Project Adoption Checklist

1. Convert private VCS packages to subtree folders under `packages/*`.
2. Configure path repository in `repositories`.
3. Add plugin package dependency.
4. Enable plugin in Composer 2.2+ `allow-plugins`.
5. Add `composer-subtree-plugin` metadata to subtree-enabled path repository entries.
6. Run `composer subtree:doctor`.
7. Run `composer subtree:status`.
8. Validate `composer update <path-subtree-package>` behavior.
