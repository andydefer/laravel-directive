<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\Sequential;

final class DirectiveResponseRecord extends AbstractRecord
{
    public function __construct(
        public readonly ExitCode $exit_code,
        public readonly string $output,
        public readonly Sequential $problems = new Sequential,
    ) {}
}
