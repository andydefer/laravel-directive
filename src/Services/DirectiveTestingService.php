<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use Illuminate\Foundation\Application;
use Throwable;

final class DirectiveTestingService
{
    private string $tempDir;

    private string $originalCwd;

    private array $executed = [];

    public function __construct(
        private readonly Application $app,
    ) {
        $this->originalCwd = getcwd();
        $this->setupTempDirectory();
    }

    public function run(string $class, string $query): DirectiveResponseRecord
    {
        $this->executed = [];

        try {
            $directive = $this->app->make($class, ['query' => $query]);
        } catch (Throwable $e) {
            return new DirectiveResponseRecord(ExitCode::FAILURE, $e->getMessage());
        }

        ob_start();
        try {
            $exitCode = $this->executeDirective($directive);
            $output = ob_get_clean();

            return new DirectiveResponseRecord($exitCode, $output);
        } catch (Throwable $e) {
            ob_end_clean();

            return new DirectiveResponseRecord(ExitCode::RUNTIME_ERROR, $e->getMessage());
        }
    }

    private function executeDirective(AbstractDirective $directive): ExitCode
    {
        $key = get_class($directive);

        if (in_array($key, $this->executed, true)) {
            return ExitCode::CONFLICT;
        }

        $this->executed[] = $key;

        $exitCode = $directive->run();

        // Exécuter récursivement les calls
        $calls = $directive->getCalls();
        foreach ($calls as $call) {
            try {
                $callDirective = $this->app->make($call->class, ['query' => $call->query]);
                $callResult = $this->executeDirective($callDirective);

                if ($callResult !== ExitCode::SUCCESS) {
                    return $callResult;
                }
            } catch (Throwable $e) {
                return ExitCode::FAILURE;
            }
        }

        return $exitCode;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function destroy(): void
    {
        chdir($this->originalCwd);

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function setupTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        chdir($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
