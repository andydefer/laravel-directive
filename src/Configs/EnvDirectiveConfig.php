<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\Directive\Contracts\Configs\DirectiveConfigInterface;

final class EnvDirectiveConfig implements DirectiveConfigInterface
{
    public function directivesPath(): string
    {
        $path = getenv('DIRECTIVE_PATH');

        if ($path !== false && $path !== '') {
            return $path;
        }

        return getcwd() . '/app/Directives';
    }
}
