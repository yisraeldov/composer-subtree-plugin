<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Command;

use Composer\Composer;
use ComposerSubtreePlugin\Command\SubtreeStatusCommand;
use ComposerSubtreePlugin\Git\GitProcessRunner;
use PHPUnit\Framework\TestCase;

final class SubtreeStatusCommandTest extends TestCase
{
    public function testItHasCorrectName(): void
    {
        $command = new SubtreeStatusCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );

        self::assertSame('subtree:status', $command->getName());
    }

    public function testItProvidesActionableHelpExamples(): void
    {
        $command = new SubtreeStatusCommand(
            $this->createMock(Composer::class),
            $this->createMock(GitProcessRunner::class),
        );

        self::assertStringContainsString(
            'composer subtree:status',
            $command->getHelp(),
        );
    }
}
