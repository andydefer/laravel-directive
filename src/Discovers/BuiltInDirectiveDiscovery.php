<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\BuiltIn\HelpDirective;
use AndyDefer\Directive\BuiltIn\ListDirective;
use AndyDefer\Directive\BuiltIn\VersionDirective;
use AndyDefer\Directive\Contracts\DiscoverySourceInterface;

final class BuiltInDirectiveDiscovery implements DiscoverySourceInterface
{
    private array $builtInDirectives = [
        ListDirective::class,
        HelpDirective::class,
        VersionDirective::class,
    ];

    public function discover(): array
    {
        $fqcns = [];

        foreach ($this->builtInDirectives as $class) {
            $fqcns[] = $class;
        }

        return $fqcns;
    }
}
