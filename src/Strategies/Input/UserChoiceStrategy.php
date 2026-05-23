<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies\Input;

use AndyDefer\Directive\Contracts\InputStrategyInterface;
use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Records\Recordable;

final class UserChoiceStrategy implements InputStrategyInterface
{
    private $inputStream;

    public function __construct($inputStream = STDIN)
    {
        $this->inputStream = $inputStream;
    }

    public function supports(InputType $type): bool
    {
        return $type === InputType::USER_CHOICE;
    }

    public function execute(Recordable $record, InputType $type): mixed
    {
        if (!$record instanceof UserChoiceRecord) {
            return null;
        }

        echo "Which one do you want to use? [1-{$record->max}]: ";
        $input = trim(fgets($this->inputStream));

        if (!is_numeric($input)) {
            return null;
        }

        $choice = (int) $input;

        if ($choice < 1 || $choice > $record->max) {
            return null;
        }

        return $choice;
    }
}
