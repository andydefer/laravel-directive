<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tasks;

use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Records\DisplayMessageRecord;

class DisplayErrorTask
{
    public function execute(string $message): void
    {
        $record = new DisplayMessageRecord($message, MessageType::ERROR);
        echo MessageType::ERROR->getColorCode().$message.MessageType::ERROR->getResetCode()."\n";
    }
}
