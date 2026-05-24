<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Services\LaravelBootstrapper;
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

class RenderTask
{
    private array $strategies;

    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function __construct(?LaravelBootstrapper $laravelBootstrapper = null)
    {
        $this->laravelBootstrapper = $laravelBootstrapper;
        $this->initializeStrategies();
    }

    private function initializeStrategies(): void
    {
        $helpStrategy = new HelpRenderStrategy;
        $listStrategy = new ListRenderStrategy;
        $notFoundStrategy = new NotFoundRenderStrategy;
        $messageStrategy = new MessageRenderStrategy;
        $conflictStrategy = new ConflictRenderStrategy;
        $tableStrategy = new TableRenderStrategy;
        $validationErrorStrategy = new ValidationErrorRenderStrategy;
        $displayMessageStrategy = new DisplayMessageRenderStrategy;
        $warningStrategy = new WarningRenderStrategy;
        $debugStrategy = new DebugRenderStrategy;
        $versionStrategy = new VersionRenderStrategy;

        // Injecter le bootstrapper dans la stratégie version
        if ($this->laravelBootstrapper !== null) {
            $versionStrategy->setLaravelBootstrapper($this->laravelBootstrapper);
        }

        $this->strategies = [
            $helpStrategy,
            $listStrategy,
            $notFoundStrategy,
            $messageStrategy,
            $conflictStrategy,
            $tableStrategy,
            $validationErrorStrategy,
            $displayMessageStrategy,
            $warningStrategy,
            $debugStrategy,
            $versionStrategy,
        ];
    }

    public function execute(object $record, RenderType $type): string
    {
        if ($type === RenderType::LIST && $this->isEmptyDirectives($record)) {
            $type = RenderType::EMPTY;
        }

        $replacements = $this->getReplacements($record, $type);

        return $type->render($replacements->toAssociativeArray());
    }

    private function isEmptyDirectives(object $record): bool
    {
        if (! $record instanceof RenderRecord) {
            return true;
        }

        return $record->directives === null || $record->directives->isEmpty();
    }

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
