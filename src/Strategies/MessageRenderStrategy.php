<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Records\Recordable;

final class MessageRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::SUCCESS || $type === RenderType::ERROR;
    }

    public function execute(Recordable $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection;

        if (! $record instanceof RenderRecord) {
            $replacements->addReplacement('{{message}}', $type->getDefaultMessage());

            return $replacements;
        }

        $message = $record->message ?? $type->getDefaultMessage();
        $replacements->addReplacement('{{message}}', $message);

        return $replacements;
    }
}
