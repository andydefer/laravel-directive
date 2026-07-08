<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Scanners;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

final class DirectiveClassScanner
{
    public function __construct(
        private readonly FileSystemInterface $fileSystem,
    ) {}

    public function scan(string $directory, int $maxDepth = 3): array
    {
        $fqcns = [];

        if (! $this->fileSystem->isDirectory($directory)) {
            return $fqcns;
        }
        $this->scanDirectory($directory, $fqcns, 0, $maxDepth);

        return $fqcns;
    }

    private function scanDirectory(string $directory, array &$fqcns, int $currentDepth, int $maxDepth): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }

        $files = $this->fileSystem->glob($directory.'/*.php');

        foreach ($files as $file) {
            if (! $this->fileSystem->isFile($file)) {
                continue;
            }

            $content = $this->fileSystem->get($file);
            $className = $this->extractClassName($content);

            if ($className === null) {
                continue;
            }

            $namespace = $this->extractNamespace($content);

            if ($namespace === null) {
                continue;
            }

            $fqcn = $namespace.'\\'.$className;

            if (! class_exists($fqcn)) {
                continue;
            }

            $reflection = new \ReflectionClass($fqcn);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (! $reflection->isSubclassOf(AbstractDirective::class)) {
                continue;
            }

            $fqcns[] = $fqcn;
        }

        $subDirectories = $this->fileSystem->glob($directory.'/*', GLOB_ONLYDIR);

        foreach ($subDirectories as $subDirectory) {
            $this->scanDirectory($subDirectory, $fqcns, $currentDepth + 1, $maxDepth);
        }
    }

    private function extractClassName(string $content): ?string
    {
        if (preg_match('/class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\]+);/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
