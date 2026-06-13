<?php

// src/Configs/FileCreatorConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\DomainStructures\Services\EnumService;
use AndyDefer\PhpServices\Enums\PermissionMode;

final class FileCreatorConfig implements FileCreatorConfigInterface
{
    public function __construct(
        private EnumService $enumService
    ) {

        $this->enumService = new EnumService;
    }

    public function directoryPermission(): PermissionMode
    {
        $value = (int) (getenv('FILE_CREATOR_DIR_PERMISSION') ?: 0755);

        return $this->enumService->fromValue(PermissionMode::class, $value) ?? PermissionMode::DIRECTORY;
    }

    public function filePermission(): PermissionMode
    {
        $value = (int) (getenv('FILE_CREATOR_FILE_PERMISSION') ?: 0644);

        return $this->enumService->fromValue(PermissionMode::class, $value) ?? PermissionMode::PUBLIC_FILE;
    }

    public function workingDirectory(): string
    {
        return getenv('FILE_CREATOR_WORKING_DIR') ?: getcwd();
    }

    public function defaultForce(): bool
    {
        return getenv('FILE_CREATOR_DEFAULT_FORCE') === 'true';
    }

    public function fileExtension(): string
    {
        return getenv('FILE_CREATOR_FILE_EXTENSION') ?: '.php';
    }
}
