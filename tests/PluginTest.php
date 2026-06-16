<?php

use Edalzell\LaravelFeatures\Plugin;

class TestPlugin extends Plugin
{
    public function addComposerScript(): void
    {
        parent::addComposerScript();
    }
}

$tmpFile = sys_get_temp_dir().'/test-composer.json';

beforeEach(function () use ($tmpFile) {
    putenv("COMPOSER={$tmpFile}");
});

afterEach(function () use ($tmpFile) {
    putenv('COMPOSER');
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }
});

$hook = 'Edalzell\\Features\\Composer\\FeatureNamespaces::add';

it('returns the correct subscribed events', function () {
    expect(Plugin::getSubscribedEvents())->toBe(['pre-update-cmd' => 'addComposerScript']);
});

it('adds the hook when there are no scripts', function () use ($tmpFile, $hook) {
    file_put_contents($tmpFile, json_encode(['name' => 'test/pkg']));
    (new TestPlugin)->addComposerScript();
    $result = json_decode(file_get_contents($tmpFile), true);
    expect($result['scripts']['pre-autoload-dump'])->toContain($hook);
});

it('adds the hook when pre-autoload-dump does not exist', function () use ($tmpFile, $hook) {
    file_put_contents($tmpFile, json_encode(['scripts' => ['post-install-cmd' => []]]));
    (new TestPlugin)->addComposerScript();
    $result = json_decode(file_get_contents($tmpFile), true);
    expect($result['scripts']['pre-autoload-dump'])->toContain($hook);
});

it('adds the hook alongside existing hooks', function () use ($tmpFile, $hook) {
    file_put_contents($tmpFile, json_encode(['scripts' => ['pre-autoload-dump' => ['OtherClass::run']]]));
    (new TestPlugin)->addComposerScript();
    $result = json_decode(file_get_contents($tmpFile), true);
    expect($result['scripts']['pre-autoload-dump'])
        ->toContain($hook)
        ->toContain('OtherClass::run');
});

it('does not add a duplicate hook', function () use ($tmpFile, $hook) {
    file_put_contents($tmpFile, json_encode(['scripts' => ['pre-autoload-dump' => [$hook]]]));
    $originalContents = file_get_contents($tmpFile);
    (new TestPlugin)->addComposerScript();
    expect(file_get_contents($tmpFile))->toBe($originalContents);
});

it('handles pre-autoload-dump as a string', function () use ($tmpFile, $hook) {
    file_put_contents($tmpFile, json_encode(['scripts' => ['pre-autoload-dump' => 'OtherClass::run']]));
    (new TestPlugin)->addComposerScript();
    $result = json_decode(file_get_contents($tmpFile), true);
    expect($result['scripts']['pre-autoload-dump'])->toContain($hook);
});
