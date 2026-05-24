<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use Illuminate\Contracts\Console\Kernel;

/**
 * Service responsible for bootstrapping Laravel when needed.
 * This is called automatically for directives that request it.
 */
class LaravelBootstrapper
{
    private bool $bootstrapped = false;

    private ?object $app = null;

    private ?string $error = null;

    private ?string $customBootstrapPath = null;

    /**
     * Set a custom bootstrap path for testing.
     */
    public function setCustomBootstrapPath(string $path): self
    {
        $this->customBootstrapPath = $path;

        return $this;
    }

    /**
     * Bootstrap Laravel application if available and not already bootstrapped.
     *
     * @return bool True if Laravel is successfully bootstrapped or already was
     */
    public function bootstrap(): bool
    {
        // Déjà booté avec succès
        if ($this->bootstrapped && $this->app !== null) {
            return true;
        }

        // Déjà essayé et a échoué
        if ($this->error !== null) {
            return false;
        }

        // Cherche le fichier bootstrap Laravel
        $bootstrapPath = $this->customBootstrapPath ?? getcwd().'/bootstrap/app.php';

        if (! file_exists($bootstrapPath)) {
            $this->error = 'Laravel bootstrap file not found at: '.$bootstrapPath;

            return false;
        }

        try {
            // Charge l'application Laravel
            $this->app = require $bootstrapPath;

            // Bootstrap le kernel console si disponible
            if ($this->app !== null && method_exists($this->app, 'make')) {
                $kernel = $this->app->make(Kernel::class);
                $kernel->bootstrap();
            }
            $this->bootstrapped = true;

            return true;
        } catch (\Throwable $e) {
            $this->error = 'Failed to bootstrap Laravel: '.$e->getMessage();

            return false;
        }
    }

    /**
     * Check if Laravel has been successfully bootstrapped.
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped && $this->app !== null;
    }

    /**
     * Get the Laravel application instance if bootstrapped.
     */
    public function getApplication(): ?object
    {
        return $this->app;
    }

    /**
     * Get the last error message if bootstrap failed.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Reset bootstrap state (useful for testing).
     */
    public function reset(): void
    {
        $this->bootstrapped = false;
        $this->app = null;
        $this->error = null;
        $this->customBootstrapPath = null;
    }
}
