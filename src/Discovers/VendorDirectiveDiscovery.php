<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\Directive\Contracts\Services\DependencyResolverInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Throwable;

/**
 * Discovery source for vendor package directives.
 *
 * Scans installed Composer packages for directive classes by examining
 * PSR-4 autoloading paths and custom configuration files.
 */
final class VendorDirectiveDiscovery extends AbstractDiscovery
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
    ) {
        parent::__construct();
    }

    /**
     * Discovers directives from all vendor packages.
     *
     * @return array<int, string> List of fully qualified class names
     */
    public function discover(): array
    {
        $directives = [];

        try {
            $packages = $this->dependencyResolver->getFlatDependencies()->toArray();

            foreach ($packages as $package) {
                try {
                    $directives = array_merge($directives, $this->scanPackage($package));
                } catch (Throwable $e) {
                    $this->addProblem(
                        'scan_package',
                        'Failed to scan package: '.$package,
                        $e->getMessage(),
                        ['package' => $package]
                    );
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'resolve_packages',
                'Failed to resolve vendor packages',
                $e->getMessage(),
                []
            );
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
        try {
            $packagePath = $this->getPackagePath($package);

            if (! $this->fileSystem->isDirectory($packagePath)) {
                return [];
            }

            $directives = $this->scanAutoloadPaths($package, $packagePath);
            $customDirectives = $this->scanCustomSources($package, $packagePath);

            return array_merge($directives, $customDirectives);
        } catch (Throwable $e) {
            $this->addProblem(
                'scan_package_error',
                'Failed to scan package: '.$package,
                $e->getMessage(),
                ['package' => $package]
            );

            return [];
        }
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
        try {
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
                    try {
                        $directives = array_merge(
                            $directives,
                            $this->scanner->scan($fullPath, $this->maxDepth)
                        );
                    } catch (Throwable $e) {
                        $this->addProblem(
                            'scan_autoload_path',
                            'Failed to scan autoload path: '.$fullPath,
                            $e->getMessage(),
                            ['package' => $package, 'path' => $fullPath, 'namespace' => $namespace]
                        );
                    }
                }
            }

            return $directives;
        } catch (Throwable $e) {
            $this->addProblem(
                'scan_autoload_paths',
                'Failed to scan autoload paths for package: '.$package,
                $e->getMessage(),
                ['package' => $package]
            );

            return [];
        }
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
        try {
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
                    $this->addProblem(
                        'custom_source_not_directory',
                        'Custom source path is not a directory: '.$fullPath,
                        'Path does not exist or is not a directory',
                        ['package' => $package, 'source' => $source, 'full_path' => $fullPath]
                    );

                    continue;
                }

                try {
                    $directives = array_merge(
                        $directives,
                        $this->scanner->scan($fullPath, $this->maxDepth)
                    );
                } catch (Throwable $e) {
                    $this->addProblem(
                        'scan_custom_source',
                        'Failed to scan custom source: '.$fullPath,
                        $e->getMessage(),
                        ['package' => $package, 'source' => $source, 'full_path' => $fullPath]
                    );
                }
            }

            return $directives;
        } catch (Throwable $e) {
            $this->addProblem(
                'scan_custom_sources',
                'Failed to scan custom sources for package: '.$package,
                $e->getMessage(),
                ['package' => $package]
            );

            return [];
        }
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
        } catch (Throwable $e) {
            $this->addProblem(
                'read_composer_json',
                'Failed to read composer.json for package: '.basename($packagePath),
                $e->getMessage(),
                ['composer_path' => $composerPath]
            );

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
            $this->addProblem(
                'extract_custom_sources',
                'Failed to extract custom sources from config: '.$configPath,
                $e->getMessage(),
                ['config_path' => $configPath]
            );

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
