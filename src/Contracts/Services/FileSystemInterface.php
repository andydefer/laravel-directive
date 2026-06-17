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
 *
 * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface from php-services package instead.
 *             This interface will be removed in version 3.0.0.
 * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface
 */
interface FileSystemInterface
{
    /**
     * Determine if a file or directory exists at the given path.
     *
     * @param  string  $path  The path to check
     * @return bool True if the path exists, false otherwise
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::exists() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::exists()
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     *
     * @param  string  $path  Path to the file
     * @return string The file contents
     *
     * @throws \RuntimeException If the file cannot be read or does not exist
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::get() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::get()
     */
    public function get(string $path): string;

    /**
     * Write the contents to a file.
     *
     * @param  string  $path  Destination path where the file should be created
     * @param  string  $content  Content to write to the file
     * @return int|false Number of bytes written, or false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::put() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::put()
     */
    public function put(string $path, string $content): int|false;

    /**
     * Append content to a file.
     *
     * @param  string  $path  Path to the file
     * @param  string  $content  Content to append
     * @return int|false Number of bytes written, or false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::append() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::append()
     */
    public function append(string $path, string $content): int|false;

    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is a directory, false otherwise
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::isDirectory() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::isDirectory()
     */
    public function isDirectory(string $path): bool;

    /**
     * Determine if the given path is a file.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is a file, false otherwise
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::isFile() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::isFile()
     */
    public function isFile(string $path): bool;

    /**
     * Determine if the given path is readable.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is readable, false otherwise
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::isReadable() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::isReadable()
     */
    public function isReadable(string $path): bool;

    /**
     * Determine if the given path is writable.
     *
     * @param  string  $path  Path to check
     * @return bool True if the path is writable, false otherwise
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::isWritable() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::isWritable()
     */
    public function isWritable(string $path): bool;

    /**
     * Create a directory.
     *
     * @param  string  $path  Directory path to create
     * @param  PermissionMode  $mode  Directory permissions (default: PermissionMode::DIRECTORY)
     * @param  bool  $recursive  Create parent directories if needed (default: true)
     * @return bool True on success, false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::makeDirectory() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::makeDirectory()
     */
    public function makeDirectory(string $path, PermissionMode $mode = PermissionMode::DIRECTORY, bool $recursive = true): bool;

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param  string  $path  Directory path to check/create
     *
     * @throws \RuntimeException If directory cannot be created
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::ensureDirectoryExists() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::ensureDirectoryExists()
     */
    public function ensureDirectoryExists(string $path): void;

    /**
     * Copy a file from source to destination.
     *
     * @param  string  $source  Source file path
     * @param  string  $destination  Destination file path
     * @return bool True on success, false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::copy() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::copy()
     */
    public function copy(string $source, string $destination): bool;

    /**
     * Move/Rename a file or directory.
     *
     * @param  string  $source  Source path
     * @param  string  $destination  Destination path
     * @return bool True on success, false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::move() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::move()
     */
    public function move(string $source, string $destination): bool;

    /**
     * Find pathnames matching a pattern.
     *
     * @param  string  $pattern  The pattern to match (glob syntax)
     * @param  int  $flags  Optional flags (GLOB_MARK, GLOB_NOSORT, etc.)
     * @return array<int, string> Array of matching pathnames, empty array if no matches
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::glob() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::glob()
     */
    public function glob(string $pattern, int $flags = 0): array;

    /**
     * Delete a file or directory.
     *
     * @param  string  $path  Path to the file or directory to delete
     * @return bool True on success, false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::delete() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::delete()
     */
    public function delete(string $path): bool;

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param  string  $directory  Path to the directory to delete
     * @return bool True on success, false on failure
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::deleteDirectory() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::deleteDirectory()
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Get the size of a file in bytes.
     *
     * @param  string  $path  Path to the file
     * @return int File size in bytes
     *
     * @throws \RuntimeException If file does not exist or cannot be read
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::size() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::size()
     */
    public function size(string $path): int;

    /**
     * Get the last modified time of a file.
     *
     * @param  string  $path  Path to the file
     * @return int Unix timestamp of last modification
     *
     * @throws \RuntimeException If file does not exist
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::lastModified() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::lastModified()
     */
    public function lastModified(string $path): int;

    /**
     * Get the file extension.
     *
     * @param  string  $path  Path to the file
     * @return string File extension (without dot), empty string if none
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::extension() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::extension()
     */
    public function extension(string $path): string;

    /**
     * Get the basename of a path.
     *
     * @param  string  $path  Path to the file
     * @return string Basename of the path
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::basename() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::basename()
     */
    public function basename(string $path): string;

    /**
     * Get the directory name of a path.
     *
     * @param  string  $path  Path to the file
     * @return string Directory name
     *
     * @deprecated Use AndyDefer\PhpServices\Contracts\FileSystemInterface::dirname() instead
     * @see \AndyDefer\PhpServices\Contracts\FileSystemInterface::dirname()
     */
    public function dirname(string $path): string;
}
