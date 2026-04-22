# composer-subtree-plugin

A Composer plugin that adds practical Git subtree commands to your PHP project.

Implemented today:
- `composer subtree:add`
- `composer subtree:pull`
- `composer subtree:push`

## What this project is

`composer-subtree-plugin` helps you manage subtree-based package workflows from Composer commands.
It stores subtree metadata on `type=path` repository entries in
`composer.json.repositories[].composer-subtree-plugin` and uses those
definitions for pull/push operations.

## Why use this

This plugin is useful when your team has internal libraries that were previously pulled in through Composer `vcs` repositories, and that setup is causing friction in CI and deployment.

Common pain points with `vcs` internal dependencies:
- CI and deploy jobs depend on external repository availability and auth at install time.
- Build environments need extra SSH/token setup just to resolve private package sources.
- Reproducibility can become harder when dependency resolution and Git access are tightly coupled.

How this plugin helps:
- You vendor internal libraries as Git subtrees directly in your main repository.
- Composer installs from local code paths already present in the checkout.
- CI and deploy pipelines become simpler because subtree sync is an explicit step (`subtree:pull` / `subtree:push`) instead of an implicit runtime dependency fetch.

## Requirements

- PHP `^8.2`
- Composer plugin API `^2.2`
- `git` with `git subtree` available

## Installation

Install in the consumer project:

```bash
composer require yisraeldov/composer-subtree-plugin --dev
```

If your Composer setup requires explicit plugin approval, add:

```json
{
  "config": {
    "allow-plugins": {
      "yisraeldov/composer-subtree-plugin": true
    }
  }
}
```

## Configuration format

Subtrees are configured on `type=path` repository entries:

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
      }
    }
  ]
}
```

Fields:
- `url` (string): local subtree directory (path repository URL)
- `remote` (string): upstream repository URL
- `branch` (string): upstream branch
- `squash` (bool, optional): whether pull/add uses `--squash` (defaults to `false`)

## Day-to-day usage

### 1) Add a new subtree

```bash
composer subtree:add <upstream-url> <upstream-branch> [prefix] [--squash]
```

Example:

```bash
composer subtree:add https://github.com/composer/pcre.git main
```

What it does:
1. Runs `git subtree add --prefix=... <remote> <branch> [--squash]`
2. Ensures `composer.json.repositories` contains a path repository entry for the subtree prefix
3. Writes/updates subtree metadata at `repositories[].composer-subtree-plugin`

Notes:
- If `prefix` is omitted, default is `packages/<repo-name>`.
- Target selection is path-based (for example `packages/pcre`) or `all`.
- If the git command fails, config is not persisted.

### 2) Pull subtree updates

```bash
composer subtree:pull [target]
```

- `target` can be a subtree path target (for example `packages/pcre`) or `all`.
- If omitted, it behaves like `all`.

Examples:

```bash
composer subtree:pull packages/pcre
composer subtree:pull all
composer subtree:pull
```

What it does per subtree:
1. `git fetch <remote> <branch>`
2. `git subtree pull --prefix=<prefix> <remote> <branch> [--squash]`

### 3) Push subtree updates

```bash
composer subtree:push [target]
```

- `target` can be a subtree path target or `all`.
- If omitted, it behaves like `all`.

Examples:

```bash
composer subtree:push packages/pcre
composer subtree:push all
composer subtree:push
```

What it does per subtree:
- `git subtree push --prefix=<prefix> <remote> <branch>`

## Current scope (implemented)

This plugin currently provides:
- Add subtree + persist subtree config
- Pull one/all configured subtrees
- Push one/all configured subtrees

No other subtree commands or update hooks are documented here because they are not implemented yet.
