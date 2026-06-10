<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use InvalidArgumentException;

/**
 * Service responsible for executing directives.
 *
 * This service orchestrates the entire execution flow:
 * - Handles global commands (help, list, version)
 * - Discovers available directives
 * - Finds the target directive by signature, alias, or base name
 * - Parses arguments, boots Laravel if needed, hydrates the directive
 * - Executes the directive and returns the exit code
 *
 * @author Andy Defer
 */
class DirectiveExecutionService
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
        private readonly LaravelBootstrapperContext $laravelBootstrapperContext,
    ) {}

    /**
     * Executes a directive based on the execution record.
     *
     * @param  DirectiveExecutionRecord  $record  The execution record containing signature and arguments
     * @return ExitCode The exit code indicating success or failure
     */
    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        // Handle global commands
        if ($this->isHelpCommand($record->signature)) {
            $this->renderer->renderHelp();

            return ExitCode::SUCCESS;
        }

        if ($this->isListCommand($record->signature)) {
            $directives = $this->discovery->discover();
            $this->renderer->renderList($directives);

            return ExitCode::SUCCESS;
        }

        if ($this->isVersionCommand($record->signature)) {
            $this->renderer->renderVersion();

            return ExitCode::SUCCESS;
        }

        // Load directives and find the target
        $directives = $this->discovery->discover();
        $directiveMetadata = $this->findDirective($directives, $record->signature);

        if ($directiveMetadata === null) {
            $this->renderer->renderNotFound($record->signature);

            return ExitCode::NOT_FOUND;
        }

        try {
            return $this->executeDirective($directiveMetadata, $record);
        } catch (InvalidArgumentException $e) {
            $this->renderer->renderError($e->getMessage());

            return ExitCode::INVALID_ARGUMENT;
        } catch (\Throwable $e) {
            $this->renderer->renderError($e->getMessage());

            return ExitCode::FAILURE;
        }
    }

    /**
     * Checks if the signature is a help command.
     */
    private function isHelpCommand(string $signature): bool
    {
        return $signature === '--help' || $signature === '-h';
    }

    /**
     * Checks if the signature is a list command.
     */
    private function isListCommand(string $signature): bool
    {
        return $signature === '--list' || $signature === '-l';
    }

    /**
     * Checks if the signature is a version command.
     */
    private function isVersionCommand(string $signature): bool
    {
        return $signature === '--version' || $signature === '-v';
    }

    /**
     * Finds a directive metadata by signature, alias, or base name.
     */
    private function findDirective(DirectiveMetadataCollection $directives, string $signature): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            if ($directive->signature === $signature) {
                return $directive;
            }

            if ($directive->aliases->contains($signature)) {
                return $directive;
            }

            $baseSignature = $this->extractBaseSignature($directive->signature);
            if ($baseSignature === $signature) {
                return $directive;
            }
        }

        return null;
    }

    /**
     * Extracts the base name from a full signature.
     */
    private function extractBaseSignature(string $fullSignature): string
    {
        $baseSignature = explode(' ', $fullSignature)[0];
        $baseSignature = explode('{', $baseSignature)[0];

        return rtrim($baseSignature, '-');
    }

    /**
     * Executes a directive after finding its metadata.
     */
    private function executeDirective(DirectiveMetadataRecord $metadata, DirectiveExecutionRecord $record): ExitCode
    {
        $parsed = $this->parser->parse($metadata->signature, $record->arguments);

        if ($this->shouldBootLaravel($metadata->class)) {
            $this->laravelBootstrapperContext->bootstrap();
        }

        $directive = $this->hydrator->hydrate($metadata->class, $parsed);
        $result = $directive->execute();

        if ($result === ExitCode::SUCCESS) {
            $this->renderer->renderSuccess('Directive executed successfully');
        } else {
            $this->renderer->renderError('Directive execution failed');
        }

        return $result;
    }

    /**
     * Determines if a directive requires Laravel bootstrapping.
     */
    private function shouldBootLaravel(string $class): bool
    {
        try {
            $reflection = new \ReflectionClass($class);
            if (!$reflection->hasMethod('shouldBootLaravel')) {
                return false;
            }
            $tempInstance = $reflection->newInstanceWithoutConstructor();

            return $tempInstance->shouldBootLaravel();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
