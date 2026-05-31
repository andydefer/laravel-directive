<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class DebugRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::DEBUG;
    }

    public function execute(AbstractRecord $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection;

        if (! $record instanceof RenderRecord) {
            $replacements->addReplacement('{{message}}', 'Debug message');

            return $replacements;
        }

        $message = $record->message ?? 'Debug message';
        $replacements->addReplacement('{{message}}', $message);

        return $replacements;
    }
}
