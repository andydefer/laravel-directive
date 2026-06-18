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
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
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
        return true;
    }

    public function execute(DirectiveExecutionRecord $record, $fromCall = false): ExitCode
    {

        $directives = $this->discovery->discover();
        $directiveMetadata = $this->findDirective($directives, $record->signature);

        if ($directiveMetadata === null) {
            $this->renderer->renderNotFound($record->signature);

            return ExitCode::NOT_FOUND;
        }

        try {
            return $this->executeDirective($directiveMetadata, $record, $fromCall);
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
        return 0;
    }

    private function findDirective(DirectiveMetadataCollection $directives, string $signature): ?DirectiveMetadataRecord
    {

        foreach ($directives as $directive) {

            $directiveSignature = new SignatureStructureVO($directive->signature);
            $requiredSignature = new SignatureStructureVO($signature);

            if ($directiveSignature->getSource() === $requiredSignature->getSource()) {

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

    private function executeDirective(DirectiveMetadataRecord $metadata, DirectiveExecutionRecord $record, $fromCall): ExitCode
    {
        $parsed = $this->parser->parse($metadata->signature, $record->arguments);
        $directive = $this->hydrator->hydrate($metadata->class, $parsed);

        $result = $directive->run();

        $calls = $directive->getCalls();
        $hasError = false;
        $count = 0;
        $maxCalls = $this->getMaxCall();

        foreach ($calls as $call) {
            if ($count >= $maxCalls) {
                $this->renderer->renderWarning('⚠️ Maximum calls limit reached');
                break;
            }

            $res = $this->execute($call, true);

            if ($res !== ExitCode::SUCCESS) {
                $this->renderer->renderError("Child directive '{$call->signature}' failed");
                $hasError = true;
                // ❌ Ne pas break, continuer avec les autres calls
            }

            $count++;
        }

        if (! $fromCall) {
            if ($result === ExitCode::SUCCESS && ! $hasError) {
                $this->renderer->renderSuccess('Directive executed successfully');
            } else {
                $this->renderer->renderError('Directive execution failed');
            }
        }

        return $hasError ? ExitCode::FAILURE : $result;
    }

    private function getMaxCall(): int
    {
        return 50;
    }
}
