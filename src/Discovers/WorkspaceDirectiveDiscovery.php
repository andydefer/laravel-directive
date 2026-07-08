<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

final class WorkspaceDirectiveDiscovery implements DiscoverySourceInterface
{
    public function __construct(
        private readonly FileSystemInterface $fileSystem,
        private readonly DirectiveScannerInterface $scanner,
        private readonly int $maxDepth = 3,
    ) {}

    public function discover(): array
    {
        $fqcns = [];

        $paths = [
            getcwd().'/src/Directives',
            getcwd().'/app/Directives',
        ];

        foreach ($paths as $path) {
            if (! $this->fileSystem->isDirectory($path)) {
                continue;
            }

            $fqcns = array_merge($fqcns, $this->scanner->scan($path, $this->maxDepth));
        }

        return $fqcns;
    }
}
