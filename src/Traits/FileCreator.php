<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Traits;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Services\FileCreatorService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

/**
 * @deprecated This trait is deprecated since version 2.0.0 and will be removed in 3.0.0.
 *
 * Reason: Traits create implicit coupling and are difficult to test.
 * The same functionality is now available via the FileCreatorService.
 *
 * Migration example:
 *
 * // ❌ Old approach (deprecated)
 * class MyDirective extends AbstractDirective
 * {
 *     use FileCreator;
 *
 *     public function execute(): ExitCode
 *     {
 *         $this->initFileCreator();
 *         $this->createFile('/stub.stub', '/dest.php', ['{{name}}' => 'Value']);
 *     }
 * }
 *
 * // ✅ New approach (recommended) - Dependency Injection
 * use AndyDefer\Directive\Services\FileCreatorService;
 * use AndyDefer\Directive\Configs\FileCreatorConfig;
 * AndyDefer\PhpServices\Services\FileSystemService;
 *
 * class MyDirective extends AbstractDirective
 * {
 *     public function __construct(
 *         private readonly FileCreatorService $fileCreator
 *     ) {
 *         parent::__construct();
 *     }
 *
 *     public function execute(): ExitCode
 *     {
 *         $context = new FileCreationContext();
 *         $replacements = new ReplacementCollection();
 *         $replacements->addReplacement('{{name}}', 'Value');
 *
 *         $result = $this->fileCreator->createFile(
 *             '/stub.stub',
 *             '/dest.php',
 *             $replacements,
 *             $context
 *         );
 *
 *         if (!$result->success) {
 *             $this->error($result->message);
 *             return ExitCode::FAILURE;
 *         }
 *
 *         return ExitCode::SUCCESS;
 *     }
 * }
 *
 * // In your service provider:
 * $this->app->bind(FileCreatorService::class, function ($app) {
 *     return new FileCreatorService(new FileCreatorConfig());
 * });
 *
 * @author Andy Defer
 *
 * @deprecated since 2.0.0, will be removed in 3.0.0
 */
trait FileCreator
{
    private Filesystem $files;

    /**
     * @deprecated Use FileCreatorService injected in constructor instead
     */
    protected function initFileCreator(): void
    {
        @trigger_error(
            sprintf(
                '%s::initFileCreator() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Inject FileCreatorService in your constructor instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        $this->files = new Filesystem;
    }

    /**
     * Create a file from stub with variables replacement
     *
     * @deprecated Use FileCreatorService::createFile() instead
     *
     * @param  string  $stubPath  Path to the stub template file
     * @param  string  $destinationPath  Path where the file should be created
     * @param  array<string, string>  $replacements  Placeholder-value pairs
     * @param  bool  $force  Overwrite existing file if true
     * @return bool True on success, false on failure
     */
    protected function createFile(
        string $stubPath,
        string $destinationPath,
        array $replacements,
        bool $force = false
    ): bool {
        @trigger_error(
            sprintf(
                '%s::createFile() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Use FileCreatorService::createFile() instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        // Check if file already exists
        if ($this->files->exists($destinationPath) && ! $force) {
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
     *
     * @deprecated This method is deprecated and will be removed with the trait
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    /**
     * Convert kebab-case or snake_case to PascalCase
     *
     * @deprecated Use FileCreatorService::toPascalCase() instead
     *
     * @param  string  $string  Input string in kebab-case or snake_case
     * @return string Converted string in PascalCase
     */
    protected function toPascalCase(string $string): string
    {
        @trigger_error(
            sprintf(
                '%s::toPascalCase() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Use FileCreatorService::toPascalCase() instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);

        return str_replace(' ', '', $string);
    }

    /**
     * Convert string to kebab-case
     *
     * @deprecated Use FileCreatorService::toKebabCase() instead
     *
     * @param  string  $string  Input string in PascalCase
     * @return string Converted string in kebab-case
     */
    protected function toKebabCase(string $string): string
    {
        @trigger_error(
            sprintf(
                '%s::toKebabCase() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Use FileCreatorService::toKebabCase() instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    /**
     * Extract segments from path (supports subdirectories)
     *
     * @deprecated Use FileCreatorService::extractPathSegments() instead
     *
     * @param  string  $name  Path string with segments separated by slashes
     * @return array{segments: array, className: string, subPath: string, fullPath: string}
     */
    protected function extractPathSegments(string $name): array
    {
        @trigger_error(
            sprintf(
                '%s::extractPathSegments() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Use FileCreatorService::extractPathSegments() instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        $segments = explode('/', $name);
        $className = array_pop($segments);
        $subPath = ! empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
            'fullPath' => $subPath ? $subPath.'/'.$className : $className,
        ];
    }

    /**
     * Build namespace from subpath
     *
     * @deprecated Use FileCreatorService::buildNamespace() instead
     *
     * @param  string  $baseNamespace  Base namespace
     * @param  string  $subPath  Subdirectory path
     * @return string Complete namespace
     */
    protected function buildNamespace(string $baseNamespace, string $subPath): string
    {
        @trigger_error(
            sprintf(
                '%s::buildNamespace() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Use FileCreatorService::buildNamespace() instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        if (! $subPath) {
            return $baseNamespace;
        }

        return $baseNamespace.'\\'.str_replace('/', '\\', $subPath);
    }

    /**
     * Get absolute path for a file in app directory
     *
     * @deprecated Use FileCreatorService::getAppPath() instead
     *
     * @param  string  $baseDir  Base directory
     * @param  string  $className  Class name
     * @param  string  $subPath  Subdirectory path
     * @return string Absolute file path
     */
    protected function getAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        @trigger_error(
            sprintf(
                '%s::getAppPath() is deprecated since version 2.0.0. '.
                    'This method will be removed in 3.0.0. '.
                    'Use FileCreatorService::getAppPath() instead.',
                static::class
            ),
            E_USER_DEPRECATED
        );

        $directory = getcwd().$baseDir;
        if ($subPath) {
            $directory .= $subPath.'/';
        }

        return $directory.$className.'.php';
    }

    /**
     * Display an error message.
     *
     * This method must be implemented by the class using this trait.
     * In AbstractDirective, this is already implemented.
     */
    abstract protected function error(string $message): void;
}
