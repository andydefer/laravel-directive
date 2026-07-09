<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Container;

use AndyDefer\Directive\Contracts\ContainerInterface;

/**
 * Simple dependency injection container.
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, object>
     */
    private array $singletons = [];

    /**
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    private string $basePath;

    private ?string $version = null;

    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: getcwd();
    }

    public function singleton(string $abstract, mixed $concrete): self
    {
        if (is_object($concrete) && ! is_callable($concrete)) {
            $this->singletons[$abstract] = $concrete;

            return $this;
        }

        $this->bindings[$abstract] = $concrete;

        return $this;
    }

    public function bind(string $abstract, callable $concrete): self
    {
        $this->bindings[$abstract] = $concrete;

        return $this;
    }

    public function alias(string $alias, string $abstract): self
    {
        $this->aliases[$alias] = $abstract;

        return $this;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            return $this->singletons[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return $this->build($abstract, $parameters);
        }

        throw new \RuntimeException("Unable to resolve: {$abstract}");
    }

    public function register(string $provider): void
    {
        if (! class_exists($provider)) {
            throw new \RuntimeException("Service provider not found: {$provider}");
        }

        $instance = new $provider;
        $instance->register($this);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function setBasePath(string $path): self
    {
        $this->basePath = $path;

        return $this;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->singletons[$abstract])
            || isset($this->aliases[$abstract]);
    }

    private function build(string $class, array $parameters = []): object
    {
        $reflection = new \ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw new \RuntimeException("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();

            if (array_key_exists($paramName, $parameters)) {
                $args[] = $parameters[$paramName];

                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            $paramType = $param->getType();

            if ($paramType && ! $paramType->isBuiltin()) {
                $typeName = $paramType->getName();
                if ($this->has($typeName)) {
                    $args[] = $this->make($typeName);

                    continue;
                }
            }

            if ($paramType && $paramType->getName() === ContainerInterface::class) {
                $args[] = $this;

                continue;
            }

            throw new \RuntimeException(
                "Cannot resolve parameter '{$paramName}' for class {$class}"
            );
        }

        return $reflection->newInstanceArgs($args);
    }
}
