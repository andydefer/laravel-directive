<?php

// src/Contexts/LaravelBootstrapperContext.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Contracts\LaravelBootstrapperInterface;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

/**
 * Context responsible for bootstrapping Laravel when needed.
 *
 * This context manages the state of Laravel bootstrap process,
 * caching the application instance and any errors that occur.
 */
class LaravelBootstrapperContext implements LaravelBootstrapperInterface
{
    private bool $bootstrapped = false;

    private ?object $application = null;

    private ?string $error = null;

    private ?string $customBootstrapPath = null;

    public function setCustomBootstrapPath(string $path): self
    {
        $this->customBootstrapPath = $path;

        return $this;
    }

    public function bootstrap(): bool
    {
        if ($this->isSuccessfullyBootstrapped()) {
            return true;
        }

        if ($this->hasFailedPreviously()) {
            return false;
        }

        return $this->attemptBootstrap();
    }

    public function isBootstrapped(): bool
    {
        return $this->bootstrapped && $this->application !== null;
    }

    public function getApplication(): ?object
    {
        return $this->application;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function reset(): void
    {
        $this->bootstrapped = false;
        $this->application = null;
        $this->error = null;
        $this->customBootstrapPath = null;
    }

    private function isSuccessfullyBootstrapped(): bool
    {
        return $this->bootstrapped && $this->application !== null;
    }

    private function hasFailedPreviously(): bool
    {
        return $this->error !== null;
    }

    private function attemptBootstrap(): bool
    {
        $bootstrapPath = $this->resolveBootstrapPath();

        if (! $this->isValidBootstrapFile($bootstrapPath)) {
            $this->error = "Laravel bootstrap file not found at: {$bootstrapPath}";

            return false;
        }

        try {
            $this->application = $this->loadApplication($bootstrapPath);
            $this->bootstrapConsoleKernel();
            $this->bootstrapped = true;

            return true;
        } catch (Throwable $exception) {
            $this->error = "Failed to bootstrap Laravel: {$exception->getMessage()}";

            return false;
        }
    }

    private function resolveBootstrapPath(): string
    {
        return $this->customBootstrapPath ?? getcwd() . '/bootstrap/app.php';
    }

    private function isValidBootstrapFile(string $path): bool
    {
        return file_exists($path);
    }

    private function loadApplication(string $bootstrapPath): object
    {
        /** @var object $app */
        $app = require $bootstrapPath;

        return $app ?? $this->getApplication();
    }

    private function bootstrapConsoleKernel(): void
    {
        if ($this->application !== null && $this->hasMakeMethod()) {
            $kernel = $this->application->make(Kernel::class);
            $kernel->bootstrap();
        }
    }

    private function hasMakeMethod(): bool
    {
        return method_exists($this->application, 'make');
    }
}
