<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use InvalidArgumentException;

class DirectiveExecutionService
{
    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    /**
     * Find a directive metadata by signature, alias, or base name.
     */
    private function findDirective(DirectiveMetadataCollection $directives, string $signature): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            // 1. Correspondance exacte de la signature complète
            if ($directive->signature === $signature) {
                return $directive;
            }

            // 2. Correspondance par alias
            if ($directive->aliases->contains($signature)) {
                return $directive;
            }

            // 3. Correspondance par nom de base (ex: 'test-echo' au lieu de 'test-echo {message?} {extra?}')
            $baseSignature = explode(' ', $directive->signature)[0];
            $baseSignature = explode('{', $baseSignature)[0];
            $baseSignature = rtrim($baseSignature, '-');

            if ($baseSignature === $signature) {
                return $directive;
            }
        }

        return null;
    }

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        // Help command
        if ($record->signature === '--help' || $record->signature === '-h') {
            $this->renderer->renderHelp();
            return ExitCode::SUCCESS;
        }

        // List command
        if ($record->signature === '--list' || $record->signature === '-l') {
            $directives = $this->discovery->discover();
            $this->renderer->renderList($directives);
            return ExitCode::SUCCESS;
        }

        // Version command
        if ($record->signature === '--version' || $record->signature === '-v') {
            $this->renderer->renderVersion();
            return ExitCode::SUCCESS;
        }

        // Load directives
        $directives = $this->discovery->discover();

        // Find directive by signature, alias, or base name
        $directiveMetadata = $this->findDirective($directives, $record->signature);

        if ($directiveMetadata === null) {
            $this->renderer->renderNotFound($record->signature);
            return ExitCode::NOT_FOUND;
        }

        try {
            // Parse arguments
            $parsed = $this->parser->parse($directiveMetadata->signature, $record->arguments);

            // Boot Laravel if needed
            if ($this->shouldBootLaravel($directiveMetadata->class)) {
                $this->bootLaravel();
            }

            // Hydrate directive
            $directive = $this->hydrator->hydrate($directiveMetadata->class, $parsed);

            // Execute
            $result = $directive->execute();

            if ($result === ExitCode::SUCCESS) {
                $this->renderer->renderSuccess('Directive executed successfully');
            } else {
                $this->renderer->renderError('Directive execution failed');
            }

            return $result;
        } catch (InvalidArgumentException $e) {
            $this->renderer->renderError($e->getMessage());
            return ExitCode::INVALID_ARGUMENT;
        } catch (\Throwable $e) {
            $this->renderer->renderError($e->getMessage());
            return ExitCode::FAILURE;
        }
    }

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

    private function bootLaravel(): void
    {
        if ($this->laravelBootstrapper === null) {
            $this->renderer->renderWarning('Laravel bootstrap file not found');
            return;
        }
        $this->laravelBootstrapper->bootstrap();
    }
}
