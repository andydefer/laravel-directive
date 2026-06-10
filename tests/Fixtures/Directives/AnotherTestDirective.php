<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class AnotherTestDirective extends AbstractDirective
{
    public function __construct(
        DirectiveContext $context,
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($context, $interaction);
    }

    public function getSignature(): string
    {
        return 'another-test';
    }

    public function getDescription(): string
    {
        return 'Another test directive';
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
