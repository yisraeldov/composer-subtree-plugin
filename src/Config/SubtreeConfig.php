<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Config;

final readonly class SubtreeConfig
{
    public function __construct(
        private string $name,
        private string $package,
        private string $prefix,
        private string $remote,
        private string $branch,
        private bool $squash = false,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function package(): string
    {
        return $this->package;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function remote(): string
    {
        return $this->remote;
    }

    public function branch(): string
    {
        return $this->branch;
    }

    public function squash(): bool
    {
        return $this->squash;
    }
}
