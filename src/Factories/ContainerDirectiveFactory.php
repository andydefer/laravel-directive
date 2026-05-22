<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Factories;

use AndyDefer\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use Illuminate\Contracts\Container\Container;

final class ContainerDirectiveFactory implements DirectiveFactoryInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(string $class): DirectiveInterface
    {
        return $this->container->make($class);
    }
}
