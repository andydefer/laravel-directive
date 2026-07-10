<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\Container\LaravelContainerAdapter;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Throwable;

/**
 * Service for testing directives in an isolated environment.
 */
final class DirectiveTestingService
{
    private string $tempDir;

    private string $originalCwd;

    private DirectiveKernel $kernel;

    /**
     * @param  Container|LaravelApplication  $container  The container instance
     * @param  array<int, string>  $sourcePaths  Additional source paths to scan
     */
    public function __construct(
        private readonly Container|LaravelApplication $container,
        private readonly array $sourcePaths = [],
    ) {
        $this->originalCwd = getcwd();
        $this->setupTempDirectory();

        $adapter = $this->container instanceof LaravelApplication
                ? new LaravelContainerAdapter($this->container)
                : $this->container;

        // Ajouter les sources AVANT de créer le kernel
        $this->kernel = DirectiveKernel::init($adapter);

        foreach ($this->sourcePaths as $path) {
            $this->kernel->addSource($path);
        }
    }

    /**
     * Runs a directive from a query string.
     *
     * @param  string  $query  The full query (e.g., "greet John --formal")
     * @return DirectiveResponseRecord The response record
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
     * Runs a directive by its FQCN (Fully Qualified Class Name).
     *
     * @param  class-string  $fqcn  The fully qualified class name
     * @param  array<int, string>  $argv  The arguments
     * @return DirectiveResponseRecord The response record
     */
    public function runDirective(string $fqcn, array $argv = []): DirectiveResponseRecord
    {
        ob_start();

        try {
            $exitCode = $this->kernel->runDirective($fqcn, $argv);
            $output = ob_get_clean();

            return new DirectiveResponseRecord($exitCode, $output);
        } catch (Throwable $e) {
            ob_end_clean();

            return new DirectiveResponseRecord(ExitCode::RUNTIME_ERROR, $e->getMessage());
        }
    }

    /**
     * Runs a directive by its signature.
     *
     * @param  string  $query  The signature (e.g., "greet John --formal")
     * @return DirectiveResponseRecord The response record
     */
    public function runSignature(string $query): DirectiveResponseRecord
    {
        ob_start();

        try {
            $exitCode = $this->kernel->runSignature($query);
            $output = ob_get_clean();

            return new DirectiveResponseRecord($exitCode, $output);
        } catch (Throwable $e) {
            ob_end_clean();

            return new DirectiveResponseRecord(ExitCode::RUNTIME_ERROR, $e->getMessage());
        }
    }

    public function getKernel(): DirectiveKernel
    {
        return $this->kernel;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function destroy(): void
    {
        $this->restoreOriginalDirectory();
        $this->removeTempDirectory();
    }

    private function setupTempDirectory(): void
    {
        $this->createTempDirectory();
        $this->createMinimalComposerJson();
        $this->changeToTempDirectory();
    }

    private function createTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

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

    private function changeToTempDirectory(): void
    {
        chdir($this->tempDir);
    }

    private function restoreOriginalDirectory(): void
    {
        if (is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }
    }

    private function removeTempDirectory(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

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
