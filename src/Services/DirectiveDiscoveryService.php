<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;

class DirectiveDiscoveryService
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;
    private static bool $bootstrapped = false;
    private string $projectRoot;
    private string $vendorDir;

    public function __construct(
        private readonly DirectiveConfig $config,
        private readonly DirectiveHydratorService $hydrator,
    ) {
        $this->projectRoot = getcwd();
        $this->vendorDir = $this->projectRoot . '/vendor';
    }

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    public function discover(): TypedCollection
    {
        $results = new TypedCollection(DirectiveMetadataRecord::class);

        // 1. Découverte depuis le filesystem de l'application (app/Directives/)
        $results = $this->discoverFromFilesystem($results);

        // 2. Découverte depuis les packages installés (vendor/*/*/src/Directives/)
        $results = $this->discoverFromVendorPackages($results);

        return $results;
    }

    private function discoverFromFilesystem(TypedCollection $results): TypedCollection
    {
        $path = $this->config->directivesPath;

        if ($path === '' || !is_dir($path)) {
            return $results;
        }

        return $this->scanDirectoryForDirectives($results, $path);
    }

    /**
     * Découvre les directives dans les packages installés via Composer.
     * 
     * Parcourt tous les packages dans vendor/ et cherche dans src/Directives/
     */
    private function discoverFromVendorPackages(TypedCollection $results): TypedCollection
    {
        $composerFile = $this->projectRoot . '/composer.json';

        if (!file_exists($composerFile)) {
            return $results;
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        // Récupérer tous les packages requis (y compris dev)
        $packages = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        foreach ($packages as $packageName => $version) {
            // Ignorer les packages PHP internes
            if (str_starts_with($packageName, 'php') || $packageName === 'php') {
                continue;
            }

            $packagePath = $this->vendorDir . '/' . $packageName;

            if (!is_dir($packagePath)) {
                continue;
            }

            // Chercher dans src/Directives
            $directivesPath = $packagePath . '/src/Directives';

            if (is_dir($directivesPath)) {
                $results = $this->scanDirectoryForDirectives($results, $directivesPath);
            }

            // Optionnel : Chercher aussi dans un dossier alternatif pour compatibilité
            $altPaths = [
                $packagePath . '/Directives',
                $packagePath . '/src/Directive',
            ];

            foreach ($altPaths as $altPath) {
                if (is_dir($altPath) && $altPath !== $directivesPath) {
                    $results = $this->scanDirectoryForDirectives($results, $altPath);
                }
            }
        }

        return $results;
    }

    /**
     * Scanne un répertoire pour trouver des directives.
     */
    private function scanDirectoryForDirectives(TypedCollection $results, string $directory): TypedCollection
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
     * Vérifie si une directive avec la même signature est déjà enregistrée.
     */
    private function isAlreadyRegistered(TypedCollection $results, string $signature): bool
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

        if ($class === '' || !class_exists($class)) {
            return null;
        }

        return $this->extractMetadataFromClass($class);
    }

    private function extractMetadataFromClass(string $class): ?DirectiveMetadataRecord
    {
        $reflection = new \ReflectionClass($class);

        // Vérification 1 : La classe ne doit pas être abstraite
        if ($reflection->isAbstract()) {
            $debug = getenv('DIRECTIVE_DEBUG') === 'true';
            if ($debug) {
                fwrite(STDERR, "[DEBUG] Skipping abstract class: {$class}\n");
            }
            return null;
        }

        // Vérification 2 : La classe doit étendre AbstractDirective
        if (!is_subclass_of($class, AbstractDirective::class)) {
            $debug = getenv('DIRECTIVE_DEBUG') === 'true';
            if ($debug) {
                fwrite(STDERR, "[DEBUG] Skipping {$class}: does not extend " . AbstractDirective::class . "\n");
            }
            return null;
        }

        // Vérification 3 : La classe doit implémenter DirectiveInterface
        // (Normalement true si elle étend AbstractDirective, mais vérification de sécurité)
        if (!is_subclass_of($class, DirectiveInterface::class)) {
            $debug = getenv('DIRECTIVE_DEBUG') === 'true';
            if ($debug) {
                fwrite(STDERR, "[DEBUG] Skipping {$class}: does not implement " . DirectiveInterface::class . "\n");
            }
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
            $debug = getenv('DIRECTIVE_DEBUG') === 'true';
            if ($debug) {
                fwrite(STDERR, "[DEBUG] Failed to extract metadata for {$class}: " . $e->getMessage() . "\n");
            }
            return null;
        }
    }

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
}
