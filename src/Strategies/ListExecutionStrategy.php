<?php

// src/Strategies/ListExecutionStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Contracts\ExecutionStrategyInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveRendererService;

final class ListExecutionStrategy implements ExecutionStrategyInterface
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function supports(DirectiveExecutionRecord $record): bool
    {
        return $record->signature === '--list' || $record->signature === '-l';
    }

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        $directives = $this->discovery->discover();
        $this->renderer->renderList($directives);

        return ExitCode::SUCCESS;
    }

    public function getPriority(): int
    {
        return 100;
    }
}
