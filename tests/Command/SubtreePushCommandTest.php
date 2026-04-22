<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreePushCommand;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;

final class SubtreePushCommandTest extends TestCase
{
    public function testItHasCorrectName(): void
    {
        $command = new SubtreePushCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );

        self::assertSame('subtree:push', $command->getName());
    }

    public function testItHasOptionalTargetArgument(): void
    {
        $command = new SubtreePushCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasArgument('target'));
        self::assertFalse($definition->getArgument('target')->isRequired());
        self::assertSame(
            'Subtree path target or all',
            $definition->getArgument('target')->getDescription(),
        );
    }

    public function testItProvidesActionableHelpExamples(): void
    {
        $command = new SubtreePushCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );

        self::assertStringContainsString(
            'composer subtree:push',
            $command->getHelp(),
        );
    }
}
