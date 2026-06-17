<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Records\PathSegmentsRecord;

/**
 * Service for building file paths and namespaces from path segments.
 *
 * Handles the conversion of logical path structures to:
 * - Absolute file system paths
 * - PHP namespaces
 * - File names with proper extensions
 *
 * @author Andy Defer
 */
class PathBuilderService
{
    public function __construct(
        private readonly FileCreatorConfigInterface $config
    ) {}

    /**
     * Build an absolute file path from a base directory and path segments.
     *
     * @param  string  $baseDirectory  Base directory relative to working directory
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @param  string|null  $extension  File extension (defaults to '.php')
     * @return string Absolute file path
     *
     * @example
     * $builder->buildFilePath('/src/Tasks', $segments);
     * // Returns: "/var/www/src/Tasks/Admin/UserTask.php"
     */
    public function buildFilePath(
        string $baseDirectory,
        PathSegmentsRecord $segments,
        ?string $extension = null
    ): string {
        $workingDir = rtrim($this->config->workingDirectory(), '/');
        $directory = rtrim($workingDir.$baseDirectory, '/');

        if ($segments->subPath !== '') {
            $directory .= '/'.$segments->subPath;
        }

        $ext = $extension ?? $this->config->fileExtension() ?? '.php';
        $ext = str_starts_with($ext, '.') ? $ext : '.'.$ext;

        return $directory.'/'.$segments->className.$ext;
    }

    /**
     * Build a PHP namespace from a base namespace and path segments.
     *
     * @param  string  $baseNamespace  Base namespace (e.g., "App\\Tasks")
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @return string Complete namespace with subpaths
     *
     * @example
     * $builder->buildNamespace('App\\Tasks', $segments);
     * // Returns: "App\\Tasks\\Admin\\User"
     */
    public function buildNamespace(
        string $baseNamespace,
        PathSegmentsRecord $segments
    ): string {
        if ($segments->subPath === '') {
            return $baseNamespace;
        }

        return $baseNamespace.'\\'.str_replace('/', '\\', $segments->subPath);
    }

    /**
     * Build a relative path from base directory and segments.
     *
     * @param  string  $baseDirectory  Base directory
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @param  string|null  $extension  File extension
     * @return string Relative file path
     */
    public function buildRelativePath(
        string $baseDirectory,
        PathSegmentsRecord $segments,
        ?string $extension = null
    ): string {
        $directory = rtrim($baseDirectory, '/');

        if ($segments->subPath !== '') {
            $directory .= '/'.$segments->subPath;
        }

        $ext = $extension ?? $this->config->fileExtension() ?? '.php';
        $ext = str_starts_with($ext, '.') ? $ext : '.'.$ext;

        return $directory.'/'.$segments->className.$ext;
    }

    /**
     * Build a directory path (without filename).
     *
     * @param  string  $baseDirectory  Base directory
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @return string Directory path
     */
    public function buildDirectoryPath(
        string $baseDirectory,
        PathSegmentsRecord $segments
    ): string {
        $workingDir = rtrim($this->config->workingDirectory(), '/');
        $directory = rtrim($workingDir.$baseDirectory, '/');

        if ($segments->subPath !== '') {
            $directory .= '/'.$segments->subPath;
        }

        return $directory;
    }

    /**
     * Get the fully qualified class name (with namespace).
     *
     * @param  string  $baseNamespace  Base namespace
     * @param  PathSegmentsRecord  $segments  Path segments record
     * @return string Fully qualified class name
     */
    public function buildFullyQualifiedClassName(
        string $baseNamespace,
        PathSegmentsRecord $segments
    ): string {
        $namespace = $this->buildNamespace($baseNamespace, $segments);

        return $namespace.'\\'.$segments->className;
    }
}
