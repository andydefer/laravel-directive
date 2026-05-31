<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;

/**
 * Service responsible for discovering directives from the filesystem and vendor packages.
 *
 * This service scans configured directories and vendor packages to find all
 * available directives. It supports recursive scanning of composer dependencies
 * up to depth 2 and caches scanned packages to avoid duplicate work.
 *
 * @author Andy Defer
 */
class DirectiveDiscoveryService implements DirectiveLoaderInterface
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;
    private static bool $bootstrapped = false;
    private string $projectRoot;
    private string $vendorDir;

    /**
     * Cache of already scanned packages to avoid duplicate scans.
     *
     * @var array<string, bool>
     */
    private array $scannedPackages = [];

    private ?DirectiveLoaderInterface $loader = null;

    public function __construct(
        private readonly DirectiveConfig $config,
        private readonly DirectiveHydratorService $hydrator,
        ?DirectiveLoaderInterface $loader = null,
    ) {
        $this->projectRoot = getcwd();
        $this->vendorDir = $this->projectRoot . '/vendor';
        $this->loader = $loader ?? $this;
    }

    public function setLoader(DirectiveLoaderInterface $loader): void
    {
        $this->loader = $loader;
    }

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    /**
     * Discovers all available directives.
     *
     * @return DirectiveMetadataCollection Collection of directive metadata
     */
    public function discover(): DirectiveMetadataCollection
    {
        return $this->loader->load();
    }

    /**
     * Loads directives from the filesystem.
     *
     * @return DirectiveMetadataCollection Collection of directive metadata
     */
    public function load(): DirectiveMetadataCollection
    {
        return $this->loadFromFilesystem();
    }

    /**
     * Loads directives from the configured filesystem path and vendor packages.
     *
     * @return DirectiveMetadataCollection Collection of directive metadata
     */
    protected function loadFromFilesystem(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection();

        $results = $this->discoverFromFilesystem($results);
        $results = $this->discoverFromVendorPackagesRecursive($results);

        return $results;
    }

    /**
     * Discovers directives from the configured filesystem path.
     *
     * @param DirectiveMetadataCollection $results Current collection to augment
     *
     * @return DirectiveMetadataCollection Augmented collection
     */
    protected function discoverFromFilesystem(DirectiveMetadataCollection $results): DirectiveMetadataCollection
    {
        $path = $this->config->directivesPath;

        if ($path === '' || !is_dir($path)) {
            return $results;
        }

        return $this->scanDirectoryForDirectives($results, $path);
    }

    /**
     * Discovers directives from vendor packages recursively.
     *
     * Reads composer.json of the root project and scans dependencies up to depth 2.
     *
     * @param DirectiveMetadataCollection $results Current collection to augment
     *
     * @return DirectiveMetadataCollection Augmented collection
     */
    protected function discoverFromVendorPackagesRecursive(DirectiveMetadataCollection $results): DirectiveMetadataCollection
    {
        $composerFile = $this->projectRoot . '/composer.json';

        if (!file_exists($composerFile)) {
            return $results;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        $rootPackages = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        $this->scannedPackages = [];

        foreach ($rootPackages as $packageName => $version) {
            $this->scanPackage($results, $packageName, 1);
        }

        return $results;
    }

    /**
     * Scans a single package for directives.
     *
     * @param DirectiveMetadataCollection $results    Current collection
     * @param string                      $packageName Package name to scan
     * @param int                         $depth       Current recursion depth (max 2)
     */
    private function scanPackage(DirectiveMetadataCollection $results, string $packageName, int $depth): void
    {
        if (isset($this->scannedPackages[$packageName])) {
            return;
        }

        if ($depth > 2) {
            return;
        }

        if (str_starts_with($packageName, 'php') || $packageName === 'php') {
            return;
        }

        $packagePath = $this->vendorDir . '/' . $packageName;

        if (!is_dir($packagePath)) {
            return;
        }

        $this->scannedPackages[$packageName] = true;

        $this->scanPackageDirectories($results, $packagePath);

        if ($depth === 1) {
            $this->scanPackageDependencies($results, $packagePath, $depth);
        }
    }

    /**
     * Scans dependencies of a package.
     *
     * @param DirectiveMetadataCollection $results      Current collection
     * @param string                      $packagePath   Path to the package
     * @param int                         $currentDepth Current depth level
     */
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

    /**
     * Scans common directories within a package for directives.
     *
     * @param DirectiveMetadataCollection $results     Current collection
     * @param string                      $packagePath Path to the package
     */
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

    /**
     * Scans a directory for PHP files containing directives.
     *
     * @param DirectiveMetadataCollection $results   Current collection
     * @param string                      $directory Directory to scan
     *
     * @return DirectiveMetadataCollection Augmented collection
     */
    private function scanDirectoryForDirectives(DirectiveMetadataCollection $results, string $directory): DirectiveMetadataCollection
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return $results;
        }

        foreach ($files as $file) {
            $metadata = $this->extractMetadataFromFile($file);
            if ($metadata !== null && !$this->isAlreadyRegistered($results, $metadata->signature)) {
                $results->add($metadata);
            }
        }

        return $results;
    }

    /**
     * Checks if a directive with the given signature is already registered.
     *
     * @param DirectiveMetadataCollection $results  Current collection
     * @param string                      $signature Directive signature to check
     *
     * @return bool True if already registered
     */
    private function isAlreadyRegistered(DirectiveMetadataCollection $results, string $signature): bool
    {
        foreach ($results as $existing) {
            if ($existing->signature === $signature) {
                return true;
            }
            if ($existing->aliases->contains($signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts directive metadata from a PHP file.
     *
     * @param string $file Path to the PHP file
     *
     * @return DirectiveMetadataRecord|null Metadata or null if extraction failed
     */
    private function extractMetadataFromFile(string $file): ?DirectiveMetadataRecord
    {
        $class = $this->getClassFromFile($file);

        if ($class === '' || !class_exists($class)) {
            return null;
        }

        return $this->extractMetadataFromClass($class);
    }

    /**
     * Extracts directive metadata from a class.
     *
     * @param string $class Fully qualified class name
     *
     * @return DirectiveMetadataRecord|null Metadata or null if extraction failed
     */
    private function extractMetadataFromClass(string $class): ?DirectiveMetadataRecord
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        if (!is_subclass_of($class, AbstractDirective::class)) {
            return null;
        }

        if (!is_subclass_of($class, DirectiveInterface::class)) {
            return null;
        }

        $needsLaravel = $this->checkIfNeedsLaravel($class);

        if ($needsLaravel && $this->laravelBootstrapper !== null && !self::$bootstrapped) {
            $this->laravelBootstrapper->bootstrap();
            self::$bootstrapped = true;
        }

        try {
            $blueprint = $this->hydrator->hydrateBlueprint($class);
            $directive = $this->hydrator->hydrateForAliases($class);
            $aliases = $directive->getAliases();

            return new DirectiveMetadataRecord(
                signature: $blueprint->signature,
                class: $blueprint->class,
                description: $blueprint->description,
                aliases: $aliases,
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Checks if a directive requires Laravel bootstrapping.
     *
     * @param string $class Fully qualified class name
     *
     * @return bool True if Laravel bootstrapping is needed
     */
    private function checkIfNeedsLaravel(string $class): bool
    {
        try {
            $reflection = new \ReflectionClass($class);

            if (!$reflection->hasMethod('shouldBootLaravel')) {
                return false;
            }

            $tempInstance = $reflection->newInstanceWithoutConstructor();

            return $tempInstance->shouldBootLaravel();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Extracts the fully qualified class name from a PHP file.
     *
     * @param string $file Path to the PHP file
     *
     * @return string Fully qualified class name or empty string
     */
    private function getClassFromFile(string $file): string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        preg_match('/namespace\s+([^;]+);/', $content, $match);
        $namespace = $match[1] ?? '';
        $class = basename($file, '.php');

        if ($namespace === '') {
            return $class;
        }

        return $namespace . '\\' . $class;
    }

    /**
     * Outputs debug information if DIRECTIVE_DEBUG is enabled.
     *
     * @param string $message Debug message
     */
    private function debug(string $message): void
    {
        $debug = getenv('DIRECTIVE_DEBUG') === 'true';
        if ($debug) {
            fwrite(STDERR, "[DEBUG] {$message}\n");
        }
    }
}
