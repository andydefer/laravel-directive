<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ContextOrchestrateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:orchestrate';
    }

    public function getDescription(): string
    {
        return 'Orchestrate multiple context operations';
    }

    protected function execute(): ExitCode
    {

        // Step 1: Set user
        $this->contextSet('name', 'John');
        $this->contextSet('step1_done', true);

        // Step 2: Process user
        $name = $this->contextGet('name');
        $this->contextSet('processed_user', strtoupper($name));
        $this->contextSet('step2_done', true);

        // Step 3: Count steps
        $this->contextIncrement('steps_completed');
        $this->contextIncrement('steps_completed');

        return ExitCode::SUCCESS;
    }
}
