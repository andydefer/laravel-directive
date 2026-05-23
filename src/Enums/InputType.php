<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum InputType: string
{
    case SIMPLE_QUESTION = 'simple-question';
    case CONFIRMATION = 'confirmation';
    case USER_CHOICE = 'user-choice';

    public function getPromptSuffix(): string
    {
        return match ($this) {
            self::SIMPLE_QUESTION => ' ',
            self::CONFIRMATION => ' (y/n) ',
            self::USER_CHOICE => '',
        };
    }
}
