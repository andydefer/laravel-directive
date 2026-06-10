<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contexts\DirectiveContext;
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
 * $context = new DirectiveContext($bootstrapper, $blueprint, $aliases, false);
 * $directive = new ClosureDirective(
 *     context: $context,
 *     interaction: $interaction,
 *     signature: 'test {name} {--verbose}',
 *     execute: function ($d) {
 *         $d->line("Hello " . $d->argument('name'));
 *         return ExitCode::SUCCESS;
 *     }
 * );
 */
final class ClosureDirective extends AbstractDirective
{
    /**
     * @param  DirectiveContext  $context  The directive context
     * @param  DirectiveInteractionService  $interaction  Interaction service for output
     * @param  string  $signature  The directive signature
     * @param  \Closure(ClosureDirective): ExitCode  $execute  Execution logic as a closure
     */
    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
        private readonly string $signature,
        private readonly \Closure $execute,
    ) {
        parent::__construct($context, $interaction);
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
