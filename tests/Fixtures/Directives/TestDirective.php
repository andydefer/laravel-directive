<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestDirective extends AbstractDirective
{
    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($context, $interaction);
    }

    public function getSignature(): string
    {
        return 'test-directive';
    }

    public function getDescription(): string
    {
        return 'Test directive';
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
