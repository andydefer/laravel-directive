<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;

final class RenderRecord extends AbstractRecord
{
    public function __construct(
        public readonly RenderType $type,
        public readonly ?DirectiveMetadataCollection $directives = null,
        public readonly ?string $signature = null,
        public readonly ?string $message = null,
        public readonly ?DisplayMessageRecord $messageRecord = null,
        public readonly ?DisplayTableRecord $tableRecord = null,
    ) {}
}
