<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\DirectiveInterface;
use Illuminate\Foundation\Application;

class DirectiveHydratorService
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function hydrate(string $fqcn, string $query): DirectiveInterface
    {
        return $this->app->make($fqcn, [
            'query' => $query,
        ]);
    }
}
