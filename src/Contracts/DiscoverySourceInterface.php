<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\DomainStructures\Utils\ListCollection;

interface DiscoverySourceInterface
{
    /**
     * Discover directive FQCNs from a source.
     *
     * @return array<string> List of fully qualified class names
     */
    public function discover(): array;

    /**
     * Get all problems encountered during discovery.
     *
     * @return ListCollection<array<string, mixed>> Collection of problem records
     */
    public function getProblems(): ListCollection;

    /**
     * Clear all problems.
     */
    public function clearProblems(): self;
}
