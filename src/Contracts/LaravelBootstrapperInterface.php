<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

interface LaravelBootstrapperInterface
{
    /**
     * Bootstrap Laravel application.
     *
     * @return bool True if successfully bootstrapped
     */
    public function bootstrap(): bool;

    /**
     * Check if Laravel has been successfully bootstrapped.
     *
     * @return bool True if bootstrapped
     */
    public function isBootstrapped(): bool;

    /**
     * Get the Laravel application instance.
     *
     * @return object|null Laravel application instance
     */
    public function getApplication(): ?object;

    /**
     * Get the last error message.
     *
     * @return string|null Error message
     */
    public function getError(): ?string;

    /**
     * Reset bootstrap state.
     */
    public function reset(): void;
}
