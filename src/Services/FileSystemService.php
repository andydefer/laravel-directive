<?php

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
 * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService from php-services package instead.
 *             This service will be removed in version 3.0.0.
 * @see \AndyDefer\PhpServices\Services\FileSystemService
 */
class FileSystemService implements FileSystemInterface
{
    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::exists() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::exists()
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::get() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::get()
     */
    public function get(string $path): string
    {
        if (!$this->exists($path)) {
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
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::put() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::put()
     */
    public function put(string $path, string $content): int|false
    {
        $this->ensureDirectoryExists(dirname($path));
        return file_put_contents($path, $content);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::append() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::append()
     */
    public function append(string $path, string $content): int|false
    {
        $this->ensureDirectoryExists(dirname($path));
        return file_put_contents($path, $content, FILE_APPEND | LOCK_EX);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::isDirectory() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::isDirectory()
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::isFile() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::isFile()
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::isReadable() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::isReadable()
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::isWritable() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::isWritable()
     */
    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::makeDirectory() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::makeDirectory()
     */
    public function makeDirectory(string $path, PermissionMode $mode = PermissionMode::DIRECTORY, bool $recursive = true): bool
    {
        if ($this->isDirectory($path)) {
            return true;
        }

        return mkdir($path, $mode->value(), $recursive);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::ensureDirectoryExists() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::ensureDirectoryExists()
     */
    public function ensureDirectoryExists(string $path): void
    {
        if (!$this->isDirectory($path)) {
            if (!$this->makeDirectory($path, PermissionMode::DIRECTORY, true)) {
                throw new \RuntimeException(sprintf('Cannot create directory: %s', $path));
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::copy() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::copy()
     */
    public function copy(string $source, string $destination): bool
    {
        $this->ensureDirectoryExists(dirname($destination));
        return copy($source, $destination);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::move() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::move()
     */
    public function move(string $source, string $destination): bool
    {
        $this->ensureDirectoryExists(dirname($destination));
        return rename($source, $destination);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::glob() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::glob()
     */
    public function glob(string $pattern, int $flags = 0): array
    {
        $result = glob($pattern, $flags);
        return $result === false ? [] : $result;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::delete() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::delete()
     */
    public function delete(string $path): bool
    {
        if (!$this->exists($path)) {
            return true;
        }

        if ($this->isDirectory($path)) {
            return $this->deleteDirectory($path);
        }

        return unlink($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::deleteDirectory() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::deleteDirectory()
     */
    public function deleteDirectory(string $directory): bool
    {
        if (!$this->isDirectory($directory)) {
            return false;
        }

        $files = $this->glob($directory . '/*');

        foreach ($files as $file) {
            if ($this->isDirectory($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }

        return rmdir($directory);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::size() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::size()
     */
    public function size(string $path): int
    {
        if (!$this->exists($path)) {
            throw new \RuntimeException(sprintf('File does not exist: %s', $path));
        }

        $size = filesize($path);

        if ($size === false) {
            throw new \RuntimeException(sprintf('Cannot get file size: %s', $path));
        }

        return $size;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::lastModified() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::lastModified()
     */
    public function lastModified(string $path): int
    {
        if (!$this->exists($path)) {
            throw new \RuntimeException(sprintf('File does not exist: %s', $path));
        }

        $mtime = filemtime($path);

        if ($mtime === false) {
            throw new \RuntimeException(sprintf('Cannot get last modified time: %s', $path));
        }

        return $mtime;
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::extension() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::extension()
     */
    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::basename() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::basename()
     */
    public function basename(string $path): string
    {
        return basename($path);
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated Use AndyDefer\PhpServices\Services\FileSystemService::dirname() instead
     * @see \AndyDefer\PhpServices\Services\FileSystemService::dirname()
     */
    public function dirname(string $path): string
    {
        return dirname($path);
    }
}
