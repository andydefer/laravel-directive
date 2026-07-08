<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use Illuminate\Foundation\Application;
use Throwable;

/**
 * Service for testing directives in an isolated environment.
 *
 * Creates a temporary directory with a minimal composer.json file,
 * changes the current working directory, and executes directives
 * in a sandboxed environment.
 */
final class DirectiveTestingService
{
    /**
     * The temporary directory path.
     */
    private string $tempDir;

    /**
     * The original working directory before testing.
     */
    private string $originalCwd;

    /**
     * The directive kernel instance.
     */
    private DirectiveKernel $kernel;

    /**
     * @param  Application  $app  The Laravel application instance
     * @param  array<int, string>  $sourcePaths  Additional source paths to scan
     */
    public function __construct(
        private readonly Application $app,
        private readonly array $sourcePaths = [],
    ) {
        $this->originalCwd = getcwd();
        $this->setupTempDirectory();

        $discovery = $this->app->make(DirectiveDiscoveryService::class);

        foreach ($this->sourcePaths as $path) {
            $discovery->addSource($path);
        }

        $this->kernel = new DirectiveKernel(
            $this->app,
            $discovery,
        );
    }

    /**
     * Runs a directive in the testing environment.
     *
     * @param  string  $query  The directive query to execute
     * @return DirectiveResponseRecord The execution result
     */
    public function run(string $query): DirectiveResponseRecord
    {
        ob_start();

        try {
            $argv = ['directive', ...explode(' ', $query)];
            $exitCode = $this->kernel->run($argv);
            $output = ob_get_clean();

            return new DirectiveResponseRecord($exitCode, $output);
        } catch (Throwable $e) {
            ob_end_clean();

            return new DirectiveResponseRecord(ExitCode::RUNTIME_ERROR, $e->getMessage());
        }
    }

    /**
     * Gets the temporary directory path.
     *
     * @return string The temporary directory path
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * Cleans up the testing environment.
     *
     * Restores the original working directory and removes the
     * temporary directory.
     */
    public function destroy(): void
    {
        $this->restoreOriginalDirectory();
        $this->removeTempDirectory();
    }

    /**
     * Sets up the temporary testing directory.
     *
     * Creates a temporary directory with a minimal composer.json file
     * and changes the current working directory to it.
     */
    private function setupTempDirectory(): void
    {
        $this->createTempDirectory();
        $this->createMinimalComposerJson();
        $this->changeToTempDirectory();
    }

    /**
     * Creates the temporary directory.
     */
    private function createTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    /**
     * Creates a minimal composer.json file in the temporary directory.
     */
    private function createMinimalComposerJson(): void
    {
        $composerJson = <<<'JSON'
{
    "name": "directive-test/app",
    "type": "project",
    "require": {
        "php": "^8.1"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
JSON;

        file_put_contents($this->tempDir.'/composer.json', $composerJson);
    }

    /**
     * Changes the current working directory to the temporary directory.
     */
    private function changeToTempDirectory(): void
    {
        chdir($this->tempDir);
    }

    /**
     * Restores the original working directory.
     */
    private function restoreOriginalDirectory(): void
    {
        if (is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }
    }

    /**
     * Removes the temporary directory.
     */
    private function removeTempDirectory(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * Recursively removes a directory.
     *
     * @param  string  $dir  The directory path to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
