<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;

class DirectiveDiscoveryService
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;
    private static bool $bootstrapped = false;

    public function __construct(
        private readonly DirectiveConfig $config,
        private readonly DirectiveHydratorService $hydrator,
        private readonly ?DirectiveRegistrarInterface $registrar = null,
    ) {}

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    public function discover(): TypedCollection
    {
        $results = new TypedCollection(DirectiveMetadataRecord::class);

        $results = $this->discoverFromFilesystem($results);

        if ($this->registrar !== null) {
            $results = $this->discoverFromRegistrar($results);
        }

        return $results;
    }

    private function discoverFromFilesystem(TypedCollection $results): TypedCollection
    {
        $path = $this->config->directivesPath;

        if ($path === '' || !is_dir($path)) {
            return $results;
        }

        $files = glob($path . '/*.php');

        if ($files === false) {
            return $results;
        }

        foreach ($files as $file) {
            $metadata = $this->extractMetadataFromFile($file);
            if ($metadata !== null) {
                $results->add($metadata);
            }
        }

        return $results;
    }

    private function discoverFromRegistrar(TypedCollection $results): TypedCollection
    {
        $registeredClasses = $this->registrar->getRegistered();

        foreach ($registeredClasses as $class) {
            $metadata = $this->extractMetadataFromClass($class);
            if ($metadata !== null) {
                $results->add($metadata);
            }
        }

        return $results;
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

        if ($reflection->isAbstract()) {
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
