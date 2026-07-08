<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

interface DirectiveConfigInterface
{
    public function basePath(): string;

    public function getDirectories(): array;

    public function getReservedSignatures(): array;

    public function getVendorDir(): string;

    public function getComposerPath(): string;

    public function isDebug(): bool;

    public function getMaxDepth(): int;

    public function getCustomSources(): array;

    public function setReservedSignatures(array $signatures): void;
}
