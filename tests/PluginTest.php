<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use ComposerSubtreePlugin\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testItRegistersTheCommandProviderCapability(): void
    {
        $plugin = new Plugin();

        self::assertSame(
            [CommandProviderCapability::class => SubtreeCommandProvider::class],
            $plugin->getCapabilities(),
        );
    }
}
