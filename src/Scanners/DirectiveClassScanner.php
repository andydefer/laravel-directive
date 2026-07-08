<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Scanners;

use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

final class DirectiveClassScanner implements DirectiveScannerInterface
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

            // Extraire le namespace
            $namespace = $this->extractNamespace($content);
            if ($namespace === null) {
                continue;
            }

            // Extraire le nom de la classe
            $className = $this->extractClassName($content);
            if ($className === null) {
                continue;
            }

            // Vérifier si la classe étend AbstractDirective (via le contenu du fichier)
            if (! $this->extendsAbstractDirective($content)) {
                continue;
            }

            // Vérifier si la classe est abstraite (via le contenu du fichier)
            if ($this->isAbstractClass($content)) {
                continue;
            }

            $fqcn = $namespace.'\\'.$className;
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

    private function extendsAbstractDirective(string $content): bool
    {
        // Vérifie si la classe étend AbstractDirective (avec ou sans namespace complet)
        return preg_match('/class\s+\w+\s+extends\s+(?:\\\\)?AbstractDirective/', $content) === 1 ||
               preg_match('/class\s+\w+\s+extends\s+(?:\\\\)?AndyDefer\\\\Directive\\\\AbstractDirective/', $content) === 1;
    }

    private function isAbstractClass(string $content): bool
    {
        // Vérifie si la classe est abstraite
        return preg_match('/abstract\s+class/', $content) === 1;
    }
}
