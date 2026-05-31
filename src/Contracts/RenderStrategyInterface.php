<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

interface RenderStrategyInterface
{
    public function supports(RenderType $type): bool;

    public function execute(AbstractRecord $record, RenderType $type): ReplacementCollection;
}
