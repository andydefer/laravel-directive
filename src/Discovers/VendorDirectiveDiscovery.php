<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\Directive\Contracts\Services\DependencyResolverInterface;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

final class VendorDirectiveDiscovery implements DiscoverySourceInterface
{
    public function __construct(
        private readonly ComposerReaderInterface $composerReader,
        private readonly DependencyResolverInterface $dependencyResolver,
        private readonly FileSystemInterface $fileSystem,
        private readonly DirectiveClassScanner $scanner,
        private readonly int $maxDepth = 3,
    ) {}

    public function discover(): array
    {
        $fqcns = [];

        $packages = $this->dependencyResolver->getFlatDependencies()->toArray();

        foreach ($packages as $package) {
            $fqcns = array_merge($fqcns, $this->scanPackage($package));
        }

        return $fqcns;
    }

    private function scanPackage(string $package): array
    {
        $fqcns = [];
        $packagePath = $this->composerReader->getVendorDir().'/'.$package;

        if (! $this->fileSystem->isDirectory($packagePath)) {
            return $fqcns;
        }

        $composerPath = $packagePath.'/composer.json';

        if (! $this->fileSystem->exists($composerPath)) {
            return $fqcns;
        }

        try {
            $content = $this->fileSystem->get($composerPath);
        } catch (\RuntimeException $e) {
            return $fqcns;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $fqcns;
        }

        $autoload = $data['autoload'] ?? [];
        $psr4 = $autoload['psr-4'] ?? [];

        // Scanner les chemins PSR-4
        foreach ($psr4 as $namespace => $path) {
            if (! is_string($path)) {
                continue;
            }

            $fullPath = $packagePath.'/'.rtrim($path, '/').'/Directives';

            if ($this->fileSystem->isDirectory($fullPath)) {
                $fqcns = array_merge($fqcns, $this->scanner->scan($fullPath, $this->maxDepth));
            }
        }

        // Lire le fichier config/directive.php du package vendor s'il existe
        $packageConfigPath = $packagePath.'/config/directive.php';
        if ($this->fileSystem->exists($packageConfigPath)) {
            try {
                $configContent = $this->fileSystem->get($packageConfigPath);
                $configData = eval('?>'.$configContent);

                if (is_array($configData) && isset($configData['custom_sources'])) {
                    $customSources = is_array($configData['custom_sources']) ? $configData['custom_sources'] : [];

                    foreach ($customSources as $source) {
                        $fullPath = $packagePath.'/'.ltrim($source, '/');

                        if (! $this->fileSystem->isDirectory($fullPath)) {
                            continue;
                        }

                        $fqcns = array_merge($fqcns, $this->scanner->scan($fullPath, $this->maxDepth));
                    }
                }
            } catch (\RuntimeException $e) {
                // Ignorer les erreurs de lecture du fichier config
            }
        }

        return $fqcns;
    }
}
