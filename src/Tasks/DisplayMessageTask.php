<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Records\DisplayMessageRecord;

class DisplayMessageTask
{
    public function execute(DisplayMessageRecord $record): void
    {
        $color = $record->type->getColorCode();
        $reset = $record->type->getResetCode();

        echo $color.$record->message.$reset."\n";
    }
}
