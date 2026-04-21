<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Command;

use Composer\Plugin\Capability\CommandProvider;

final class SubtreeCommandProvider implements CommandProvider
{
    public function getCommands(): array
    {
        return [];
    }
}
