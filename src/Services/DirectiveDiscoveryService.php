<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;

class DirectiveDiscoveryService implements DirectiveLoaderInterface
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;

    private static bool $bootstrapped = false;

    private string $projectRoot;

    private string $vendorDir;

    /**
     * @var array<string, bool> Cache des packages déjà scannés
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

    public function discover(): DirectiveMetadataCollection
    {
        return $this->loader->load();
    }

    // ==== Implémentation de DirectiveLoaderInterface pour le chargement depuis le filesystem ====

    public function load(): DirectiveMetadataCollection
    {
        return $this->loadFromFilesystem();
    }

    protected function loadFromFilesystem(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;
        $results = $this->discoverFromFilesystem($results);
        $results = $this->discoverFromVendorPackagesRecursive($results);

        return $results;
    }

    protected function discoverFromFilesystem(DirectiveMetadataCollection $results): DirectiveMetadataCollection
    {
        $path = $this->config->directivesPath;

        if ($path === '' || ! is_dir($path)) {
            return $results;
        }

        return $this->scanDirectoryForDirectives($results, $path);
    }

    protected function discoverFromVendorPackagesRecursive(DirectiveMetadataCollection $results): DirectiveMetadataCollection
    {
        $composerFile = $this->projectRoot . '/composer.json';

        if (! file_exists($composerFile)) {
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

        if (! is_dir($packagePath)) {
            return;
        }

        $this->scannedPackages[$packageName] = true;

        $this->scanPackageDirectories($results, $packagePath);

        if ($depth === 1) {
            $this->scanPackageDependencies($results, $packagePath, $depth);
        }
    }

    private function scanPackageDependencies(DirectiveMetadataCollection $results, string $packagePath, int $currentDepth): void
    {
        $composerFile = $packagePath . '/composer.json';

        if (! file_exists($composerFile)) {
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

    private function scanDirectoryForDirectives(DirectiveMetadataCollection $results, string $directory): DirectiveMetadataCollection
    {
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return $results;
        }

        foreach ($files as $file) {
            $metadata = $this->extractMetadataFromFile($file);
            if ($metadata !== null && ! $this->isAlreadyRegistered($results, $metadata->signature)) {
                $results->add($metadata);
            }
        }

        return $results;
    }

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

    private function extractMetadataFromFile(string $file): ?DirectiveMetadataRecord
    {
        $class = $this->getClassFromFile($file);

        if ($class === '' || ! class_exists($class)) {
            return null;
        }

        return $this->extractMetadataFromClass($class);
    }

    private function extractMetadataFromClass(string $class): ?DirectiveMetadataRecord
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->isAbstract()) {
            $this->debug("Skipping abstract class: {$class}");

            return null;
        }

        if (! is_subclass_of($class, AbstractDirective::class)) {
            $this->debug("Skipping {$class}: does not extend " . AbstractDirective::class);

            return null;
        }

        if (! is_subclass_of($class, DirectiveInterface::class)) {
            $this->debug("Skipping {$class}: does not implement " . DirectiveInterface::class);

            return null;
        }

        $needsLaravel = $this->checkIfNeedsLaravel($class);

        if ($needsLaravel && $this->laravelBootstrapper !== null && ! self::$bootstrapped) {
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
            $this->debug("Failed to extract metadata for {$class}: " . $e->getMessage());

            return null;
        }
    }

    private function checkIfNeedsLaravel(string $class): bool
    {
        try {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->hasMethod('shouldBootLaravel')) {
                return false;
            }

            $tempInstance = $reflection->newInstanceWithoutConstructor();

            return $tempInstance->shouldBootLaravel();
        } catch (\Throwable $e) {
            return false;
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

        if ($namespace === '') {
            return $class;
        }

        return $namespace . '\\' . $class;
    }

    private function debug(string $message): void
    {
        $debug = getenv('DIRECTIVE_DEBUG') === 'true';
        if ($debug) {
            fwrite(STDERR, "[DEBUG] {$message}\n");
        }
    }
}
