<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

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
        $signature = $record->signature;

        // Handle built-in commands
        if ($signature === '--help' || $signature === '-h') {
            $this->renderer->renderHelp();
            return ExitCode::SUCCESS;
        }

        if ($signature === '--list' || $signature === '-l') {
            $directives = $this->discovery->discover();
            $this->renderer->renderList($directives);
            return ExitCode::SUCCESS;
        }

        // Find directive by signature or alias
        $directives = $this->discovery->discover();
        $directive = $this->findDirective($directives, $signature);

        if ($directive === null) {
            $this->renderer->renderNotFound($signature);
            return ExitCode::NOT_FOUND;
        }

        return $this->executeDirective($directive->class, $record);
    }

    private function findDirective(TypedCollection $directives, string $signature): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            // Extraire le nom de base (ex: 'test-echo' depuis 'test-echo {message?}')
            $baseSignature = explode(' ', $directive->signature)[0];

            // Check by base signature
            if ($baseSignature === $signature) {
                return $directive;
            }
            // Check by alias
            if ($directive->aliases->contains($signature)) {
                return $directive;
            }
        }
        return null;
    }

    private function executeDirective(string $class, DirectiveExecutionRecord $record): ExitCode
    {
        $reflection = new \ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $fullSignature = $instance->getSignature();

        $parsed = $this->parser->parse($fullSignature, $record->arguments);

        $directive = $this->hydrator->hydrate($class, $parsed);

        $result = $directive->execute();

        if ($result->isSuccess()) {
            $this->renderer->renderSuccess('Directive executed successfully');
        } else {
            $this->renderer->renderError('Directive execution failed');
        }

        return $result;
    }
}
