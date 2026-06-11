<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Configs;

interface DirectiveConfigInterface
{
    /**
     * Get the path where directive classes are located.
     *
     * @return string Absolute path to the directives directory
     */
    public function directivesPath(): string;
}
