<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin\Event;

use Composer\Plugin\PreCommandRunEvent;

final class UpdateHookSubscriber
{
    public function onPreCommandRun(PreCommandRunEvent $event): void
    {
        if (!$this->shouldRunFor($event)) {
            return;
        }
    }

    public function shouldRunFor(PreCommandRunEvent $event): bool
    {
        return $event->getCommand() === 'update';
    }
}
