<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\RegisteredDirectives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestPackageDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,
    ) {
        parent::__construct($interaction);
    }

    public function getSignature(): string
    {
        return 'test-package';
    }

    public function getDescription(): string
    {
        return 'Test directive from external package';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('tpkg');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
