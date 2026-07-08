<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;
use AndyDefer\Directive\Contracts\Services\ComposerReaderInterface;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use RuntimeException;

final class ComposerReaderService implements ComposerReaderInterface
{
    private ?array $composerData = null;

    public function __construct(
        private readonly DirectiveConfigInterface $config,
        private readonly FileSystemInterface $fileSystem,
    ) {}

    private function getComposerData(): array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $composerPath = $this->config->getComposerPath();

        if (! $this->fileSystem->exists($composerPath)) {
            throw new RuntimeException("composer.json not found at: {$composerPath}");
        }

        try {
            $content = $this->fileSystem->get($composerPath);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Could not read composer.json at: {$composerPath}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in composer.json: '.json_last_error_msg());
        }

        $this->composerData = $data;

        return $this->composerData;
    }

    public function getRequire(): array
    {
        $data = $this->getComposerData();

        return $data['require'] ?? [];
    }

    public function getRequireDev(): array
    {
        $data = $this->getComposerData();

        return $data['require-dev'] ?? [];
    }

    public function getAllDependencies(): array
    {
        return array_merge(
            $this->getRequire(),
            $this->getRequireDev()
        );
    }

    public function getVendorDirectories(): array
    {
        $dependencies = $this->getRequire();

        $vendors = [];
        foreach ($dependencies as $package => $version) {
            if (str_starts_with($package, 'php')) {
                continue;
            }

            $parts = explode('/', $package);
            if (count($parts) === 2) {
                $vendors[] = $parts[0];
            }
        }

        return array_unique($vendors);
    }

    public function getPackageNames(): array
    {
        $dependencies = $this->getRequire();

        $packages = [];
        foreach ($dependencies as $package => $version) {
            if (str_starts_with($package, 'php')) {
                continue;
            }

            $packages[] = $package;
        }

        return $packages;
    }

    public function hasPackage(string $packageName): bool
    {
        $dependencies = $this->getAllDependencies();

        return isset($dependencies[$packageName]);
    }

    public function getPackageVersion(string $packageName): ?string
    {
        $dependencies = $this->getAllDependencies();

        return $dependencies[$packageName] ?? null;
    }

    public function getAutoload(): array
    {
        $data = $this->getComposerData();

        return $data['autoload'] ?? [];
    }

    public function getAutoloadDev(): array
    {
        $data = $this->getComposerData();

        return $data['autoload-dev'] ?? [];
    }

    public function getVendorDir(): string
    {
        return $this->config->getVendorDir();
    }
}
