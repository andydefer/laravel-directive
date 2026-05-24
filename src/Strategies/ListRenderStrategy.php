<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Recordable;

final class ListRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::LIST || $type === RenderType::EMPTY;
    }

    public function execute(Recordable $record, RenderType $type): ReplacementCollection
    {
        if (! $record instanceof RenderRecord) {
            return new ReplacementCollection;
        }

        $replacements = new ReplacementCollection;

        if ($type === RenderType::EMPTY) {
            return $replacements;
        }

        if ($record->directives === null || $record->directives->isEmpty()) {
            return $replacements;
        }

        $replacements->addReplacement('{{count}}', (string) $record->directives->count());
        $replacements->addReplacement('{{rows}}', $this->buildListRows($record->directives));

        return $replacements;
    }

    private function buildListRows(TypedCollection $directives): string
    {
        $rows = [];

        foreach ($directives as $directive) {
            $aliases = $directive->aliases->count() > 0
                ? ' ('.implode(', ', $directive->aliases->toArray()).')'
                : '';

            $rows[] = sprintf(
                "  \033[33m%-23s\033[0m \033[37m%s\033[90m%s\033[0m",
                $directive->signature,
                $directive->description,
                $aliases
            );
        }

        return implode("\n", $rows);
    }
}
