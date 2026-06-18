<?php

// src/Sources/VendorDiscoverySource.php

declare(strict_types=1);

namespace AndyDefer\Directive\Sources;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Services\DirectiveMetadataExtractorService;

final class VendorDiscoverySource implements DiscoverySourceInterface
{
    private array $scannedPackages = [];

    public function __construct(
        private readonly string $projectRoot,
        private readonly DirectiveMetadataExtractorService $extractor,
    ) {}

    public function discover(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;
        $composerFile = $this->projectRoot.'/composer.json';

        if (! file_exists($composerFile)) {
            return $results;
        }

        $composer = json_decode(file_get_contents($composerFile), true);
        $packages = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        $this->scannedPackages = [];

        foreach ($packages as $packageName => $version) {
            $this->scanPackage($results, $packageName, 1);
        }

        return $results;
    }

    private function scanPackage(DirectiveMetadataCollection $results, string $packageName, int $depth): void
    {
        if (isset($this->scannedPackages[$packageName]) || $depth > 2) {
            return;
        }

        if (str_starts_with($packageName, 'php') || $packageName === 'php') {
            return;
        }

        $packagePath = $this->projectRoot.'/vendor/'.$packageName;

        if (! is_dir($packagePath)) {
            return;
        }

        $this->scannedPackages[$packageName] = true;

        $possiblePaths = [
            $packagePath.'/src/Directives',
            $packagePath.'/Directives',
            $packagePath.'/src/Directive',
            $packagePath.'/Directive',
        ];

        foreach ($possiblePaths as $directivesPath) {
            if (is_dir($directivesPath)) {
                $files = glob($directivesPath.'/*.php');
                if ($files !== false) {
                    foreach ($files as $file) {
                        $metadata = $this->extractor->extractFromFile($file);
                        if ($metadata !== null) {
                            $results->add($metadata);
                        }
                    }
                }
            }
        }

        if ($depth === 1) {
            $this->scanDependencies($results, $packagePath, $depth);
        }
    }

    private function scanDependencies(DirectiveMetadataCollection $results, string $packagePath, int $currentDepth): void
    {
        $composerFile = $packagePath.'/composer.json';

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

    public function getName(): string
    {
        return 'vendor';
    }
}
