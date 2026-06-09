<?php
// src/Enums/PathType.php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

enum PathType: string
{
    case FILE = 'file';
    case DIRECTORY = 'directory';
}
