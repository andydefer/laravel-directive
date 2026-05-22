<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Directive\Enums\DirectiveEventType;
use AndyDefer\Directive\Enums\ExitCode;

final class DirectiveLogRecord extends AbstractRecord
{
    public function __construct(
        public readonly DirectiveEventType $type,
        public readonly string $signature,
        public readonly ?string $class = null,
        public readonly ?ExitCode $exitCode = null,
    ) {}
}
