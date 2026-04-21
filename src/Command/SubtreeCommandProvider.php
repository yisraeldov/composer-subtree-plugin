<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Plugin\Capability\CommandProvider;
use ComposerSubtreePlugin\Git\GitProcessRunner;

final class SubtreeCommandProvider implements CommandProvider
{
    public function __construct(
        private readonly Composer $composer,
    ) {}

    public function getCommands(): array
    {
        return [
            new SubtreeAddCommand($this->composer, new GitProcessRunner()),
            new SubtreePullCommand($this->composer, new GitProcessRunner()),
            new SubtreePushCommand($this->composer, new GitProcessRunner()),
        ];
    }
}
