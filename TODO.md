# Composer Subtree Plugin - Vertical Slices by Usefulness

Legend:
- [ ] pending
- [~] in progress
- [x] done

## Track A - Fastest path to useful commands (ASAP)

### Slice 1 - Installable plugin skeleton
- [x] Create plugin package wiring (`type: composer-plugin`, PSR-4, `extra.class`).
- [x] Implement minimal `Plugin` + command provider registration.
- [x] Add constraints: `php:^8.2`, `composer-plugin-api:^2.2`.
- [x] DoD: plugin installs and activates without errors.

### Slice 2 - Minimal subtree config loader (happy path)
- [x] Read `extra.subtrees` from root `composer.json`.
- [x] Map entries to minimal runtime config (`name`, `package`, `prefix`, `remote`, `branch`, `squash`).
- [x] Default `squash=false` when omitted.
- [x] DoD: commands can resolve configured subtrees from config.

### Slice 3 - Git execution baseline
- [x] Implement `GitProcessRunner` for command execution + exit code capture.
- [x] Implement uniform exception with command/stderr context.
- [x] DoD: all git calls use one runner with consistent failures.

### Slice 4 - `composer subtree:add` (MVP)
- [ ] Add command: `composer subtree:add <upstream-url> <upstream-branch> [prefix] [--squash]`.
- [ ] Default prefix to `packages/<repo-name>` when omitted.
- [ ] Default `squash=false`.
- [ ] Append subtree entry under `extra.subtrees` in `composer.json`.
- [ ] Run `git subtree add --prefix=<prefix> <remote> <branch>` (with squash flag when set).
- [ ] DoD: one command adds config entry + performs initial subtree add.

### Slice 5 - `composer subtree:pull` single target (MVP)
- [ ] Add command: `composer subtree:pull [name|all]`.
- [ ] Support named subtree pull.
- [ ] Run `git fetch` then `git subtree pull --prefix=... <remote> <branch>`.
- [ ] Respect per-subtree `squash` setting.
- [ ] DoD: `composer subtree:pull <name>` works end-to-end.

### Slice 6 - `composer subtree:push` single target (MVP)
- [ ] Add command: `composer subtree:push [name|all]`.
- [ ] Support named subtree push.
- [ ] Run `git subtree push --prefix=... <remote> <branch>`.
- [ ] DoD: `composer subtree:push <name>` works end-to-end.

### Slice 7 - `all` target behavior for pull/push
- [ ] Make default target `all` for pull and push when no arg is provided.
- [ ] Execute in deterministic order (sorted by logical subtree name).
- [ ] DoD: `composer subtree:pull` and `composer subtree:push` process all reliably.

## Track B - Core usability and visibility

### Slice 8 - `composer subtree:status` base output
- [ ] Add command: `composer subtree:status`.
- [ ] Show `name`, `package`, `prefix`, `remote`, `branch` for all entries.
- [ ] DoD: status exposes complete configured map.

### Slice 9 - `subtree:status` operational indicators
- [ ] Indicate dirty state under each subtree prefix.
- [ ] Indicate whether configured remote appears to exist/reachability check result.
- [ ] DoD: status quickly shows what is safe to sync.

### Slice 10 - Help text + examples for MVP commands
- [ ] Add complete help for `subtree:add`, `subtree:pull`, `subtree:push`, `subtree:status`.
- [ ] Include copy-paste examples in command descriptions.
- [ ] DoD: `composer help subtree:*` is actionable.

## Track C - Update hook behavior

### Slice 11 - Event subscriber wiring
- [ ] Implement `UpdateHookSubscriber` for `pre-command-run`.
- [ ] Detect `composer update` command only.
- [ ] DoD: hook is active and isolated to update flow.

### Slice 12 - Package targeting in update hook
- [ ] Parse requested package args for `composer update <packages...>`.
- [ ] Match requested packages against configured subtree `package` values.
- [ ] Do nothing for bare `composer update` (no package args).
- [ ] DoD: hook decides exactly when pre-pull should run.

### Slice 13 - Pre-pull execution before solver
- [ ] On matching package(s), run subtree pull before normal update continues.
- [ ] Abort update with non-zero exit + actionable message if pre-pull fails.
- [ ] DoD: `composer update <subtree-package>` pre-sync works as specified.

## Track D - Safety and guardrails (after MVP speed)

### Slice 14 - Dirty tree guard
- [ ] Block pull/push when working tree has uncommitted changes.
- [ ] Add `--allow-dirty` override for pull and push.
- [ ] DoD: safety criterion for dirty tree is enforced.

