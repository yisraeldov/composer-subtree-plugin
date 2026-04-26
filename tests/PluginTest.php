<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests;

use Composer\Plugin\PluginEvents;
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

    public function testItSubscribesToPreCommandRunEvent(): void
    {
        self::assertSame(
            [PluginEvents::PRE_COMMAND_RUN => 'onPreCommandRun'],
            Plugin::getSubscribedEvents(),
        );
    }
}
