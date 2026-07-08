<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use RuntimeException;

/**
 * Service for reading and accessing Composer package information.
 *
 * Provides a typed abstraction over the composer.json file, allowing
 * retrieval of dependencies, autoloading configuration, and package metadata.
 */
final class ComposerReaderService implements ComposerReaderInterface
{
    /**
     * The cached composer.json data.
     *
     * @var array<string, mixed>|null
     */
    private ?array $composerData = null;

    /**
     * @param  DirectiveConfigInterface  $config  The directive configuration
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     */
    public function __construct(
        private readonly DirectiveConfigInterface $config,
        private readonly FileSystemInterface $fileSystem,
    ) {}

    /**
     * Gets the production dependencies from composer.json.
     *
     * @return array<string, string> Map of package names to version constraints
     */
    public function getRequire(): array
    {
        $data = $this->getComposerData();

        return $data['require'] ?? [];
    }

    /**
     * Gets the development dependencies from composer.json.
     *
     * @return array<string, string> Map of package names to version constraints
     */
    public function getRequireDev(): array
    {
        $data = $this->getComposerData();

        return $data['require-dev'] ?? [];
    }

    /**
     * Gets all dependencies (production and development) from composer.json.
     *
     * @return array<string, string> Map of package names to version constraints
     */
    public function getAllDependencies(): array
    {
        return array_merge(
            $this->getRequire(),
            $this->getRequireDev()
        );
    }

    /**
     * Gets the list of vendor names from production dependencies.
     *
     * @return array<int, string> List of vendor names
     */
    public function getVendorDirectories(): array
    {
        $vendors = [];

        foreach ($this->getRequire() as $package => $version) {
            if ($this->isPhpPackage($package)) {
                continue;
            }

            $vendor = $this->extractVendorFromPackage($package);

            if ($vendor !== null) {
                $vendors[] = $vendor;
            }
        }

        return array_values(array_unique($vendors));
    }

    /**
     * Gets the list of all production package names.
     *
     * @return array<int, string> List of package names
     */
    public function getPackageNames(): array
    {
        $packages = [];

        foreach ($this->getRequire() as $package => $version) {
            if ($this->isPhpPackage($package)) {
                continue;
            }

            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * Checks if a specific package is installed.
     *
     * @param  string  $packageName  The package name to check
     * @return bool True if the package exists, false otherwise
     */
    public function hasPackage(string $packageName): bool
    {
        $dependencies = $this->getAllDependencies();

        return isset($dependencies[$packageName]);
    }

    /**
     * Gets the version constraint of a specific package.
     *
     * @param  string  $packageName  The package name to query
     * @return string|null The version constraint, or null if the package is not found
     */
    public function getPackageVersion(string $packageName): ?string
    {
        $dependencies = $this->getAllDependencies();

        return $dependencies[$packageName] ?? null;
    }

    /**
     * Gets the production autoloading configuration.
     *
     * @return array<string, mixed> The autoload configuration from composer.json
     */
    public function getAutoload(): array
    {
        $data = $this->getComposerData();

        return $data['autoload'] ?? [];
    }

    /**
     * Gets the development autoloading configuration.
     *
     * @return array<string, mixed> The autoload-dev configuration from composer.json
     */
    public function getAutoloadDev(): array
    {
        $data = $this->getComposerData();

        return $data['autoload-dev'] ?? [];
    }

    /**
     * Gets the absolute path to the vendor directory.
     *
     * @return string The vendor directory path
     */
    public function getVendorDir(): string
    {
        return $this->config->getVendorDir();
    }

    /**
     * Reads and parses the composer.json file.
     *
     * @return array<string, mixed> The parsed composer data
     *
     * @throws RuntimeException If the composer.json file cannot be read or parsed
     */
    private function getComposerData(): array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $composerPath = $this->config->getComposerPath();

        $this->validateComposerFileExists($composerPath);

        $content = $this->readComposerFile($composerPath);
        $data = $this->parseComposerJson($content, $composerPath);

        $this->composerData = $data;

        return $this->composerData;
    }

    /**
     * Validates that the composer.json file exists.
     *
     * @param  string  $composerPath  The path to composer.json
     *
     * @throws RuntimeException If the file does not exist
     */
    private function validateComposerFileExists(string $composerPath): void
    {
        if (! $this->fileSystem->exists($composerPath)) {
            throw new RuntimeException("composer.json not found at: {$composerPath}");
        }
    }

    /**
     * Reads the composer.json file content.
     *
     * @param  string  $composerPath  The path to composer.json
     * @return string The file content
     *
     * @throws RuntimeException If the file cannot be read
     */
    private function readComposerFile(string $composerPath): string
    {
        try {
            return $this->fileSystem->get($composerPath);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Could not read composer.json at: {$composerPath}", 0, $e);
        }
    }

    /**
     * Parses the composer.json content.
     *
     * @param  string  $content  The JSON content
     * @param  string  $composerPath  The path to composer.json (for error context)
     * @return array<string, mixed> The parsed data
     *
     * @throws RuntimeException If the JSON is invalid
     */
    private function parseComposerJson(string $content, string $composerPath): array
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf(
                    'Invalid JSON in composer.json at %s: %s',
                    $composerPath,
                    json_last_error_msg()
                )
            );
        }

        return $data;
    }

    /**
     * Checks if a package is a PHP meta-package.
     *
     * @param  string  $package  The package name
     * @return bool True if it's a PHP package, false otherwise
     */
    private function isPhpPackage(string $package): bool
    {
        return str_starts_with($package, 'php');
    }

    /**
     * Extracts the vendor name from a package name.
     *
     * @param  string  $package  The package name (e.g., "vendor/package")
     * @return string|null The vendor name, or null if invalid format
     */
    private function extractVendorFromPackage(string $package): ?string
    {
        $parts = explode('/', $package);

        if (count($parts) === 2) {
            return $parts[0];
        }

        return null;
    }
}
