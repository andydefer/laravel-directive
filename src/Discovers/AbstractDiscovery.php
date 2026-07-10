<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\Contracts\DiscoverySourceInterface;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use Carbon\Carbon;

/**
 * Abstract base class for discovery sources.
 *
 * Provides common functionality for all discovery sources including
 * problem tracking and error handling.
 */
abstract class AbstractDiscovery implements DiscoverySourceInterface
{
    /**
     * @var ListCollection<StrictAssociative> Collection of problems encountered during discovery
     */
    private ListCollection $problems;

    public function __construct()
    {
        $this->problems = new ListCollection;
    }

    /**
     * Add a problem to the problems collection.
     *
     * @param  string  $key  Unique identifier for the problem
     * @param  string  $context  Human-readable description of the problem context
     * @param  string  $message  The error message
     * @param  array<string, mixed>  $contextData  Additional context data
     */
    protected function addProblem(string $key, string $context, string $message, array $contextData = []): void
    {
        $this->problems = $this->problems->add(StrictAssociative::from([
            'key' => $key,
            'context' => $context,
            'message' => $message,
            'context_data' => $contextData,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),

        ]));
    }

    /**
     * Get all problems encountered during discovery.
     *
     * @return ListCollection<StrictAssociative> Collection of problem records
     */
    public function getProblems(): ListCollection
    {
        return $this->problems;
    }

    /**
     * Clear all problems.
     */
    public function clearProblems(): self
    {
        $this->problems = new ListCollection;

        return $this;
    }
}
