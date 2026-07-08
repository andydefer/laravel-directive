<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

interface ComposerReaderInterface
{
    public function getRequire(): array;

    public function getRequireDev(): array;

    public function getAllDependencies(): array;

    public function getVendorDirectories(): array;

    public function getPackageNames(): array;

    public function hasPackage(string $packageName): bool;

    public function getPackageVersion(string $packageName): ?string;

    public function getAutoload(): array;

    public function getAutoloadDev(): array;

    public function getVendorDir(): string;
}
