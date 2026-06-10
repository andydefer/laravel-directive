<?php

// src/Contexts/DirectiveDiscoveryContext.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;

/**
 * Context responsible for managing directive discovery state.
 *
 * This context handles:
 * - Bootstrapped flag for Laravel
 * - Scanned packages cache
 * - Project root and vendor directory paths
 * - Custom loader instance
 */
final class DirectiveDiscoveryContext
{
    private bool $bootstrapped = false;
    private ?DirectiveLoaderInterface $loader = null;
    private string $projectRoot;
    private string $vendorDir;
    private array $scannedPackages = [];

    public function __construct()
    {
        $this->projectRoot = getcwd();
        $this->vendorDir = $this->projectRoot . '/vendor';
    }

    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    public function setBootstrapped(bool $bootstrapped): void
    {
        $this->bootstrapped = $bootstrapped;
    }

    public function getLoader(): ?DirectiveLoaderInterface
    {
        return $this->loader;
    }

    public function setLoader(?DirectiveLoaderInterface $loader): void
    {
        $this->loader = $loader;
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getVendorDir(): string
    {
        return $this->vendorDir;
    }

    public function isPackageScanned(string $packageName): bool
    {
        return isset($this->scannedPackages[$packageName]);
    }

    public function markPackageAsScanned(string $packageName): void
    {
        $this->scannedPackages[$packageName] = true;
    }

    public function resetScannedPackages(): void
    {
        $this->scannedPackages = [];
    }

    public function reset(): void
    {
        $this->bootstrapped = false;
        $this->loader = null;
        $this->scannedPackages = [];
    }
}
