<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use Illuminate\Foundation\Application;
use Throwable;

final class DirectiveTestingService
{
    private string $tempDir;

    private string $originalCwd;

    private DirectiveKernel $kernel;

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

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function destroy(): void
    {
        if (is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function setupTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        // Créer un composer.json minimal pour éviter l'erreur
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

        chdir($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
