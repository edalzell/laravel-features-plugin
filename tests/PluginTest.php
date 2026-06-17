<?php

use Composer\Package\RootPackage;
use Edalzell\LaravelFeatures\Plugin;

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/plugin-test-'.uniqid();
    mkdir($this->tmpDir, 0755, true);
    $this->originalCwd = getcwd();
    chdir($this->tmpDir);

    $this->package = new RootPackage('test/app', '1.0.0', '1.0.0');
    $this->package->setAutoload(['psr-4' => []]);
    $this->package->setDevAutoload(['psr-4' => []]);

    $this->plugin = makePlugin($this->package);
});

afterEach(function () {
    chdir($this->originalCwd);
    rmRecursive($this->tmpDir);
});

it('subscribes to pre-autoload-dump', function () {
    expect(Plugin::getSubscribedEvents())->toBe(['pre-autoload-dump' => 'setAutoloads']);
});

it('does nothing when there are no feature directories', function () {
    $this->plugin->setAutoloads();

    expect($this->package->getAutoload())->toBe(['psr-4' => []])
        ->and($this->package->getDevAutoload())->toBe(['psr-4' => []]);
});

it('generates psr-4 entries for an app feature', function () {
    mkdir('features/MyFeature', 0755, true);

    $this->plugin->setAutoloads();

    expect($this->package->getAutoload()['psr-4'])
        ->toHaveKey('Features\MyFeature\\', 'features/MyFeature/src')
        ->toHaveKey('Features\MyFeature\Database\Factories\\', 'features/MyFeature/database/factories')
        ->toHaveKey('Features\MyFeature\Database\Seeders\\', 'features/MyFeature/database/seeders');

    expect($this->package->getDevAutoload()['psr-4'])
        ->toHaveKey('Features\MyFeature\Tests\\', 'features/MyFeature/tests');
});

it('generates psr-4 entries for multiple app features', function () {
    mkdir('features/Alpha', 0755, true);
    mkdir('features/Beta', 0755, true);

    $this->plugin->setAutoloads();

    expect($this->package->getAutoload()['psr-4'])
        ->toHaveKey('Features\Alpha\\')
        ->toHaveKey('Features\Beta\\');
});

it('uses the root psr-4 namespace as a prefix for non-app packages', function () {
    $package = new RootPackage('acme/my-pkg', '1.0.0', '1.0.0');
    $package->setAutoload(['psr-4' => ['Acme\MyPkg\\' => 'src/']]);
    $package->setDevAutoload(['psr-4' => []]);
    $plugin = makePlugin($package);

    mkdir('features/MyFeature', 0755, true);
    $plugin->setAutoloads();

    expect($package->getAutoload()['psr-4'])
        ->toHaveKey('Acme\MyPkg\Features\MyFeature\\');
});

it('uses plain Features namespace for app packages', function () {
    $package = new RootPackage('test/app', '1.0.0', '1.0.0');
    $package->setAutoload(['psr-4' => ['App\\' => 'app/']]);
    $package->setDevAutoload(['psr-4' => []]);
    $plugin = makePlugin($package);

    mkdir('features/MyFeature', 0755, true);
    $plugin->setAutoloads();

    expect($package->getAutoload()['psr-4'])
        ->toHaveKey('Features\MyFeature\\');
});

it('generates psr-4 entries for vendor package features', function () {
    mkdir('vendor/author/package/features/MyFeature', 0755, true);
    file_put_contents(
        'vendor/author/package/composer.json',
        json_encode(['autoload' => ['psr-4' => ['Author\Package\\' => 'src/']]])
    );

    $this->plugin->setAutoloads();

    expect($this->package->getAutoload()['psr-4'])
        ->toHaveKey('Author\Package\Features\MyFeature\\', 'vendor/author/package/features/MyFeature/src');
});

it('throws when vendor composer.json is missing', function () {
    mkdir('vendor/author/package/features/MyFeature', 0755, true);

    $this->plugin->setAutoloads();
})->throws(Exception::class);

it('throws when vendor composer.json is missing autoload psr-4', function () {
    mkdir('vendor/author/package/features/MyFeature', 0755, true);
    file_put_contents(
        'vendor/author/package/composer.json',
        json_encode(['name' => 'author/package'])
    );

    $this->plugin->setAutoloads();
})->throws(Exception::class);
