<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;

class ClosureDirective extends AbstractDirective
{
    private string $signature;
    private \Closure $execute;

    public function __construct(
        string $signature,
        callable $execute,
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($interaction);
        $this->signature = $signature;
        $this->execute = $execute(...);
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
