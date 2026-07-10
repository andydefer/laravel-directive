<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Discovers;

use AndyDefer\Directive\BuiltIn\CleanLogsDirective;
use AndyDefer\Directive\BuiltIn\HelpDirective;
use AndyDefer\Directive\BuiltIn\KernelAuditDirective;
use AndyDefer\Directive\BuiltIn\ListDirective;
use AndyDefer\Directive\BuiltIn\VersionDirective;

/**
 * Discovery source for built-in directives.
 *
 * Provides the core directives that come bundled with the package:
 * - ListDirective: Lists all available directives
 * - HelpDirective: Displays help information
 * - VersionDirective: Shows version information
 */
final class BuiltInDirectiveDiscovery extends AbstractDiscovery
{
    /**
     * The list of built-in directive class names.
     *
     * @var array<int, class-string>
     */
    private array $builtInDirectives = [
        ListDirective::class,
        HelpDirective::class,
        VersionDirective::class,
        CleanLogsDirective::class,
        KernelAuditDirective::class,
    ];

    /**
     * Discovers all built-in directives.
     *
     * @return array<int, class-string> The list of built-in directive class names
     */
    public function discover(): array
    {
        return $this->builtInDirectives;
    }
}
