<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use Carbon\Carbon;

final class ContextPipelineDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:pipeline {name}';
    }

    public function getDescription(): string
    {
        return 'Pipeline demo with context';
    }

    protected function execute(): ExitCode
    {
        $name = $this->getArgument('name');

        // Step 1: Validate
        $this->contextSet('name', $name);
        $this->contextSet('validated', true);

        // Step 2: Enrich
        $this->contextSet('enriched', true);
        $this->contextSet('timestamp', Carbon::now()->format('Y-m-d H:i:s'));

        // Step 3: Process
        $this->call('context:increment');
        $this->call('context:increment');
        $this->call('context:increment');

        // Step 4: Display results
        $steps = $this->contextGet('counter', 0);

        return ExitCode::SUCCESS;
    }
}
