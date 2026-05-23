<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Records\Recordable;

interface InputStrategyInterface
{
    public function supports(InputType $type): bool;

    public function execute(Recordable $record, InputType $type): mixed;
}
