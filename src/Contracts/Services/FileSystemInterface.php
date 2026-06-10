<?php

// src/Contracts/Services/FileSystemInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

use AndyDefer\Directive\Enums\PermissionMode;

/**
 * Interface for file system operations.
 *
 * This interface provides a minimal set of methods needed for file creation
 * and manipulation, decoupling the package from any specific framework
 * implementation.
 *
 * @author Andy Defer
 */
interface FileSystemInterface
{
    /**
     * Determine if a file or directory exists at the given path.
     *
     * @param  string  $path  The path to check
     * @return bool True if the path exists, false otherwise
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     *
     * @param  string  $path  Path to the file
     * @return string The file contents
     *
     * @throws \RuntimeException If the file cannot be read or does not exist
     */
    public function get(string $path): string;

    /**
     * Write the contents to a file.
     *
     * @param  string  $path  Destination path where the file should be created
     * @param  string  $content  Content to write to the file
     * @return int|false Number of bytes written, or false on failure
     */
    public function put(string $path, string $content): int|false;

    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is a directory, false otherwise
     */
    public function isDirectory(string $path): bool;

    /**
     * Create a directory.
     *
     * @param  string  $path  Directory path to create
     * @param  PermissionMode  $mode  Directory permissions (default: PermissionMode::DIRECTORY)
     * @param  bool  $recursive  Create parent directories if needed (default: true)
     * @return bool True on success, false on failure
     */
    public function makeDirectory(string $path, PermissionMode $mode = PermissionMode::DIRECTORY, bool $recursive = true): bool;
}
