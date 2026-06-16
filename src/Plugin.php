<?php

namespace Edalzell\LaravelFeatures;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonManipulator;
use Composer\Plugin\PluginInterface;

class Plugin implements EventSubscriberInterface, PluginInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'pre-update-cmd' => 'addComposerScript',
        ];
    }

    public function activate(Composer $composer, IOInterface $io) {}

    public function deactivate(Composer $composer, IOInterface $io) {}

    public function uninstall(Composer $composer, IOInterface $io) {}

    protected function addComposerScript(): void
    {
        $hook = 'Edalzell\\Features\\Composer\\FeatureNamespaces::add';
        $path = Factory::getComposerFile();
        $contents = file_get_contents($path);
        $json = json_decode($contents, true);

        $hooks = (array) ($json['scripts']['pre-autoload-dump'] ?? []);

        if (in_array($hook, $hooks)) {
            return;
        }

        $hooks[] = $hook;

        $manipulator = new JsonManipulator($contents);
        $manipulator->addSubNode('scripts', 'pre-autoload-dump', $hooks);

        file_put_contents($path, $manipulator->getContents());
    }
}
