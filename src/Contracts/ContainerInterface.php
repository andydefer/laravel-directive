<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

/**
 * Container interface for the Directive system.
 *
 * This interface allows the Directive system to work with any dependency
 * injection container (Laravel, Symfony, or custom) without coupling.
 *
 * @template T
 */
interface ContainerInterface
{
    /**
     * Resolve a service from the container.
     *
     * @template T
     *
     * @param  class-string<T>|string  $abstract  The service identifier or class name
     * @param  array<string, mixed>  $parameters  Optional parameters to pass to the constructor
     * @return T The resolved service instance
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Register a service provider.
     *
     * @param  string  $provider  The service provider class name
     */
    public function register(string $provider): void;

    /**
     * Get the base path of the application.
     *
     * @return string The base path
     */
    public function basePath(): string;

    /**
     * Get the application version.
     *
     * @return string|null The version string, or null if not available
     */
    public function version(): ?string;
}
