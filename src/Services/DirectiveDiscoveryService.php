<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;

class DirectiveDiscoveryService
{
    public function __construct(
        private readonly DirectiveConfig $config,
        private readonly DirectiveHydratorService $hydrator,
    ) {}

    public function discover(): TypedCollection
    {
        $results = new TypedCollection(DirectiveMetadataRecord::class);

        if (! is_dir($this->config->directivesPath)) {
            return $results;
        }

        $files = glob($this->config->directivesPath . '/*.php');

        foreach ($files as $file) {
            $metadata = $this->extractMetadata($file);
            if ($metadata !== null) {
                $results->add($metadata);
            } else {
            }
        }

        return $results;
    }

    private function extractMetadata(string $file): ?DirectiveMetadataRecord
    {
        $class = $this->getClassFromFile($file);

        if (! class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        if (! is_subclass_of($class, DirectiveInterface::class)) {
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

    private function getClassFromFile(string $file): string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        preg_match('/namespace\s+([^;]+);/', $content, $match);
        $namespace = $match[1] ?? '';
        $class = basename($file, '.php');

        $fullClass = $namespace ? $namespace . '\\' . $class : $class;

        return $fullClass;
    }
}
