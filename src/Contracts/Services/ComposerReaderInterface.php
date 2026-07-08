<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

/**
 * Interface for reading and accessing Composer package information.
 *
 * Provides a typed abstraction over the composer.json file, allowing
 * retrieval of dependencies, autoloading configuration, and package metadata.
 */
interface ComposerReaderInterface
{
    /**
     * Gets the production dependencies from composer.json.
     *
     * @return array<string, string> Map of package names to version constraints
     */
    public function getRequire(): array;

    /**
     * Gets the development dependencies from composer.json.
     *
     * @return array<string, string> Map of package names to version constraints
     */
    public function getRequireDev(): array;

    /**
     * Gets all dependencies (production and development) from composer.json.
     *
     * @return array<string, string> Map of package names to version constraints
     */
    public function getAllDependencies(): array;

    /**
     * Gets the list of vendor directories from production dependencies.
     *
     * @return array<int, string> List of vendor names
     */
    public function getVendorDirectories(): array;

    /**
     * Gets the list of all production package names.
     *
     * @return array<int, string> List of package names
     */
    public function getPackageNames(): array;

    /**
     * Checks if a specific package is installed.
     *
     * @param  string  $packageName  The package name to check (e.g., "laravel/framework")
     * @return bool True if the package exists, false otherwise
     */
    public function hasPackage(string $packageName): bool;

    /**
     * Gets the version constraint of a specific package.
     *
     * @param  string  $packageName  The package name to query
     * @return string|null The version constraint, or null if the package is not found
     */
    public function getPackageVersion(string $packageName): ?string;

    /**
     * Gets the production autoloading configuration.
     *
     * @return array<string, mixed> The autoload configuration from composer.json
     */
    public function getAutoload(): array;

    /**
     * Gets the development autoloading configuration.
     *
     * @return array<string, mixed> The autoload-dev configuration from composer.json
     */
    public function getAutoloadDev(): array;

    /**
     * Gets the absolute path to the vendor directory.
     *
     * @return string The vendor directory path
     */
    public function getVendorDir(): string;
}
