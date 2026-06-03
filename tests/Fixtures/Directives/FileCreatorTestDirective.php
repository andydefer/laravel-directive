<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Traits\FileCreator;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Fixture class that implements the trait and extends AbstractDirective
 */
class FileCreatorTestDirective extends AbstractDirective
{
    use FileCreator;

    public function __construct(DirectiveInteractionService $interaction)
    {
        parent::__construct($interaction);
        $this->initFileCreator();
    }

    public function getSignature(): string
    {
        return 'test-file-creator';
    }

    public function getDescription(): string
    {
        return 'Test file creator trait';
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection();
    }

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
