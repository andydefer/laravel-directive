<?php
// src/Steps/CreateLaravelStructureStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\PathType;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Step that creates a minimal Laravel application structure.
 *
 * @author Andy Defer
 */
final class CreateLaravelStructureStep implements DirectiveTestingStepInterface
{
    private const BOOTSTRAP_APP_PATH = '/bootstrap/app.php';
    private const CONFIG_APP_PATH = '/config/app.php';

    public function supports(DirectiveTestingContext $context): bool
    {
        return $context->shouldBootLaravel() && !$context->hasLaravelStructure();
    }

    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext
    {
        $tempDir = $context->getTempDir();

        $this->createBootstrapDirectory($tempDir, $context);
        $this->createConfigDirectory($tempDir, $context);
        $this->createStorageDirectory($tempDir, $context);
        $this->createAppDirectory($tempDir, $context);

        $context->addStepResult('create_laravel_structure', $tempDir, new DateTimeVO(null));

        return $next($context);
    }

    private function createBootstrapDirectory(string $tempDir, DirectiveTestingContext $context): void
    {
        $bootstrapDir = $tempDir . '/bootstrap';
        mkdir($bootstrapDir, 0777, true);

        $bootstrapAppPath = $tempDir . self::BOOTSTRAP_APP_PATH;

        $this->copyStubToDestination('bootstrap/app.php', $bootstrapAppPath, $context);
    }

    private function createConfigDirectory(string $tempDir, DirectiveTestingContext $context): void
    {
        $configDir = $tempDir . '/config';
        mkdir($configDir, 0777, true);

        $configAppPath = $tempDir . self::CONFIG_APP_PATH;

        $this->copyStubToDestination('config/app.php', $configAppPath, $context);
    }

    private function createStorageDirectory(string $tempDir, DirectiveTestingContext $context): void
    {
        $storageDir = $tempDir . '/storage';
        mkdir($storageDir, 0777, true);
        mkdir($storageDir . '/framework', 0777, true);
        mkdir($storageDir . '/framework/views', 0777, true);
        mkdir($storageDir . '/framework/cache', 0777, true);
        mkdir($storageDir . '/logs', 0777, true);

        $context->addCreatedPath($storageDir, PathType::DIRECTORY, new DateTimeVO(null));
    }

    private function createAppDirectory(string $tempDir, DirectiveTestingContext $context): void
    {
        $appDir = $tempDir . '/app';
        mkdir($appDir, 0777, true);
        mkdir($appDir . '/Http', 0777, true);
        mkdir($appDir . '/Models', 0777, true);
        mkdir($appDir . '/Directives', 0777, true);

        $context->addCreatedPath($appDir, PathType::DIRECTORY, new DateTimeVO(null));
    }

    private function copyStubToDestination(string $stubPath, string $destinationPath, DirectiveTestingContext $context): void
    {
        $stubFullPath = __DIR__ . '/../../stubs/laravel/' . $stubPath;

        if (!file_exists($stubFullPath)) {
            throw new \RuntimeException(sprintf('Stub file not found: %s', $stubFullPath));
        }

        $content = file_get_contents($stubFullPath);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read stub file: %s', $stubFullPath));
        }

        file_put_contents($destinationPath, $content);

        $context->addCreatedPath($destinationPath, PathType::FILE, new DateTimeVO(null));
    }
}
