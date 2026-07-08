<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\Directive\Contracts\Services\DependencyResolverInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use RuntimeException;

/**
 * Resolves and analyzes Composer package dependencies.
 *
 * This service builds dependency trees, detects circular dependencies,
 * and provides flattened lists of all package dependencies.
 */
final class DependencyResolverService implements DependencyResolverInterface
{
    /**
     * The resolved package data cache.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $resolved = [];

    /**
     * The visited packages during resolution.
     *
     * @var array<int, string>
     */
    private array $visited = [];

    /**
     * @param  ComposerReaderInterface  $composerReader  The Composer reader service
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @param  int  $maxDepth  Maximum recursion depth for dependency resolution
     */
    public function __construct(
        private readonly ComposerReaderInterface $composerReader,
        private readonly FileSystemInterface $fileSystem,
        private readonly int $maxDepth = 3,
    ) {}

    /**
     * Resolves all dependencies recursively.
     *
     * @return array<string, array<string, mixed>> Map of package names to their composer.json data
     */
    public function resolveAll(): array
    {
        $this->resetState();

        foreach ($this->composerReader->getRequire() as $package => $version) {
            if ($this->isPhpPackage($package)) {
                continue;
            }

            $this->resolvePackage($package, 0);
        }

        return $this->resolved;
    }

    /**
     * Resolves the dependencies of a specific package.
     *
     * @param  string  $package  The package name to resolve dependencies for
     * @return array<string, array<string, mixed>> Map of dependency names to their composer.json data
     */
    public function resolvePackageDependencies(string $package): array
    {
        $this->resetState();

        $this->resolvePackage($package, 0);

        return $this->resolved;
    }

    /**
     * Builds a hierarchical dependency tree for all packages.
     *
     * @return array<string, array<string, mixed>> Nested tree structure of dependencies
     */
    public function getDependencyTree(): array
    {
        $tree = [];

        foreach ($this->composerReader->getRequire() as $package => $version) {
            if ($this->isPhpPackage($package)) {
                continue;
            }

            $tree[$package] = $this->buildTree($package, 0);
        }

        return $tree;
    }

    /**
     * Gets a flattened list of all package dependencies.
     *
     * @return StringTypedCollection Collection of package names
     */
    public function getFlatDependencies(): StringTypedCollection
    {
        $packages = new StringTypedCollection;

        foreach (array_keys($this->resolveAll()) as $package) {
            $packages->add($package);
        }

        return $packages;
    }

    /**
     * Checks if there are any circular dependencies in the package graph.
     *
     * @return bool True if a circular dependency exists, false otherwise
     */
    public function hasCircularDependency(): bool
    {
        foreach ($this->composerReader->getRequire() as $package => $version) {
            if ($this->isPhpPackage($package)) {
                continue;
            }

            $this->resetState();

            if ($this->detectCycle($package, [], 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves a single package and its dependencies recursively.
     *
     * @param  string  $package  The package name
     * @param  int  $depth  The current recursion depth
     */
    private function resolvePackage(string $package, int $depth): void
    {
        if ($this->shouldSkipResolution($package, $depth)) {
            return;
        }

        $this->visited[] = $package;

        $composerData = $this->loadComposerData($package);

        if ($composerData === null) {
            return;
        }

        $this->resolved[$package] = $composerData;

        foreach ($this->extractDependencies($composerData) as $dependency) {
            if ($this->shouldSkipDependency($dependency)) {
                continue;
            }

            $this->resolvePackage($dependency, $depth + 1);
        }
    }

    /**
     * Builds a dependency tree for a package.
     *
     * @param  string  $package  The package name
     * @param  int  $depth  The current recursion depth
     * @return array<string, array<string, mixed>> The dependency tree
     */
    private function buildTree(string $package, int $depth): array
    {
        if ($depth > $this->maxDepth) {
            return ['__truncated__' => true];
        }

        $composerData = $this->loadComposerData($package);

        if ($composerData === null) {
            return [];
        }

        $tree = [];

        foreach ($this->extractDependencies($composerData) as $dependency) {
            if ($this->isPhpPackage($dependency)) {
                continue;
            }

            $tree[$dependency] = $this->buildTree($dependency, $depth + 1);
        }

        return $tree;
    }

    /**
     * Detects circular dependencies in the package graph.
     *
     * @param  string  $package  The package to check
     * @param  array<int, string>  $path  The current dependency path
     * @param  int  $depth  The current recursion depth
     * @return bool True if a cycle is detected, false otherwise
     */
    private function detectCycle(string $package, array $path, int $depth): bool
    {
        if ($depth > $this->maxDepth) {
            return false;
        }

        if (in_array($package, $path, true)) {
            return true;
        }

        $path[] = $package;

        $composerData = $this->loadComposerData($package);

        if ($composerData === null) {
            return false;
        }

        foreach ($this->extractDependencies($composerData) as $dependency) {
            if ($this->isPhpPackage($dependency)) {
                continue;
            }

            if ($this->detectCycle($dependency, $path, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a package should be skipped during resolution.
     *
     * @param  string  $package  The package name
     * @param  int  $depth  The current depth
     * @return bool True if the package should be skipped
     */
    private function shouldSkipResolution(string $package, int $depth): bool
    {
        return $depth > $this->maxDepth || in_array($package, $this->visited, true);
    }

    /**
     * Checks if a dependency should be skipped.
     *
     * @param  string  $dependency  The dependency name
     * @return bool True if the dependency should be skipped
     */
    private function shouldSkipDependency(string $dependency): bool
    {
        return $this->isPhpPackage($dependency) || in_array($dependency, $this->visited, true);
    }

    /**
     * Loads and parses a package's composer.json data.
     *
     * @param  string  $package  The package name
     * @return array<string, mixed>|null The composer data, or null on failure
     */
    private function loadComposerData(string $package): ?array
    {
        $composerPath = $this->composerReader->getVendorDir().'/'.$package.'/composer.json';

        if (! $this->fileSystem->exists($composerPath)) {
            return null;
        }

        try {
            $content = $this->fileSystem->get($composerPath);
        } catch (RuntimeException $e) {
            return null;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Extracts dependencies from composer data.
     *
     * @param  array<string, mixed>  $data  The composer data
     * @return array<int, string> The list of dependency names
     */
    private function extractDependencies(array $data): array
    {
        $dependencies = [];

        foreach ($data['require'] ?? [] as $dependency => $version) {
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Checks if a package is a PHP meta-package.
     *
     * @param  string  $package  The package name
     * @return bool True if it's a PHP package
     */
    private function isPhpPackage(string $package): bool
    {
        return str_starts_with($package, 'php');
    }

    /**
     * Resets the internal state for a new resolution.
     */
    private function resetState(): void
    {
        $this->resolved = [];
        $this->visited = [];
    }
}
