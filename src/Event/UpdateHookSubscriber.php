<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Event;

use Composer\Plugin\PreCommandRunEvent;

final class UpdateHookSubscriber
{
    private const UPDATE_COMMAND = 'update';

    public function onPreCommandRun(PreCommandRunEvent $event): void
    {
        if (!$this->shouldRunFor($event)) {
            return;
        }
    }

    public function shouldRunFor(PreCommandRunEvent $event): bool
    {
        return $event->getCommand() === self::UPDATE_COMMAND;
    }
}
