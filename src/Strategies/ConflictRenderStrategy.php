<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Records\Recordable;

final class ConflictRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::CONFLICT;
    }

    public function execute(Recordable $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection;

        if (! $record instanceof ConflictDisplayRecord) {
            return $replacements;
        }

        $options = $this->buildConflictOptions($record);
        $replacements->addReplacement('{{name}}', $record->name);
        $replacements->addReplacement('{{options}}', $options);

        return $replacements;
    }

    private function buildConflictOptions(ConflictDisplayRecord $record): string
    {
        $classNames = $record->classNames->toArray();
        $signatures = $record->signatures->toArray();
        $descriptions = $record->descriptions->toArray();
        $options = [];

        for ($i = 0; $i < count($classNames); $i++) {
            $option = ($i + 1).'. '.$classNames[$i]." (signature: {$signatures[$i]})";

            if (! empty($descriptions[$i])) {
                $option .= "\n   ".$descriptions[$i];
            }

            $options[] = $option;
        }

        return implode("\n", $options);
    }
}
