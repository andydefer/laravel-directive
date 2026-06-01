<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;

/**
 * Test directive that executes a closure instead of a full class.
 *
 * This directive is used for testing purposes to quickly create directives
 * without writing complete classes. The closure receives the directive instance
 * as its first parameter, allowing access to interaction methods (line, info, error),
 * arguments, and options.
 *
 * @example
 * $directive = new ClosureDirective(
 *     signature: 'test {name} {--verbose}',
 *     execute: function ($d) {
 *         $d->line("Hello " . $d->argument('name'));
 *         return ExitCode::SUCCESS;
 *     },
 *     interaction: $interaction
 * );
 */
final class ClosureDirective extends AbstractDirective
{
    /**
     * @param string $signature The directive signature
     * @param callable(ClosureDirective): ExitCode $execute Execution logic as a closure
     * @param DirectiveInteractionService $interaction Interaction service for output
     */
    public function __construct(
        private readonly string $signature,
        private readonly \Closure $execute,
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($interaction);
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getDescription(): string
    {
        return 'Test directive created from closure';
    }

    public function execute(): ExitCode
    {
        return ($this->execute)($this);
    }
}