### Slice 15 - Prefix existence guard
- [ ] Fail pull/push with clear message when prefix directory is missing.
- [ ] Include next action in error output.
- [ ] DoD: missing prefix fails fast and clearly.

### Slice 16 - Remote reachability guard
- [ ] Verify remote/branch reachability before pull/push.
- [ ] Fail with actionable message when unreachable.
- [ ] DoD: remote connectivity issues fail early and clearly.

### Slice 17 - No destructive git actions policy
- [ ] Ensure implementation never uses destructive actions (`reset --hard`, forced checkout, force push).
- [ ] Add tests/assertions around command generation where practical.
- [ ] DoD: safety criterion B4 is demonstrably satisfied.

## Track E - Validation and doctor (intentionally later)

### Slice 18 - Strict config validator core
- [ ] Add `SubtreeConfigValidator` with required keys: `package`, `prefix`, `remote`, `branch`.
- [ ] Validate type/shape and emit actionable errors.
- [ ] DoD: invalid config is rejected with precise feedback.

### Slice 19 - Validation semantics
- [ ] Validate prefix exists.
- [ ] Validate subtree package exists in `require` or `require-dev`.
- [ ] Validate `git subtree` support availability.
- [ ] Validate remote URL format and branch reachability.
- [ ] DoD: semantic validation catches misconfiguration before sync.

### Slice 20 - `composer subtree:doctor`
- [ ] Add command: `composer subtree:doctor`.
- [ ] Run schema + semantic validation and print concise report.
- [ ] Exit `0` on valid config; non-zero on invalid.
- [ ] DoD: doctor command satisfies acceptance criterion A1.

## Track F - Options and refinements

### Slice 21 - Push dry-run
- [ ] Add `--dry-run` to `subtree:push`.
- [ ] Print intended actions without mutating repo/remotes.
- [ ] DoD: dry-run is safe and predictable.

### Slice 22 - Output and DX polish
- [ ] Standardize all error messages to include immediate next action.
- [ ] Improve command output formatting consistency.
- [ ] DoD: DX criteria for helpful errors/help text are met.

## Track G - Compatibility and CI

### Slice 23 - Composer API compatibility hardening
- [ ] Guard Composer API differences with runtime feature detection where needed.
- [ ] Verify behavior on Composer 2.2 and latest 2.x.
- [ ] DoD: plugin runs on supported Composer range.

### Slice 24 - PHP compatibility checks
- [ ] Validate on PHP 8.2, 8.3, 8.4 where supported.
- [ ] Fix any version-specific issues.
- [ ] DoD: runtime compatibility matrix holds.

### Slice 25 - CI matrix implementation
- [ ] Add jobs:
  - PHP 8.2 + Composer 2.2.x
  - PHP 8.3 + Composer 2.2.x
  - PHP 8.2 + Composer latest 2.x
  - PHP 8.3 + Composer latest 2.x
  - PHP 8.4 + Composer latest 2.x
- [ ] Run static checks, unit tests, integration tests in each job.
- [ ] DoD: minimum CI matrix from plan is green.

## Track H - Open source readiness and release

### Slice 26 - README installation and setup
- [ ] Document install, `allow-plugins`, and path repository setup.
- [ ] Provide full `extra.subtrees` configuration examples.
- [ ] DoD: README supports copy-paste onboarding.

### Slice 27 - README command and update-hook examples
- [ ] Add examples for add/status/pull/push/doctor.
- [ ] Add `composer update <subtree-package>` behavior examples.
- [ ] DoD: command usage is easy to follow.

### Slice 28 - Troubleshooting and policy docs
- [ ] Document common failures and fixes (dirty tree, missing prefix, unreachable remote).
- [ ] Document semver policy and support policy.
- [ ] Publish compatibility matrix and any exclusions.
- [ ] DoD: project docs are release-candidate ready.

### Slice 29 - Acceptance and release gate
- [ ] Verify all acceptance criteria A-F with tests and checklist.
- [ ] Ensure unit coverage: config parsing, validation, package matching, option parsing.
- [ ] Ensure integration coverage: real subtree pull/push flows in temp repos.
- [ ] Tag and publish first stable release with changelog when criteria are met.
- [ ] DoD: plan acceptance criteria are fully satisfied.

---

## Quick-start recommended implementation order
- [ ] 1 -> 7 first (get `subtree:add`, `subtree:pull`, `subtree:push` working ASAP).
- [ ] Then 8 -> 13 (status + update-hook value).
- [ ] Then 14 -> 20 (safety + doctor).
- [ ] Then 21 -> 29 (polish, compatibility, CI, docs, release).
