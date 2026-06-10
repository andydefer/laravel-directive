<?php

// tests/TestDirectiveConfig.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;

final class TestDirectiveConfig implements DirectiveConfigInterface
{
    public function __construct(
        private readonly string $path,
    ) {}

    public function directivesPath(): string
    {
        return $this->path;
    }
}
