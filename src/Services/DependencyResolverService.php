<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\Directive\Contracts\Services\DependencyResolverInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;

final class DependencyResolverService implements DependencyResolverInterface
{
    private array $resolved = [];

    private array $visited = [];

    public function __construct(
        private readonly ComposerReaderInterface $composerReader,
        private readonly FileSystemInterface $fileSystem,
        private readonly int $maxDepth = 3,
    ) {}

    public function resolveAll(): array
    {
        $this->resolved = [];
        $this->visited = [];

        foreach ($this->composerReader->getRequire() as $package => $version) {
            if (! str_starts_with($package, 'php')) {
                $this->resolvePackage($package, 0);
            }
        }

        return $this->resolved;
    }

    private function resolvePackage(string $package, int $depth): void
    {
        if ($depth > $this->maxDepth || in_array($package, $this->visited, true)) {
            return;
        }

        $this->visited[] = $package;

        $composerPath = $this->composerReader->getVendorDir().'/'.$package.'/composer.json';

        if (! $this->fileSystem->exists($composerPath)) {
            return;
        }

        try {
            $content = $this->fileSystem->get($composerPath);
        } catch (\RuntimeException $e) {
            return;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        $this->resolved[$package] = $data;

        foreach ($data['require'] ?? [] as $dependency => $version) {
            if (! str_starts_with($dependency, 'php') && ! in_array($dependency, $this->visited, true)) {
                $this->resolvePackage($dependency, $depth + 1);
            }
        }
    }

    public function resolvePackageDependencies(string $package): array
    {
        $this->resolved = [];
        $this->visited = [];

        $this->resolvePackage($package, 0);

        return $this->resolved;
    }

    public function getDependencyTree(): array
    {
        $tree = [];

        foreach ($this->composerReader->getRequire() as $package => $version) {
            if (! str_starts_with($package, 'php')) {
                $tree[$package] = $this->buildTree($package, 0);
            }
        }

        return $tree;
    }

    private function buildTree(string $package, int $depth): array
    {
        if ($depth > $this->maxDepth) {
            return ['__truncated__' => true];
        }

        $composerPath = $this->composerReader->getVendorDir().'/'.$package.'/composer.json';

        if (! $this->fileSystem->exists($composerPath)) {
            return [];
        }

        try {
            $content = $this->fileSystem->get($composerPath);
        } catch (\RuntimeException $e) {
            return [];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $tree = [];

        foreach ($data['require'] ?? [] as $dependency => $version) {
            if (! str_starts_with($dependency, 'php')) {
                $tree[$dependency] = $this->buildTree($dependency, $depth + 1);
            }
        }

        return $tree;
    }

    public function getFlatDependencies(): StringTypedCollection
    {
        $packages = new StringTypedCollection;

        foreach (array_keys($this->resolveAll()) as $package) {
            $packages->add($package);
        }

        return $packages;
    }

    public function hasCircularDependency(): bool
    {
        foreach ($this->composerReader->getRequire() as $package => $version) {
            if (! str_starts_with($package, 'php')) {
                $this->visited = [];
                $this->resolved = [];

                if ($this->detectCycle($package, [], 0)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function detectCycle(string $package, array $path, int $depth): bool
    {
        if ($depth > $this->maxDepth) {
            return false;
        }

        if (in_array($package, $path, true)) {
            return true;
        }

        $path[] = $package;

        $composerPath = $this->composerReader->getVendorDir().'/'.$package.'/composer.json';

        if (! $this->fileSystem->exists($composerPath)) {
            return false;
        }

        try {
            $content = $this->fileSystem->get($composerPath);
        } catch (\RuntimeException $e) {
            return false;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        foreach ($data['require'] ?? [] as $dependency => $version) {
            if (! str_starts_with($dependency, 'php')) {
                if ($this->detectCycle($dependency, $path, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }
}
