<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Traits;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

trait FileCreator
{
    private Filesystem $files;

    protected function initFileCreator(): void
    {
        $this->files = new Filesystem();
    }

    /**
     * Create a file from stub with variables replacement
     */
    protected function createFile(
        string $stubPath,
        string $destinationPath,
        array $replacements,
        bool $force = false
    ): bool {
        // Check if file already exists
        if ($this->files->exists($destinationPath) && !$force) {
            $this->error("File already exists: {$destinationPath}");
            return false;
        }

        // Create directory if it doesn't exist
        $this->ensureDirectoryExists(dirname($destinationPath));

        // Get stub content (catch exception if file not found)
        try {
            $stub = $this->files->get($stubPath);
        } catch (FileNotFoundException $e) {
            $this->error("Stub template not found at: {$stubPath}");
            return false;
        }

        // Replace variables
        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        // Write file
        if ($this->files->put($destinationPath, $content) === false) {
            $this->error("Cannot create file: {$destinationPath}");
            return false;
        }

        return true;
    }

    /**
     * Ensure directory exists, create if not
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Convert kebab-case or snake_case to PascalCase
     */
    protected function toPascalCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }

    /**
     * Convert string to kebab-case
     */
    protected function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    /**
     * Extract segments from path (supports subdirectories)
     */
    protected function extractPathSegments(string $name): array
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);
        $subPath = !empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
            'fullPath' => $subPath ? $subPath . '/' . $className : $className,
        ];
    }

    /**
     * Build namespace from subpath
     */
    protected function buildNamespace(string $baseNamespace, string $subPath): string
    {
        if (!$subPath) {
            return $baseNamespace;
        }
        return $baseNamespace . '\\' . str_replace('/', '\\', $subPath);
    }

    /**
     * Get absolute path for a file in app directory
     */
    protected function getAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        $directory = getcwd() . $baseDir;
        if ($subPath) {
            $directory .= $subPath . '/';
        }
        return $directory . $className . '.php';
    }
}
