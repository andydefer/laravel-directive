<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Dispatchers;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\ConflictRenderStrategy;
use AndyDefer\Directive\Strategies\DebugRenderStrategy;
use AndyDefer\Directive\Strategies\DisplayMessageRenderStrategy;
use AndyDefer\Directive\Strategies\HelpRenderStrategy;
use AndyDefer\Directive\Strategies\ListRenderStrategy;
use AndyDefer\Directive\Strategies\MessageRenderStrategy;
use AndyDefer\Directive\Strategies\NotFoundRenderStrategy;
use AndyDefer\Directive\Strategies\TableRenderStrategy;
use AndyDefer\Directive\Strategies\ValidationErrorRenderStrategy;
use AndyDefer\Directive\Strategies\VersionRenderStrategy;
use AndyDefer\Directive\Strategies\WarningRenderStrategy;

/**
 * Task responsible for rendering different types of output.
 *
 * This task uses a strategy pattern to delegate rendering to specialized
 * strategies based on the render type. It handles fallbacks (e.g., LIST
 * with no directives falls back to EMPTY) and manages the rendering
 * pipeline.
 *
 * @author Andy Defer
 */
class RenderDispatcher
{
    /**
     * @var array<int, RenderStrategyInterface>
     */
    private array $strategies;

    private ?LaravelBootstrapperContext $laravelBootstrapperContext = null;

    public function __construct(?LaravelBootstrapperContext $laravelBootstrapperContext = null)
    {
        $this->laravelBootstrapperContext = $laravelBootstrapperContext;
        $this->initializeStrategies();
    }

    /**
     * Initializes all rendering strategies.
     *
     * Strategies are registered in the order they will be checked.
     */
    private function initializeStrategies(): void
    {
        $this->strategies = [
            new HelpRenderStrategy,
            new ListRenderStrategy,
            new NotFoundRenderStrategy,
            new MessageRenderStrategy,
            new ConflictRenderStrategy,
            new TableRenderStrategy,
            new ValidationErrorRenderStrategy,
            new DisplayMessageRenderStrategy,
            new WarningRenderStrategy,
            new DebugRenderStrategy,
            new VersionRenderStrategy,
        ];
    }

    /**
     * Executes the rendering process for the given record and type.
     *
     * @param  object  $record  The record containing data to render
     * @param  RenderType  $type  The type of render to perform
     * @return string The rendered output
     */
    public function execute(object $record, RenderType $type): string
    {
        $effectiveType = $this->determineEffectiveType($record, $type);
        $replacements = $this->getReplacements($record, $effectiveType);

        return $effectiveType->render($replacements->toAssociativeArray());
    }

    /**
     * Determines the effective render type, applying fallbacks if needed.
     *
     * If the type is LIST but there are no directives, fallback to EMPTY.
     *
     * @param  object  $record  The record containing data
     * @param  RenderType  $type  The requested render type
     * @return RenderType The effective render type
     */
    private function determineEffectiveType(object $record, RenderType $type): RenderType
    {
        if ($type === RenderType::LIST && $this->isEmptyDirectives($record)) {
            return RenderType::EMPTY;
        }

        return $type;
    }

    /**
     * Checks if the record has no directives to display.
     *
     * @param  object  $record  The record to check
     * @return bool True if there are no directives to display
     */
    private function isEmptyDirectives(object $record): bool
    {
        if (! $record instanceof RenderRecord) {
            return true;
        }

        return $record->directives === null || $record->directives->isEmpty();
    }

    /**
     * Finds the appropriate strategy and executes it to get replacements.
     *
     * @param  object  $record  The record containing data
     * @param  RenderType  $type  The render type
     * @return ReplacementCollection The replacements for template rendering
     */
    private function getReplacements(object $record, RenderType $type): ReplacementCollection
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($type)) {
                return $strategy->execute($record, $type);
            }
        }

        return new ReplacementCollection;
    }
}
