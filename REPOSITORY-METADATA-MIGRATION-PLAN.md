# Repository Metadata Migration Plan

## Purpose

Migrate subtree configuration from `extra.subtrees` to
`repositories[].composer-subtree-plugin` on `type=path` repository entries.

This migration is intentional and breaking. No backward compatibility layer will
be implemented.

## Target Configuration

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/pcre",
      "composer-subtree-plugin": {
        "remote": "https://github.com/composer/pcre.git",
        "branch": "main",
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

## Behavioral Rules

1. `subtree:add`, `subtree:pull`, and `subtree:push` remain package-agnostic.
2. Commands resolve subtree targets only from path repositories that include
   `composer-subtree-plugin` metadata.
3. Subtree target identity is path-oriented (repository `url` / normalized
   prefix), not package-oriented.
4. `update` hook remains package-aware, but package mapping is computed from
   package install path and path repository metadata.

## Scope

### In Scope

- Config loader/provider migration to repository metadata.
- Command behavior migration to path-based targets.
- `subtree:add` persistence migration.
- Test updates (unit, command, e2e fixtures).
- Planning updates for future update hook implementation.

### Out of Scope

- Update hook implementation itself (not yet in codebase).
- Backward compatibility for `extra.subtrees`.
- Any destructive migration script.

## Implementation Sequence (Strict TDD)

### Phase 1 - Config read model migration

1. Add failing tests for reading subtree targets from `repositories`:
   - include only `type=path` entries with `composer-subtree-plugin` object,
   - validate `remote` and `branch` as required,
   - default `squash=false` when omitted,
   - ignore non-path repos and malformed metadata.
2. Implement minimal loader/provider changes to make tests pass.
3. Refactor names and data structures for path-oriented semantics.

Primary expected edits:
- `src/Config/SubtreeConfigLoader.php`
- `src/Config/SubtreeTargetConfigProvider.php`
- `src/Config/SubtreeConfig.php`
- `tests/Config/SubtreeConfigLoaderTest.php`
- `tests/Config/SubtreeTargetConfigProviderTest.php`

### Phase 2 - Command target resolution migration

1. Add failing command tests proving pull/push resolve targets from repository
   metadata only.
2. Keep `all` default behavior and deterministic ordering.
3. Ensure target names are stable and path-derived in output.

Primary expected edits:
- `src/Command/SubtreePullCommand.php`
- `src/Command/SubtreePushCommand.php`
- `src/Config/SubtreeTargetResolver.php`
- `tests/Command/SubtreePullCommandTest.php`
- `tests/Command/SubtreePushCommandTest.php`
- `tests/Command/SubtreePullCommandExecuteTest.php`
- `tests/Command/SubtreePushCommandExecuteTest.php`

### Phase 3 - `subtree:add` write model migration

1. Add failing tests for manifest updates:
   - ensure a matching `type=path` repo entry exists for prefix,
   - write metadata under `composer-subtree-plugin`,
   - do not write `extra.subtrees`.
2. Implement minimal write-path changes.
3. Refactor for idempotent updates to existing repository entries.

Primary expected edits:
- `src/Command/SubtreeAddCommand.php`
- `src/Config/RepositoriesConfigUpdater.php`
- `tests/Command/SubtreeAddCommandExecuteTest.php`
- `tests/E2E/SubtreeWorkflowE2ETest.php`

### Phase 4 - Cleanup and consistency

1. Remove obsolete assumptions and dead code tied to `extra.subtrees`.
2. Update command help text to reflect repository metadata source.
3. Keep README updates for a dedicated docs pass unless required by tests.

## Future Update Hook Plan (Design Only)

When implemented, update hook behavior should be:

1. Listen on pre-command-run and gate on `update` only.
2. If no package args are passed, do nothing.
3. For each requested package:
   - resolve install path,
   - find matching `type=path` repository entry,
   - require `composer-subtree-plugin` metadata to qualify.
4. Run subtree pull for matched path targets before solver execution.
5. Abort with actionable error if any pre-pull fails.

## Validation and Quality Gates

Run at minimum after each phase:

1. Narrowest relevant PHPUnit test(s).
2. `composer cs`
3. `composer phpstan`
4. `composer test`

Do not relax checks to pass CI.
