<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;

/**
 * Service responsible for discovering directives from multiple sources.
 *
 * Sources:
 * 1. Filesystem (app/Directives/*.php)
 * 2. Registered directives from external packages via DirectiveRegistrar
 */
class DirectiveDiscoveryService
{
    public function __construct(
        private readonly DirectiveConfig $config,
        private readonly DirectiveHydratorService $hydrator,
        private readonly ?DirectiveRegistrarInterface $registrar = null,
    ) {}

    /**
     * Discover all directives from all sources.
     *
     * @return TypedCollection<DirectiveMetadataRecord> Collection of directive metadata
     */
    public function discover(): TypedCollection
    {
        $results = new TypedCollection(DirectiveMetadataRecord::class);

        $results = $this->discoverFromFilesystem($results);

        if ($this->registrar !== null) {
            $results = $this->discoverFromRegistrar($results);
        }

        return $results;
    }

    /**
     * Discover directives from filesystem (app/Directives/).
     *
     * @param TypedCollection<DirectiveMetadataRecord> $results Collection to add to
     *
     * @return TypedCollection<DirectiveMetadataRecord> Updated collection
     */
    private function discoverFromFilesystem(TypedCollection $results): TypedCollection
    {
        $path = $this->config->directivesPath;

        if ($path === '') {
            return $results;
        }

        if (!is_dir($path)) {
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

    /**
     * Discover directives from registered packages.
     *
     * @param TypedCollection<DirectiveMetadataRecord> $results Collection to add to
     *
     * @return TypedCollection<DirectiveMetadataRecord> Updated collection
     */
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

    /**
     * Extract metadata from a file.
     *
     * @param string $file Path to the PHP file
     *
     * @return DirectiveMetadataRecord|null Metadata record or null if invalid
     */
    private function extractMetadataFromFile(string $file): ?DirectiveMetadataRecord
    {
        $class = $this->getClassFromFile($file);

        if ($class === '') {
            return null;
        }

        if (!class_exists($class)) {
            return null;
        }

        return $this->extractMetadataFromClass($class);
    }

    /**
     * Extract metadata from a class.
     *
     * @param class-string $class Fully qualified class name
     *
     * @return DirectiveMetadataRecord|null Metadata record or null if invalid
     */
    private function extractMetadataFromClass(string $class): ?DirectiveMetadataRecord
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        if (!is_subclass_of($class, DirectiveInterface::class)) {
            return null;
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
     * Extract class name from file.
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
}
