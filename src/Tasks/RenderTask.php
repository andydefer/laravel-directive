<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\ConflictRenderStrategy;
use AndyDefer\Directive\Strategies\DisplayMessageRenderStrategy;
use AndyDefer\Directive\Strategies\HelpRenderStrategy;
use AndyDefer\Directive\Strategies\ListRenderStrategy;
use AndyDefer\Directive\Strategies\MessageRenderStrategy;
use AndyDefer\Directive\Strategies\NotFoundRenderStrategy;
use AndyDefer\Directive\Strategies\TableRenderStrategy;
use AndyDefer\Directive\Strategies\ValidationErrorRenderStrategy;

class RenderTask
{
    private string $stubPath;

    private array $strategies;

    public function __construct(?string $stubPath = null)
    {
        $this->stubPath = $stubPath ?? __DIR__ . '/../../stubs/';

        $this->strategies = [
            new HelpRenderStrategy(),
            new ListRenderStrategy(),
            new NotFoundRenderStrategy(),
            new MessageRenderStrategy(),
            new ConflictRenderStrategy(),
            new TableRenderStrategy(),
            new ValidationErrorRenderStrategy(),
            new DisplayMessageRenderStrategy(),
        ];
    }

    public function execute(object $record, RenderType $type): string
    {
        if ($type === RenderType::LIST && $this->isEmptyDirectives($record)) {
            $type = RenderType::EMPTY;
        }

        $stub = $this->getStub($type);
        $replacements = $this->getReplacements($record, $type);

        return $this->applyReplacements($stub, $replacements);
    }

    private function isEmptyDirectives(object $record): bool
    {
        if (!$record instanceof RenderRecord) {
            return true;
        }

        return $record->directives === null || $record->directives->isEmpty();
    }

    private function getStub(RenderType $type): string
    {
        $content = file_get_contents($this->stubPath . $type->getStubName());
        return $content === false ? '' : $content;
    }

    private function getReplacements(object $record, RenderType $type): ReplacementCollection
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($type)) {
                return $strategy->execute($record, $type);
            }
        }

        return new ReplacementCollection();
    }

    private function applyReplacements(string $content, ReplacementCollection $replacements): string
    {
        return str_replace(
            $replacements->getPlaceholders()->toArray(),
            $replacements->getValues()->toArray(),
            $content
        );
    }
}
