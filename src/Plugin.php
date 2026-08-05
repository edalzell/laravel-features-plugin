<?php

namespace Edalzell\LaravelFeatures;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Exception;

class Plugin implements EventSubscriberInterface, PluginInterface
{
    /** @var array<string, array<string>|string> */
    private array $autoload = [];

    /** @var array<string, array<string>|string> */
    private array $autoloadDev = [];

    private RootPackageInterface $package;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->package = $composer->getPackage();
        $this->autoload = $this->package->getAutoload();
        $this->autoloadDev = $this->package->getDevAutoload();
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::PRE_AUTOLOAD_DUMP => 'setAutoloads'];
    }

    public function setAutoloads(): void
    {
        $this
            ->autoloadFeatures()
            ->autoloadPackageFeatures();

        $this->package->setAutoload($this->autoload);
        $this->package->setDevAutoload($this->autoloadDev);
    }

    private function autoloadFeatures(): self
    {
        if ($featurePaths = $this->featurePaths('features')) {
            $this->generateNamespaces($this->featuresNamespace(), $featurePaths);
        }

        return $this->autoloadConfiguredFeatures();
    }

    /**
     * Autoload features from any additional directories declared in the root
     * package's `extra.laravel-features.paths`, keyed by path => namespace.
     */
    private function autoloadConfiguredFeatures(): self
    {
        $paths = $this->package->getExtra()['laravel-features']['paths'] ?? [];

        if (! is_array($paths)) {
            throw new Exception('extra.laravel-features.paths must be an object of path => namespace.');
        }

        foreach ($paths as $path => $namespace) {
            if (! is_string($path) || ! is_string($namespace)) {
                throw new Exception(
                    'extra.laravel-features.paths must map a path to a namespace, e.g. {"../../shared/features": "Shared\\\\Features"}.'
                );
            }

            if (empty($featurePaths = $this->featurePaths($path))) {
                continue;
            }

            $this->generateNamespaces(rtrim($namespace, '\\'), $featurePaths);
        }

        return $this;
    }

    private function autoloadPackageFeatures(): self
    {
        if (empty($featurePaths = $this->featurePaths('vendor/*/*/features'))) {
            return $this;
        }

        $this->generateNamespaces(
            $this->featuresNamespace($featurePaths),
            $featurePaths
        );

        return $this;
    }

    /** @return array<int, string> */
    private function featurePaths(string $path): array
    {
        if (empty($paths = glob(getcwd().'/'.$path.'/*'))) {
            return [];
        }

        return array_filter($paths, 'is_dir');
    }

    /** @param array<int, string> $featurePaths */
    private function generateNamespaces(string $namespace, array $featurePaths): void
    {
        $cwd = str_replace('\\', '/', getcwd());

        foreach ($featurePaths as $path) {
            $normalizedPath = str_replace('\\', '/', $path);
            $featureName = basename($normalizedPath);
            $featurePath = ltrim(str_replace($cwd, '', $normalizedPath), '/');

            $rootNamespace = "{$namespace}\\{$featureName}\\";
            $dbRootNamespace = $rootNamespace.'Database\\';
            $rootPath = "{$featurePath}/src";

            $this->autoload['psr-4'][$rootNamespace] = $rootPath;

            $factoryPath = "{$featurePath}/database/factories";
            $seedersPath = "{$featurePath}/database/seeders";

            $this->autoload['psr-4'][$dbRootNamespace.'Factories\\'] = $factoryPath;
            $this->autoload['psr-4'][$dbRootNamespace.'Seeders\\'] = $seedersPath;

            $this->autoloadDev['psr-4'][$rootNamespace.'Tests\\'] = "{$featurePath}/tests";
        }

    }

    /** @param array<int, string> $featurePaths */
    private function featuresNamespace(array $featurePaths = []): string
    {
        if (empty($featurePaths)) {
            // When the root package has its own PSR-4 namespace (i.e. it's a package,
            // not a Laravel app), prefix Features with that namespace so local feature
            // classes resolve correctly during package development.
            $rootPsr4 = array_key_first($this->autoload['psr-4'] ?? []);

            if ($rootPsr4 && $rootPsr4 !== 'App\\') {
                return rtrim($rootPsr4, '\\').'\\Features';
            }

            return 'Features';
        }

        $composerPath = $this->getComposerPath($featurePaths[0]);
        if (! is_readable($composerPath)) {
            throw new Exception("Cannot read composer.json at {$composerPath}");
        }

        $contents = file_get_contents($composerPath);

        $composer = json_decode($contents, true);

        if (! is_array($composer) || ! isset($composer['autoload']['psr-4'])) {
            throw new Exception("composer.json at {$composerPath} is missing autoload.psr-4");
        }

        return array_key_first($composer['autoload']['psr-4']).'Features';
    }

    private function getComposerPath(string $featurePath): string
    {
        return dirname($featurePath, 2).'/composer.json';
    }
}
