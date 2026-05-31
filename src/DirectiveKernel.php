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
 */
class DirectiveKernel
{
    public function __construct(
        private readonly DirectiveExecutionService $service,
        private readonly SignatureValidationService $signatureValidator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function run(array $argv): ExitCode
    {
        if (count($argv) < 2) {
            return $this->showDefaultHelp();
        }

        $signature = $argv[1];

        // Check for long options --help, --list
        if (str_starts_with($signature, '--')) {
            return $this->executeDirective($signature, []);
        }

        // Check for allowed short options -h, -l, -v
        if (ShortOption::isValid($signature)) {
            return $this->executeDirective($signature, []);
        }

        $validation = $this->signatureValidator->validate($signature);

        if (! $validation->isValid) {
            $this->renderer->renderValidationError($validation);

            return ExitCode::INVALID_ARGUMENT;
        }

        $arguments = array_slice($argv, 2);

        return $this->executeDirective($signature, $arguments);
    }

    private function executeDirective(string $signature, array $arguments): ExitCode
    {
        $argumentCollection = new StringTypedCollection;

        foreach ($arguments as $argument) {
            $argumentCollection->add($argument);
        }

        $record = new DirectiveExecutionRecord(
            signature: $signature,
            arguments: $argumentCollection,
        );

        return $this->service->execute($record);
    }

    private function showDefaultHelp(): ExitCode
    {
        return $this->executeDirective('--help', []);
    }
}
