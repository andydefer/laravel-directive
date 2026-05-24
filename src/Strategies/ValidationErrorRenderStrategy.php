<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Records\Recordable;

final class ValidationErrorRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::VALIDATION_ERROR;
    }

    public function execute(Recordable $record, RenderType $type): ReplacementCollection
    {
        $replacements = new ReplacementCollection;

        if (! $record instanceof ValidationResultRecord) {
            $replacements->addReplacement('{{error}}', 'Invalid signature');

            return $replacements;
        }

        $replacements->addReplacement('{{error}}', $record->error ?? 'Invalid signature');

        return $replacements;
    }
}
