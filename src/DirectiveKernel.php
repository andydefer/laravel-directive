<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\ContainerInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use ReflectionClass;

/**
 * The core kernel that orchestrates directive execution.
 *
 * Extends DirectiveDiscoveryService to inherit all discovery capabilities
 * and adds execution methods.
 */
final class DirectiveKernel extends DirectiveDiscoveryService
{
    /**
     * @param  ContainerInterface  $container  The container instance
     */
    private function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct($container);
    }

    /**
     * Initialize the kernel with a container.
     */
    public static function init(ContainerInterface $container): self
    {
        return new self($container);
    }

    /**
     * Get the container instance.
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

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
     * Execute a directive by its fully qualified class name.
     *
     * @param  class-string<AbstractDirective>  $fqcn  The fully qualified class name
     * @param  array<int, string>  $argv  The arguments (without the directive name)
     * @return ExitCode The exit code
     */
    public function runDirective(string $fqcn, array $argv = []): ExitCode
    {
        // Register the directive (subject to validation)
        $this->addDirective($fqcn);

        // Extract the command name from the signature
        $reflection = new ReflectionClass($fqcn);
        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $instance->getSignature();
        $parts = explode(' ', $signature);
        $commandName = $parts[0];

        // Build the full argv array
        $fullArgv = array_merge(['directive', $commandName], $argv);

        return $this->run($fullArgv);
    }

    /**
     * Execute a directive by its full query string.
     *
     * @param  string  $query  The full query string (e.g., "greet John")
     * @return ExitCode The exit code
     */
    public function runSignature(string $query): ExitCode
    {
        $argv = array_merge(['directive'], explode(' ', $query));

        return $this->run($argv);
    }

    /**
     * Checks if no command was provided.
     */
    private function isMissingCommand(array $argv): bool
    {
        return count($argv) < 2;
    }

    /**
     * Executes the default help directive.
     */
    private function executeHelpDirective(): ExitCode
    {
        return $this->executeDirective('help', 'help');
    }

    /**
     * Parses the command-line arguments into command name and query.
     *
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
     */
    private function executeDirective(string $commandName, string $query): ExitCode
    {
        $directives = $this->discover();

        $directive = $this->findDirective($directives, $commandName);

        if ($directive === null) {
            return ExitCode::NOT_FOUND;
        }

        return $this->instantiateAndRun($directive, $query);
    }

    /**
     * Finds a directive by command name or alias.
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
     */
    private function matchesCommandName(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        $signatureParts = explode(' ', $directive->signature);
        $directiveName = $signatureParts[0];

        return $directiveName === $commandName;
    }

    /**
     * Checks if a directive matches a command alias.
     */
    private function matchesAlias(DirectiveMetadataRecord $directive, string $commandName): bool
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
     */
    private function instantiateAndRun(DirectiveMetadataRecord $directive, string $query): ExitCode
    {
        $instance = new $directive->class($this, $query);

        return $instance->run();
    }
}
