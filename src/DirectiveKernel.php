<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use Illuminate\Foundation\Application;

/**
 * The core kernel that orchestrates directive execution.
 *
 * Responsible for discovering directives, resolving the appropriate
 * directive for a given command, and executing it.
 */
final class DirectiveKernel
{
    /**
     * @param  Application  $app  The Laravel application instance
     * @param  DirectiveDiscoveryService  $discovery  The directive discovery service
     */
    public function __construct(
        private readonly Application $app,
        private readonly DirectiveDiscoveryService $discovery,
    ) {}

    /**
     * Executes the kernel with the given command-line arguments.
     *
     * @param  array<int, string>  $argv  The command-line arguments
     * @return ExitCode The exit code
     */
    public function run(array $argv): ExitCode
    {
        if ($this->isMissingCommand($argv)) {
            return $this->executeHelpDirective();
        }

        [$commandName, $query] = $this->parseArguments($argv);

        return $this->executeDirective($commandName, $query);
    }

    /**
     * Checks if no command was provided.
     *
     * @param  array<int, string>  $argv  The command-line arguments
     * @return bool True if no command was provided, false otherwise
     */
    private function isMissingCommand(array $argv): bool
    {
        return count($argv) < 2;
    }

    /**
     * Executes the default help directive.
     *
     * @return ExitCode The exit code
     */
    private function executeHelpDirective(): ExitCode
    {
        return $this->executeDirective('help', 'help');
    }

    /**
     * Parses the command-line arguments into command name and query.
     *
     * @param  array<int, string>  $argv  The command-line arguments
     * @return array{0: string, 1: string} The command name and query
     */
    private function parseArguments(array $argv): array
    {
        $query = implode(' ', array_slice($argv, 1));
        $parts = explode(' ', $query);
        $commandName = $parts[0];

        return [$commandName, $query];
    }

    /**
     * Executes a directive by name with the given query.
     *
     * @param  string  $commandName  The command name to execute
     * @param  string  $query  The query string
     * @return ExitCode The exit code
     */
    private function executeDirective(string $commandName, string $query): ExitCode
    {
        $directives = $this->discovery->discover();

        $directive = $this->findDirective($directives, $commandName);

        if ($directive === null) {
            return ExitCode::NOT_FOUND;
        }

        return $this->instantiateAndRun($directive, $query);
    }

    /**
     * Finds a directive by command name or alias.
     *
     * @param  DirectiveMetadataCollection  $directives  The collection of directives
     * @param  string  $commandName  The command name to find
     * @return DirectiveMetadataRecord|null The directive metadata, or null if not found
     */
    private function findDirective(DirectiveMetadataCollection $directives, string $commandName): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            if ($this->matchesCommandName($directive, $commandName)) {
                return $directive;
            }

            if ($this->matchesAlias($directive, $commandName)) {
                return $directive;
            }
        }

        return null;
    }

    /**
     * Checks if a directive matches a command name.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive metadata
     * @param  string  $commandName  The command name to check
     * @return bool True if matches, false otherwise
     */
    private function matchesCommandName(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        $signatureParts = explode(' ', $directive->signature);
        $directiveName = $signatureParts[0];

        return $directiveName === $commandName;
    }

    /**
     * Checks if a directive matches a command alias.
     *
     * @param  object  $directive  The directive metadata
     * @param  string  $commandName  The command name to check
     * @return bool True if matches, false otherwise
     */
    private function matchesAlias(object $directive, string $commandName): bool
    {
        foreach ($directive->aliases as $alias) {
            if ($alias === $commandName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Instantiates and runs a directive.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive metadata
     * @param  string  $query  The query string
     * @return ExitCode The exit code
     */
    private function instantiateAndRun(DirectiveMetadataRecord $directive, string $query): ExitCode
    {
        $instance = $this->app->make($directive->class, [
            'query' => $query,
        ]);

        return $instance->run();
    }
}
