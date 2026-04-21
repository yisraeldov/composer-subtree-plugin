<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\PluginInterface;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;

final class Plugin implements PluginInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    /**
     * @return array<class-string, class-string>
     */
    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => SubtreeCommandProvider::class,
        ];
    }
}
