<?php

// src/Services/DirectiveExecutionService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\ExecutionStrategyInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Strategies\DirectiveExecutionStrategy;
use AndyDefer\Directive\Strategies\HelpExecutionStrategy;
use AndyDefer\Directive\Strategies\ListExecutionStrategy;
use AndyDefer\Directive\Strategies\VersionExecutionStrategy;

class DirectiveExecutionService
{
    /**
     * @var array<class-string<ExecutionStrategyInterface>>
     */
    private array $strategyClasses = [
        HelpExecutionStrategy::class,
        ListExecutionStrategy::class,
        VersionExecutionStrategy::class,
        DirectiveExecutionStrategy::class,
    ];

    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        foreach ($this->strategyClasses as $strategyClass) {
            $strategy = $this->resolveStrategy($strategyClass);

            if ($strategy->supports($record)) {
                return $strategy->execute($record);
            }
        }

        return ExitCode::FAILURE;
    }

    private function resolveStrategy(string $class): ExecutionStrategyInterface
    {
        return match ($class) {
            HelpExecutionStrategy::class => new HelpExecutionStrategy($this->renderer),
            ListExecutionStrategy::class => new ListExecutionStrategy($this->discovery, $this->renderer),
            VersionExecutionStrategy::class => new VersionExecutionStrategy($this->renderer),
            DirectiveExecutionStrategy::class => new DirectiveExecutionStrategy(
                $this->discovery,
                $this->parser,
                $this->hydrator,
                $this->renderer
            ),
            default => throw new \RuntimeException("Unknown strategy: {$class}"),
        };
    }
}
