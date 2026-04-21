<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Git;

final readonly class GitProcessResult
{
    public function __construct(
        private int $exitCode,
        private string $stdout,
        private string $stderr,
    ) {}

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }

    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }
}
