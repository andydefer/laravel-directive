<?php

namespace AndyDefer\Directive\Enums;

enum PathContextType: string
{
    case FILE_DIRECTORY = '__DIR__';
    case WORKING_DIRECTORY = 'getcwd';
}
