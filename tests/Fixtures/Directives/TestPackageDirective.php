<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class TestPackageDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test:package';
    }

    public function getDescription(): string
    {
        return 'Test directive from external package';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('tpkg');
        return $aliases;
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
