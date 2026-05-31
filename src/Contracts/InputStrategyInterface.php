<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

interface InputStrategyInterface
{
    public function supports(InputType $type): bool;

    public function execute(AbstractRecord $record, InputType $type): mixed;
}
