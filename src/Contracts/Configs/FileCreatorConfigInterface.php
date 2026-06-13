<?php

// src/Contracts/Configs/FileCreatorConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

use AndyDefer\PhpServices\Enums\PermissionMode;

interface FileCreatorConfigInterface
{
    public function directoryPermission(): PermissionMode;

    public function filePermission(): PermissionMode;

    public function workingDirectory(): string;

    public function defaultForce(): bool;

    public function fileExtension(): string;
}
