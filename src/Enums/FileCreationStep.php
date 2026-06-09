<?php
// src/Enums/FileCreationStep.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum FileCreationStep: string
{
    case START = 'start';
    case CREATING_DIRECTORY = 'creating_directory';
    case READING_STUB = 'reading_stub';
    case REPLACING_VARIABLES = 'replacing_variables';
    case WRITING_FILE = 'writing_file';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
