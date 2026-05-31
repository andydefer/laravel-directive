<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class TestDirective extends AbstractDirective
{
    private string $signature = 'test-directive';

    private string $description = 'Test directive';

    private ?ExitCode $exitCode = null;

    public function __construct(
        DirectiveInteractionService $interaction,
        ?string $signature = null,
        ?string $description = null,
        ?ExitCode $exitCode = null,
    ) {
        parent::__construct($interaction);
        if ($signature !== null) {
            $this->signature = $signature;
        }
        if ($description !== null) {
            $this->description = $description;
        }
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

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function execute(): ExitCode
    {
        return $this->exitCode ?? ExitCode::SUCCESS;
    }
}
