<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Records\Recordable;

final class DisplayMessageRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::DISPLAY_MESSAGE;
    }

    public function execute(Recordable $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection();

        if (!$record instanceof RenderRecord) {
            return $replacements;
        }

        $messageRecord = $record->messageRecord;

        if (!$messageRecord instanceof DisplayMessageRecord) {
            return $replacements;
        }

        $replacements->addReplacement('{{color}}', $messageRecord->type->getColorCode());
        $replacements->addReplacement('{{message}}', $messageRecord->message);
        $replacements->addReplacement('{{reset}}', $messageRecord->type->getResetCode());

        return $replacements;
    }
}
