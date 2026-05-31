<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestDirectiveWithArgs extends AbstractDirective
{
    private string $customArg;

    public function __construct(
        DirectiveInteractionService $interaction,
        string $customArg,
    ) {
        parent::__construct($interaction);
        $this->customArg = $customArg;
    }

    public function getCustomArg(): string
    {
        return $this->customArg;
    }

    public function getSignature(): string
    {
        return 'test-with-args';
    }

    public function getDescription(): string
    {
        return 'Test directive with args';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }
}
