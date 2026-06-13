<?php

// src/Services/DirectiveDiscoveryService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contexts\DirectiveDiscoveryContext;
use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use Illuminate\Foundation\Application;

/**
 * Service responsible for discovering directives from the filesystem and vendor packages.
 */
class DirectiveDiscoveryService implements DirectiveLoaderInterface
{
    public function __construct(
        private readonly DirectiveConfigInterface $config,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveDiscoveryContext $context,
        private readonly ?Application $application = null,
        ?DirectiveLoaderInterface $loader = null,
    ) {
        $this->context->setLoader($loader ?? $this);
    }

    public function discover(): DirectiveMetadataCollection
    {
        return $this->context->getLoader()->load();
    }

    public function load(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection();
        $path = $this->config->directivesPath();

        if ($path !== '' && is_dir($path)) {
            $this->scanDirectoryForDirectives($results, $path);
        }

        $this->discoverFromVendorPackages($results);

        return $results;
    }

    private function discoverFromVendorPackages(DirectiveMetadataCollection $results): void
    {
        $composerFile = $this->context->getProjectRoot() . '/composer.json';

        if (!file_exists($composerFile)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        $rootPackages = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        $this->context->resetScannedPackages();

        foreach ($rootPackages as $packageName => $version) {
            $this->scanPackage($results, $packageName, 1);
        }
    }

    private function scanPackage(DirectiveMetadataCollection $results, string $packageName, int $depth): void
    {
        if ($this->context->isPackageScanned($packageName) || $depth > 2) {
            return;
        }

        if (str_starts_with($packageName, 'php') || $packageName === 'php') {
            return;
        }

        $packagePath = $this->context->getVendorDir() . '/' . $packageName;

        if (!is_dir($packagePath)) {
            return;
        }

        $this->context->markPackageAsScanned($packageName);
        $this->scanPackageDirectories($results, $packagePath);

        if ($depth === 1) {
            $this->scanPackageDependencies($results, $packagePath, $depth);
        }
    }

    private function scanPackageDependencies(DirectiveMetadataCollection $results, string $packagePath, int $currentDepth): void
    {
        $composerFile = $packagePath . '/composer.json';

        if (!file_exists($composerFile)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        if ($composer === null) {
            return;
        }

        $dependencies = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        foreach ($dependencies as $dependencyName => $version) {
            $this->scanPackage($results, $dependencyName, $currentDepth + 1);
        }
    }

    private function scanPackageDirectories(DirectiveMetadataCollection $results, string $packagePath): void
    {
        $possiblePaths = [
            $packagePath . '/src/Directives',
            $packagePath . '/Directives',
            $packagePath . '/src/Directive',
            $packagePath . '/Directive',
        ];

        foreach ($possiblePaths as $directivesPath) {
            if (is_dir($directivesPath)) {
                $this->scanDirectoryForDirectives($results, $directivesPath);
            }
        }
    }

    private function scanDirectoryForDirectives(DirectiveMetadataCollection $results, string $directory): void
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $metadata = $this->extractMetadataFromFile($file);
            if ($metadata !== null && !$this->isAlreadyRegistered($results, $metadata->signature)) {
                $results->add($metadata);
            }
        }
    }

    private function isAlreadyRegistered(DirectiveMetadataCollection $results, string $signature): bool
    {
        foreach ($results as $existing) {
            if ($existing->signature === $signature || $existing->aliases->contains($signature)) {
                return true;
            }
        }

        return false;
    }

    private function extractMetadataFromFile(string $file): ?DirectiveMetadataRecord
    {
        $class = $this->getClassFromFile($file);

        return ($class !== '' && class_exists($class)) ? $this->extractMetadataFromClass($class) : null;
    }

    private function extractMetadataFromClass(string $class): ?DirectiveMetadataRecord
    {
        $reflection = new \ReflectionClass($class);

        if (
            $reflection->isAbstract() ||
            !is_subclass_of($class, AbstractDirective::class) ||
            !is_subclass_of($class, DirectiveInterface::class)
        ) {
            return null;
        }

        try {
            $blueprint = $this->hydrator->hydrateBlueprint($class);
            $directive = $this->hydrator->hydrateForAliases($class);

            return new DirectiveMetadataRecord(
                signature: $blueprint->signature,
                class: $blueprint->class,
                description: $blueprint->description,
                aliases: $directive->getAliases(),
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getClassFromFile(string $file): string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        preg_match('/namespace\s+([^;]+);/', $content, $match);
        $namespace = $match[1] ?? '';
        $class = basename($file, '.php');

        return $namespace === '' ? $class : $namespace . '\\' . $class;
    }
}
