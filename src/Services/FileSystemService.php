<?php

// src/Services/FileSystemService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Services\FileSystemInterface;
use AndyDefer\Directive\Enums\PermissionMode;

/**
 * Native PHP implementation of the file system interface.
 *
 * This implementation uses only PHP built-in functions and has no external
 * dependencies, making it suitable for any PHP project regardless of the
 * framework being used.
 *
 * @author Andy Defer
 */
final class FileSystemService implements FileSystemInterface
{
    /**
     * {@inheritDoc}
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $path): string
    {
        if (! $this->exists($path)) {
            throw new \RuntimeException(sprintf('File does not exist at path: %s', $path));
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read file at path: %s', $path));
        }

        return $content;
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $path, string $content): int|false
    {
        $this->ensureDirectoryExists(dirname($path));

        return file_put_contents($path, $content);
    }

    /**
     * {@inheritDoc}
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * {@inheritDoc}
     */
    public function makeDirectory(string $path, PermissionMode $mode = PermissionMode::DIRECTORY, bool $recursive = true): bool
    {
        if ($this->isDirectory($path)) {
            return true;
        }

        return mkdir($path, $mode->value(), $recursive);
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param  string  $path  Directory path to check/create
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (! $this->isDirectory($path)) {
            $this->makeDirectory($path, PermissionMode::DIRECTORY, true);
        }
    }
}
