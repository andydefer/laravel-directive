<?php

// src/Strategies/VersionExecutionStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Contracts\ExecutionStrategyInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveRendererService;

final class VersionExecutionStrategy implements ExecutionStrategyInterface
{
    public function __construct(
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function supports(DirectiveExecutionRecord $record): bool
    {
        return $record->signature === '--version' || $record->signature === '-v';
    }

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        $this->renderer->renderVersion();

        return ExitCode::SUCCESS;
    }

    public function getPriority(): int
    {
        return 100;
    }
}
