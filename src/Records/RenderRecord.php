<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Collections\TypedCollection;

final class RenderRecord extends AbstractRecord
{
    public function __construct(
        public readonly RenderType $type,
        public readonly ?TypedCollection $directives = null,
        public readonly ?string $signature = null,
        public readonly ?string $message = null,
        public readonly ?DisplayMessageRecord $messageRecord = null,
        public readonly ?DisplayTableRecord $tableRecord = null,
    ) {}
}
