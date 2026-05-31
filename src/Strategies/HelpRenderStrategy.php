<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contracts\RenderStrategyInterface;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class HelpRenderStrategy implements RenderStrategyInterface
{
    public function supports(RenderType $type): bool
    {
        return $type === RenderType::HELP;
    }

    public function execute(AbstractRecord $record, RenderType $type): ReplacementCollection
    {
        return new ReplacementCollection;
    }
}
