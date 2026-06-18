<?php

// src/Services/DirectiveMetadataExtractorService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;

final class DirectiveMetadataExtractorService
{
    public function __construct(
        private readonly DirectiveHydratorService $hydrator,
    ) {}

    public function extractFromFile(string $file): ?DirectiveMetadataRecord
    {
        $class = $this->getClassFromFile($file);

        if ($class === '' || ! class_exists($class)) {
            return null;
        }

        return $this->extractFromClass($class);
    }

    public function extractFromClass(string $class): ?DirectiveMetadataRecord
    {
        $reflection = new \ReflectionClass($class);

        if (
            $reflection->isAbstract() ||
            ! is_subclass_of($class, AbstractDirective::class) ||
            ! is_subclass_of($class, DirectiveInterface::class)
        ) {
            return null;
        }

        try {
            $blueprint = $this->hydrator->hydrateBlueprint($class);
            $directive = $this->hydrator->hydrateForAliases($class);

            return new DirectiveMetadataRecord(
                signature: $blueprint->signature,
                class: $blueprint->class,
                description: $blueprint->description,
                aliases: $directive->getAliases(),
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

        return $namespace === '' ? $class : $namespace.'\\'.$class;
    }
}
