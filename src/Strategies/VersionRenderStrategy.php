<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Records\Recordable;

final class VersionRenderStrategy implements RenderStrategyInterface
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    public function supports(RenderType $type): bool
    {
        return $type === RenderType::VERSION;
    }

    public function execute(Recordable $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection;

        // Get version from composer.json
        $composerFile = __DIR__.'/../../composer.json';
        $version = 'unknown';

        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $version = $composer['version'] ?? 'unknown';
        }

        $replacements->addReplacement('{{version}}', $version);
        $replacements->addReplacement('{{php_version}}', PHP_VERSION);

        $laravelStatus = $this->laravelBootstrapper !== null && $this->laravelBootstrapper->isBootstrapped()
            ? 'Bootstrapped ✓'
            : 'Not bootstrapped';

        $replacements->addReplacement('{{laravel_status}}', $laravelStatus);

        return $replacements;
    }
}
