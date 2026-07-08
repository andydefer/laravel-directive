<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

interface DependencyResolverInterface
{
    public function resolveAll(): array;

    public function resolvePackageDependencies(string $package): array;

    public function getDependencyTree(): array;

    public function getFlatDependencies(): StringTypedCollection;

    public function hasCircularDependency(): bool;
}
