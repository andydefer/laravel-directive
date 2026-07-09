<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Container;

use AndyDefer\Directive\Contracts\ContainerInterface;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel container adapter.
 *
 * Adapts the Laravel application container to the Directive ContainerInterface.
 */
final class LaravelContainerAdapter implements ContainerInterface
{
    /**
     * @param  Application  $app  The Laravel application instance
     */
    public function __construct(
        private readonly Application $app
    ) {}

    /**
     * {@inheritdoc}
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->app->make($abstract, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function register(string $provider): void
    {
        $this->app->register($provider);
    }

    /**
     * {@inheritdoc}
     */
    public function basePath(): string
    {
        return $this->app->basePath();
    }

    /**
     * {@inheritdoc}
     */
    public function version(): ?string
    {
        return $this->app->version();
    }
}
