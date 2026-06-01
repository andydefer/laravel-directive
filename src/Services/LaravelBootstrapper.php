<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use Illuminate\Contracts\Console\Kernel;
use Throwable;

/**
 * Service responsible for bootstrapping Laravel when needed.
 *
 * This service is called automatically for directives that require Laravel
 * framework integration. It handles loading the Laravel application,
 * bootstrapping the console kernel, and caches the result to avoid
 * multiple bootstrap attempts.
 */
class LaravelBootstrapper
{
    private bool $bootstrapped = false;
    private ?object $application = null;
    private ?string $error = null;
    private ?string $customBootstrapPath = null;

    /**
     * Set a custom bootstrap path for testing or custom installations.
     *
     * @param string $path Path to Laravel's bootstrap/app.php file
     *
     * @return self Returns self for method chaining
     */
    public function setCustomBootstrapPath(string $path): self
    {
        $this->customBootstrapPath = $path;

        return $this;
    }

    /**
     * Bootstrap Laravel application if available and not already bootstrapped.
     *
     * This method attempts to locate and load Laravel's bootstrap file,
     * then bootstraps the console kernel. Results are cached to prevent
     * repeated bootstrap attempts.
     *
     * @return bool True if Laravel is successfully bootstrapped or already was
     */
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

    /**
     * Check if Laravel has been successfully bootstrapped.
     *
     * @return bool True if bootstrapped and application instance exists
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped && $this->application !== null;
    }

    /**
     * Get the Laravel application instance if bootstrapped.
     *
     * @return object|null Laravel application instance or null if not bootstrapped
     */
    public function getApplication(): ?object
    {
        return $this->application;
    }

    /**
     * Get the last error message if bootstrap failed.
     *
     * @return string|null Error message or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Reset bootstrap state (useful for testing).
     *
     * Clears all cached state including application instance and error messages.
     */
    public function reset(): void
    {
        $this->bootstrapped = false;
        $this->application = null;
        $this->error = null;
        $this->customBootstrapPath = null;
    }

    /**
     * Check if bootstrap was previously successful.
     */
    private function isSuccessfullyBootstrapped(): bool
    {
        return $this->bootstrapped && $this->application !== null;
    }

    /**
     * Check if bootstrap previously failed.
     */
    private function hasFailedPreviously(): bool
    {
        return $this->error !== null;
    }

    /**
     * Attempt to bootstrap Laravel application.
     */
    private function attemptBootstrap(): bool
    {
        $bootstrapPath = $this->resolveBootstrapPath();

        if (!$this->isValidBootstrapFile($bootstrapPath)) {
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

    /**
     * Resolve the bootstrap file path.
     */
    private function resolveBootstrapPath(): string
    {
        return $this->customBootstrapPath ?? getcwd() . '/bootstrap/app.php';
    }

    /**
     * Check if bootstrap file exists and is readable.
     */
    private function isValidBootstrapFile(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Load Laravel application from bootstrap file.
     *
     * @return object Laravel application instance
     */
    private function loadApplication(string $bootstrapPath): object
    {
        /** @var object $app */
        $app = require $bootstrapPath;
        return $app;
    }

    /**
     * Bootstrap Laravel console kernel if available.
     */
    private function bootstrapConsoleKernel(): void
    {
        if ($this->application !== null && $this->hasMakeMethod()) {
            $kernel = $this->application->make(Kernel::class);
            $kernel->bootstrap();
        }
    }

    /**
     * Check if application has the make method (Laravel container).
     */
    private function hasMakeMethod(): bool
    {
        return method_exists($this->application, 'make');
    }
}
