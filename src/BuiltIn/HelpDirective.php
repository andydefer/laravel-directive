<?php

declare(strict_types=1);

namespace AndyDefer\Directive\BuiltIn;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Built-in directive that displays help information.
 *
 * Shows a list of available directives along with global options.
 * This directive acts as a wrapper around the ListDirective.
 */
final class HelpDirective extends AbstractDirective
{
    /**
     * The signature used to invoke this directive.
     */
    public function getSignature(): string
    {
        return 'help';
    }

    /**
     * The human-readable description of this directive.
     */
    public function getDescription(): string
    {
        return 'Display help information';
    }

    /**
     * The list of aliases that can be used to invoke this directive.
     */
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['-h', '--help']);
    }

    /**
     * Executes the help directive.
     *
     * Displays the list of available directives and then shows
     * additional global options information.
     */
    protected function execute(): ExitCode
    {
        $this->call('list');

        $this->displayGlobalOptions();

        return ExitCode::SUCCESS;
    }

    /**
     * Displays the global options available in the CLI.
     */
    private function displayGlobalOptions(): void
    {
        $this->newLine();
        $this->line('Global options:');
        $this->line('  --help, -h     Show this help message');
        $this->line('  --list, -l     List all available commands');
        $this->line('  --version, -v  Show the application version');
    }
}
