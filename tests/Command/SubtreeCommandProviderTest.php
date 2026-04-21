<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreeAddCommand;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use PHPUnit\Framework\TestCase;

final class SubtreeCommandProviderTest extends TestCase
{
    public function testItProvidesSubtreeAddCommand(): void
    {
        $composer = $this->createMock(Composer::class);
        $provider = new SubtreeCommandProvider($composer);

        $commands = $provider->getCommands();

        self::assertCount(1, $commands);
        self::assertInstanceOf(SubtreeAddCommand::class, $commands[0]);
    }
}
