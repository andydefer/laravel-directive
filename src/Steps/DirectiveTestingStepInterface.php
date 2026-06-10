<?php

// src/Steps/DirectiveTestingStepInterface.php

declare(strict_types=1);

namespace AndyDefer\Directive\Steps;

use AndyDefer\Directive\Contexts\DirectiveTestingContext;

/**
 * Interface for directive testing initialization steps.
 *
 * Each step in the chain is responsible for one specific part of the
 * test environment setup.
 *
 * @author Andy Defer
 */
interface DirectiveTestingStepInterface
{
    /**
     * Execute the step.
     *
     * @param  DirectiveTestingContext  $context  The testing context (state)
     * @param  callable  $next  The next step in the chain
     * @return DirectiveTestingContext The modified context
     */
    public function execute(DirectiveTestingContext $context, callable $next): DirectiveTestingContext;

    /**
     * Check if this step supports the current context state.
     *
     * @param  DirectiveTestingContext  $context  The testing context
     * @return bool True if the step can be executed
     */
    public function supports(DirectiveTestingContext $context): bool;
}
