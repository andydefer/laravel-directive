<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;

final class TestDirective extends AbstractDirective
{
    private string $signature;

    private string $description;

    private ?ExitCode $exitCode;

    public function __construct(
        DirectiveInteractionService $interaction,
        string $signature = 'test-directive',
        string $description = 'Test directive',
        ?ExitCode $exitCode = ExitCode::SUCCESS,
    ) {
        parent::__construct($interaction);
        $this->signature = $signature;
        $this->description = $description;
        $this->exitCode = $exitCode;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function execute(): ExitCode
    {
        return $this->exitCode ?? ExitCode::SUCCESS;
    }
}
