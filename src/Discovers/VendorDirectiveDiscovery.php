<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\Directive\Contracts\Services\DependencyResolverInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use RuntimeException;
use Throwable;

/**
 * Discovery source for vendor package directives.
 *
 * Scans installed Composer packages for directive classes by examining
 * PSR-4 autoloading paths and custom configuration files.
 */
final class VendorDirectiveDiscovery implements DiscoverySourceInterface
{
    /**
     * The subdirectory where directives are typically located within a package.
     */
    private const DIRECTIVES_SUBDIR = 'Directives';

    /**
     * The configuration file path relative to the package root.
     */
    private const CONFIG_FILE = 'config/directive.php';

    /**
     * The composer.json file name.
     */
    private const COMPOSER_FILE = 'composer.json';

    /**
     * @param  ComposerReaderInterface  $composerReader  The Composer reader service
     * @param  DependencyResolverInterface  $dependencyResolver  The dependency resolver
     * @param  FileSystemInterface  $fileSystem  The filesystem service
     * @param  DirectiveScannerInterface  $scanner  The directive scanner
     * @param  int  $maxDepth  Maximum directory scanning depth
     */
    public function __construct(
        private readonly ComposerReaderInterface $composerReader,
        private readonly DependencyResolverInterface $dependencyResolver,
        private readonly FileSystemInterface $fileSystem,
        private readonly DirectiveScannerInterface $scanner,
        private readonly int $maxDepth = 3,
    ) {}

    /**
     * Discovers directives from all vendor packages.
     *
     * @return array<int, string> List of fully qualified class names
     */
    public function discover(): array
    {
        $directives = [];
        $packages = $this->dependencyResolver->getFlatDependencies()->toArray();

        foreach ($packages as $package) {
            $directives = array_merge($directives, $this->scanPackage($package));
        }

        return $directives;
    }

    /**
     * Scans a single vendor package for directives.
     *
     * @param  string  $package  The package name
     * @return array<int, string> List of fully qualified class names
     */
    private function scanPackage(string $package): array
    {
        $packagePath = $this->getPackagePath($package);

        if (! $this->fileSystem->isDirectory($packagePath)) {
            return [];
        }

        $directives = $this->scanAutoloadPaths($package, $packagePath);
        $customDirectives = $this->scanCustomSources($package, $packagePath);

        return array_merge($directives, $customDirectives);
    }

    /**
     * Gets the absolute path to a package directory.
     *
     * @param  string  $package  The package name
     * @return string The package path
     */
    private function getPackagePath(string $package): string
    {
        return $this->composerReader->getVendorDir().'/'.$package;
    }

    /**
     * Scans PSR-4 autoload paths for directives.
     *
     * @param  string  $package  The package name
     * @param  string  $packagePath  The package directory path
     * @return array<int, string> List of fully qualified class names
     */
    private function scanAutoloadPaths(string $package, string $packagePath): array
    {
        $composerData = $this->readComposerJson($packagePath);

        if ($composerData === null) {
            return [];
        }

        $psr4 = $composerData['autoload']['psr-4'] ?? [];
        $directives = [];

        foreach ($psr4 as $namespace => $path) {
            if (! is_string($path)) {
                continue;
            }

            $fullPath = $packagePath.'/'.rtrim($path, '/').'/'.self::DIRECTIVES_SUBDIR;

            if ($this->fileSystem->isDirectory($fullPath)) {
                $directives = array_merge(
                    $directives,
                    $this->scanner->scan($fullPath, $this->maxDepth)
                );
            }
        }

        return $directives;
    }

    /**
     * Scans custom source paths defined in the package's configuration.
     *
     * @param  string  $package  The package name
     * @param  string  $packagePath  The package directory path
     * @return array<int, string> List of fully qualified class names
     */
    private function scanCustomSources(string $package, string $packagePath): array
    {
        $configPath = $packagePath.'/'.self::CONFIG_FILE;

        if (! $this->fileSystem->exists($configPath)) {
            return [];
        }

        $customSources = $this->extractCustomSources($configPath);

        if (empty($customSources)) {
            return [];
        }

        $directives = [];

        foreach ($customSources as $source) {
            $fullPath = $packagePath.'/'.ltrim($source, '/');

            if (! $this->fileSystem->isDirectory($fullPath)) {
                continue;
            }

            $directives = array_merge(
                $directives,
                $this->scanner->scan($fullPath, $this->maxDepth)
            );
        }

        return $directives;
    }

    /**
     * Reads and parses a package's composer.json file.
     *
     * @param  string  $packagePath  The package directory path
     * @return array<string, mixed>|null The parsed composer data, or null on failure
     */
    private function readComposerJson(string $packagePath): ?array
    {
        $composerPath = $packagePath.'/'.self::COMPOSER_FILE;

        if (! $this->fileSystem->exists($composerPath)) {
            return null;
        }

        try {
            $content = $this->fileSystem->get($composerPath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $data;
        } catch (RuntimeException $e) {
            return null;
        }
    }

    /**
     * Extracts custom source paths from a package's configuration file.
     *
     * @param  string  $configPath  The path to the configuration file
     * @return array<int, string> The custom source paths
     */
    private function extractCustomSources(string $configPath): array
    {
        try {
            $configData = require $configPath;

            if (! is_array($configData) || ! isset($configData['custom_sources'])) {
                return [];
            }

            $customSources = $configData['custom_sources'];

            return is_array($customSources) ? $this->filterStringValues($customSources) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Filters an array to only keep string values.
     *
     * @param  array<mixed>  $values  The values to filter
     * @return array<int, string> The filtered string values
     */
    private function filterStringValues(array $values): array
    {
        return array_values(array_filter($values, 'is_string'));
    }
}
