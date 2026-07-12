<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestEchoDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test:echo {message=?} {extra=?}';
    }

    public function getDescription(): string
    {
        return 'Test echo directive';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['echo']);
    }

    public function execute(): ExitCode
    {
        $message = $this->getArgument('message') ?? 'Hello World';

        $this->line($message);

        if ($this->getArgument('extra')) {
            $this->line($this->getArgument('extra'));
        }

        return ExitCode::SUCCESS;
    }
}
