<?php

// src/Services/DirectiveExecutionService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Strategies\DirectiveExecutionStrategy;
use AndyDefer\Directive\Strategies\HelpExecutionStrategy;
use AndyDefer\Directive\Strategies\ListExecutionStrategy;
use AndyDefer\Directive\Strategies\VersionExecutionStrategy;

class DirectiveExecutionService
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
        private readonly ContainerInterface $container,
    ) {
        $this->container->add(HelpExecutionStrategy::class, new HelpExecutionStrategy($this->renderer));
        $this->container->add(ListExecutionStrategy::class, new ListExecutionStrategy($this->discovery, $this->renderer));
        $this->container->add(VersionExecutionStrategy::class, new VersionExecutionStrategy($this->renderer));
        $this->container->add(DirectiveExecutionStrategy::class, new DirectiveExecutionStrategy(
            $this->discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer
        ));
    }

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        $strategies = $this->container->getAll();

        foreach ($strategies as $strategy) {
            if ($strategy->supports($record)) {
                return $strategy->execute($record);
            }
        }

        return ExitCode::FAILURE;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
