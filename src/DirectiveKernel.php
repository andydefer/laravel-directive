<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\ShortOption;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Core kernel that orchestrates directive execution from CLI.
 *
 * The DirectiveKernel is the main entry point for the CLI application.
 * It parses raw command-line arguments, validates directive signatures,
 * and delegates execution to the DirectiveExecutionService.
 *
 * @example
 * $kernel = new DirectiveKernel($executionService, $validator, $renderer);
 * $exitCode = $kernel->run(['directive', 'user:create', 'John']);
 *
 * @author Andy Defer
 */
final class DirectiveKernel
{
    public function __construct(
        private readonly DirectiveExecutionService $service,
        private readonly SignatureValidationService $signatureValidator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    /**
     * Runs the kernel with the given command-line arguments.
     *
     * This method parses the arguments, determines which directive to execute,
     * and returns the appropriate exit code.
     *
     * @param array<int, string> $argv Command-line arguments (e.g., ['directive', 'user:create', 'John'])
     *
     * @return ExitCode The exit code indicating success or failure
     */
    public function run(array $argv): ExitCode
    {
        if (count($argv) < 2) {
            return $this->showDefaultHelp();
        }

        $signature = $argv[1];

        if ($this->isGlobalOption($signature)) {
            return $this->executeDirective($signature, []);
        }

        $validation = $this->signatureValidator->validate($signature);

        if (!$validation->isValid) {
            $this->renderer->renderValidationError($validation);
            return ExitCode::INVALID_ARGUMENT;
        }

        $arguments = array_slice($argv, 2);

        return $this->executeDirective($signature, $arguments);
    }

    /**
     * Checks if the signature is a global CLI option.
     *
     * Global options include:
     * - Long options starting with '--' (--help, --list, --version)
     * - Short options (-h, -l, -v)
     *
     * @param string $signature The directive signature or option
     *
     * @return bool True if the signature is a global option
     */
    private function isGlobalOption(string $signature): bool
    {
        return str_starts_with($signature, '--') || ShortOption::isValid($signature);
    }

    /**
     * Executes a directive with the given signature and arguments.
     *
     * @param string $signature The directive signature (e.g., 'user:create')
     * @param array<int, string> $arguments The list of arguments to pass to the directive
     *
     * @return ExitCode The exit code from the directive execution
     */
    private function executeDirective(string $signature, array $arguments): ExitCode
    {
        $record = DirectiveExecutionRecord::from([
            'signature' => $signature,
            'arguments' => $arguments,
        ]);

        return $this->service->execute($record);
    }

    /**
     * Shows the default help screen when no arguments are provided.
     *
     * @return ExitCode Always returns SUCCESS after showing help
     */
    private function showDefaultHelp(): ExitCode
    {
        return $this->executeDirective('--help', []);
    }
}
