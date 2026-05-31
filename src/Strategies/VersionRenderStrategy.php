<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class VersionRenderStrategy implements RenderStrategyInterface
{
    private const PACKAGE_NAME = 'andydefer/laravel-directive';

    public function supports(RenderType $type): bool
    {
        return $type === RenderType::VERSION;
    }

    public function execute(AbstractRecord $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection;

        // Get package version from composer.json
        $version = $this->getPackageVersion();

        // Get Laravel version from composer.json
        $laravelVersion = $this->getLaravelVersion();

        $replacements->addReplacement('{{version}}', $version);
        $replacements->addReplacement('{{php_version}}', PHP_VERSION);
        $replacements->addReplacement('{{laravel_version}}', $laravelVersion);

        return $replacements;
    }

    /**
     * Get the package version from composer.json
     */
    private function getPackageVersion(): string
    {
        $composerFile = getcwd().'/composer.json';

        if (! file_exists($composerFile)) {
            return 'unknown';
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        if ($composer === null) {
            return 'unknown';
        }

        // Chercher dans require
        if (isset($composer['require'][self::PACKAGE_NAME])) {
            return $this->normalizeVersion($composer['require'][self::PACKAGE_NAME]);
        }

        // Chercher dans require-dev
        if (isset($composer['require-dev'][self::PACKAGE_NAME])) {
            return $this->normalizeVersion($composer['require-dev'][self::PACKAGE_NAME]);
        }

        return 'unknown';
    }

    /**
     * Get Laravel version from composer.json
     */
    private function getLaravelVersion(): string
    {
        $composerFile = getcwd().'/composer.json';

        if (! file_exists($composerFile)) {
            return 'unknown';
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        if ($composer === null) {
            return 'unknown';
        }

        // Chercher laravel/framework dans require
        if (isset($composer['require']['laravel/framework'])) {
            return $this->normalizeVersion($composer['require']['laravel/framework']);
        }

        return 'unknown';
    }

    /**
     * Normalize version string (remove ^, ~, etc.)
     */
    private function normalizeVersion(string $version): string
    {
        // Remove caret ^, tilde ~, greater than >, etc.
        $version = ltrim($version, '^~><=');

        // Handle dev-* branches
        if (str_starts_with($version, 'dev-')) {
            return substr($version, 4);
        }

        // Handle aliases like "v1.0.0 as 1.1.0"
        if (str_contains($version, ' as ')) {
            $parts = explode(' as ', $version);
            $version = end($parts);
        }

        return $version;
    }
}
