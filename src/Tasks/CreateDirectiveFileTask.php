<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Records\CreateDirectiveFileRecord;
use AndyDefer\Directive\Services\DirectiveNamingService;

/**
 * Task for creating directive file and directory on the filesystem.
 */
class CreateDirectiveFileTask
{
    private const DIRECTIVES_PATH = '/app/Directives/';
    private string $stubPath;

    public function __construct(
        private readonly DirectiveNamingService $namingService,
        ?string $stubPath = null,
    ) {
        $this->stubPath = $stubPath ?? __DIR__ . '/../../stubs/directive.stub';
    }

    public function execute(string $className, string $signature): CreateDirectiveFileRecord
    {
        $directory = getcwd() . self::DIRECTIVES_PATH;
        $filePath = $directory . $className . '.php';

        if (file_exists($filePath)) {
            return new CreateDirectiveFileRecord(
                success: false,
                path: $filePath,
                error: 'Directive already exists',
            );
        }

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                return new CreateDirectiveFileRecord(
                    success: false,
                    path: $filePath,
                    error: 'Cannot create directory: ' . $directory,
                );
            }
        }

        $stub = file_get_contents($this->stubPath);

        if ($stub === false) {
            return new CreateDirectiveFileRecord(
                success: false,
                path: $filePath,
                error: 'Stub template not found at: ' . $this->stubPath,
            );
        }

        $content = $this->namingService->replaceStubVariables($stub, $className, $signature);

        if (file_put_contents($filePath, $content) === false) {
            return new CreateDirectiveFileRecord(
                success: false,
                path: $filePath,
                error: 'Cannot create file: ' . $filePath,
            );
        }

        return new CreateDirectiveFileRecord(
            success: true,
            path: $filePath,
            error: null,
        );
    }
}
