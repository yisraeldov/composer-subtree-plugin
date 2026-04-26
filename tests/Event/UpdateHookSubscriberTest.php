<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Tests\Event;

use Composer\Plugin\PreCommandRunEvent;
use ComposerSubtreePlugin\Event\UpdateHookSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;

final class UpdateHookSubscriberTest extends TestCase
{
    public function testItDetectsComposerUpdateCommand(): void
    {
        $subscriber = new UpdateHookSubscriber();
        $event = $this->createPreCommandRunEvent('update');

        self::assertTrue($subscriber->shouldRunFor($event));
    }

    public function testItIgnoresNonUpdateCommands(): void
    {
        $subscriber = new UpdateHookSubscriber();
        $event = $this->createPreCommandRunEvent('install');

        self::assertFalse($subscriber->shouldRunFor($event));
    }

    private function createPreCommandRunEvent(string $command): PreCommandRunEvent
    {
        return new PreCommandRunEvent(
            'pre-command-run',
            $this->createMock(InputInterface::class),
            $command,
        );
    }
}
