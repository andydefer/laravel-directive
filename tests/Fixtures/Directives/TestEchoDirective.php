<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class TestEchoDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($interaction);
    }

    public function getSignature(): string
    {
        return 'test-echo {message?}';
    }

    public function getDescription(): string
    {
        return 'Test echo directive';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('echo');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        $message = $this->argument('message') ?? 'Hello World';
        $this->line($message);

        return ExitCode::SUCCESS;
    }
}
