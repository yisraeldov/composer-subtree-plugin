<?php

declare(strict_types=1);

namespace ComposerSubtreePlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreCommandRunEvent;
use Composer\Plugin\PluginInterface;
use ComposerSubtreePlugin\Command\SubtreeCommandProvider;
use ComposerSubtreePlugin\Event\UpdateHookSubscriber;

final class Plugin implements PluginInterface, Capable, EventSubscriberInterface
{
    public function __construct(
        private readonly ?UpdateHookSubscriber $updateHookSubscriber = null,
    ) {}

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

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PRE_COMMAND_RUN => 'onPreCommandRun',
        ];
    }

    public function onPreCommandRun(PreCommandRunEvent $event): void
    {
        $this->updateHookSubscriber()->onPreCommandRun($event);
    }

    private function updateHookSubscriber(): UpdateHookSubscriber
    {
        if ($this->updateHookSubscriber instanceof UpdateHookSubscriber) {
            return $this->updateHookSubscriber;
        }

        return new UpdateHookSubscriber();
    }
}
