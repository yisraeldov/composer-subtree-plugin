<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Composer;
use Composer\Plugin\Capability\CommandProvider;
use ComposerSubtreePlugin\Git\GitProcessRunner;

final class SubtreeCommandProvider implements CommandProvider
{
    private readonly Composer $composer;

    /**
     * @param array<string, mixed> $capabilityArgs
     */
    public function __construct(
        array $capabilityArgs,
    ) {
        $composer = $capabilityArgs['composer'] ?? null;

        if (!$composer instanceof Composer) {
            throw new \InvalidArgumentException(
                'Command provider requires a Composer '
                . 'instance in capability args.',
            );
        }

        $this->composer = $composer;
    }

    public function getCommands(): array
    {
        return [
            new SubtreeAddCommand($this->composer, new GitProcessRunner()),
            new SubtreePullCommand($this->composer, new GitProcessRunner()),
            new SubtreePushCommand($this->composer, new GitProcessRunner()),
        ];
    }
}
