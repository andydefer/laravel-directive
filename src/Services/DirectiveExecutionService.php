<?php

// src/Services/DirectiveExecutionService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use InvalidArgumentException;

/**
 * Service responsible for executing directives.
 */
class DirectiveExecutionService
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
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

        $directives = $this->discovery->discover();
        $directiveMetadata = $this->findDirective($directives, $record->signature);

        if ($directiveMetadata === null) {
            $this->renderer->renderNotFound($record->signature);

            return ExitCode::NOT_FOUND;
        }

        try {
            $exitCode = $this->executeDirective($directiveMetadata, $record);

            // ✅ Ajouter le rendu du résultat
            if ($exitCode === ExitCode::SUCCESS) {
                $this->renderer->renderSuccess('Directive executed successfully');
            } else {
                $this->renderer->renderError('Directive execution failed');
            }

            return $exitCode;
        } catch (InvalidArgumentException $e) {
            $this->renderer->renderError($e->getMessage());

            return ExitCode::INVALID_ARGUMENT;
        } catch (\Throwable $e) {
            $this->renderer->renderError($e->getMessage());

            return ExitCode::FAILURE;
        }
    }

    private function isHelpCommand(string $signature): bool
    {
        return $signature === '--help' || $signature === '-h';
    }

    private function isListCommand(string $signature): bool
    {
        return $signature === '--list' || $signature === '-l';
    }

    private function isVersionCommand(string $signature): bool
    {
        return $signature === '--version' || $signature === '-v';
    }

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

    private function extractBaseSignature(string $fullSignature): string
    {
        $baseSignature = explode(' ', $fullSignature)[0];
        $baseSignature = explode('{', $baseSignature)[0];

        return rtrim($baseSignature, '-');
    }

    private function executeDirective(DirectiveMetadataRecord $metadata, DirectiveExecutionRecord $record): ExitCode
    {
        $parsed = $this->parser->parse($metadata->signature, $record->arguments);
        $directive = $this->hydrator->hydrate($metadata->class, $parsed);

        return $directive->execute();
    }
}
