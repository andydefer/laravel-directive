<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

interface DiscoverySourceInterface
{
    /**
     * Discover directive FQCNs from a source.
     *
     * @return array<string> List of fully qualified class names
     */
    public function discover(): array;
}
