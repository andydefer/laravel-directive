<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum RenderType: string
{
    case HELP = 'help';
    case LIST = 'list';
    case NOT_FOUND = 'not-found';
    case SUCCESS = 'success';
    case ERROR = 'error';
    case EMPTY = 'empty';
    case CONFLICT = 'conflict';
    case TABLE = 'table';
    case VALIDATION_ERROR = 'validation-error';
    case DISPLAY_MESSAGE = 'display-message';

    public function getStubName(): string
    {
        return $this->value . '.stub';
    }

    public function getDefaultMessage(): string
    {
        return match ($this) {
            self::SUCCESS => 'Directive executed successfully',
            self::ERROR => 'Directive execution failed',
            default => '',
        };
    }
}
