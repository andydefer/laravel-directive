<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Interface for resolving and analyzing Composer package dependencies.
 *
 * Provides methods to resolve dependency trees, detect circular dependencies,
 * and retrieve flattened lists of all dependencies.
 */
interface DependencyResolverInterface
{
    /**
     * Resolves all dependencies recursively.
     *
     * Returns a complete list of all dependencies with their composer.json data.
     *
     * @return array<string, array<string, mixed>> Map of package names to their composer.json data
     */
    public function resolveAll(): array;

    /**
     * Resolves the direct and transitive dependencies of a specific package.
     *
     * @param  string  $package  The package name to resolve dependencies for
     * @return array<string, array<string, mixed>> Map of dependency names to their composer.json data
     */
    public function resolvePackageDependencies(string $package): array;

    /**
     * Builds a hierarchical dependency tree for all packages.
     *
     * @return array<string, array<string, mixed>> Nested tree structure of dependencies
     */
    public function getDependencyTree(): array;

    /**
     * Gets a flattened list of all package dependencies.
     *
     * @return StringTypedCollection Collection of package names
     */
    public function getFlatDependencies(): StringTypedCollection;

    /**
     * Checks if there are any circular dependencies in the package graph.
     *
     * @return bool True if a circular dependency exists, false otherwise
     */
    public function hasCircularDependency(): bool;
}
