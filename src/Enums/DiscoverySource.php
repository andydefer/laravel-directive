<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Enums;

/**
 * Discovery sources for directives.
 */
enum DiscoverySource: string
{
    case VENDOR = 'vendor';
    case WORKSPACE = 'workspace';
    case BUILTIN = 'builtin';
    case CUSTOM = 'custom';
}
