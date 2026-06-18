<?php

// src/Strategies/DirectiveExecutionStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\ExecutionStrategyInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use InvalidArgumentException;

final class DirectiveExecutionStrategy implements ExecutionStrategyInterface
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function supports(DirectiveExecutionRecord $record): bool
    {
        return true; // Fallback pour toutes les autres commandes
    }

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
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

    public function getPriority(): int
    {
        return 0; // Priorité la plus basse
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

        $result = $directive->run();

        $calls = $directive->getCalls();
        foreach ($calls as $call) {
            $this->execute($call);
        }

        if ($result === ExitCode::SUCCESS) {
            $this->renderer->renderSuccess('Directive executed successfully');
        } else {
            $this->renderer->renderError('Directive execution failed');
        }

        return $result;
    }
}
