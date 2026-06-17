<?php

use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Package\RootPackage;
use Edalzell\LaravelFeatures\Plugin;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class);

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
function makePlugin(RootPackage $package): Plugin
{
    $composer = new Composer;
    $composer->setPackage($package);

    $plugin = new Plugin;
    $plugin->activate($composer, new NullIO);

    return $plugin;
}

function rmRecursive(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/*') ?: [] as $file) {
        is_dir($file) ? rmRecursive($file) : unlink($file);
    }

    rmdir($dir);
}
