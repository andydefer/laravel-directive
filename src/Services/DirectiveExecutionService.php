<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Records\Collections\TypedCollection;

class DirectiveExecutionService
{
    private ?LaravelBootstrapper $laravelBootstrapper = null;

    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
    ) {}

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): void
    {
        $this->laravelBootstrapper = $bootstrapper;
    }

    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        $signature = $record->signature;

        if ($signature === '--help' || $signature === '-h') {
            $this->renderer->renderHelp();

            return ExitCode::SUCCESS;
        }

        if ($signature === '--list' || $signature === '-l') {
            $directives = $this->discovery->discover();
            $this->renderer->renderList($directives);

            return ExitCode::SUCCESS;
        }

        if ($signature === '--version' || $signature === '-v') {
            $this->renderer->renderVersion();

            return ExitCode::SUCCESS;
        }

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
            $baseSignature = explode(' ', $directive->signature)[0];

            if ($baseSignature === $signature) {
                return $directive;
            }
            if ($directive->aliases->contains($signature)) {
                return $directive;
            }
        }

        return null;
    }

    private function executeDirective(string $class, DirectiveExecutionRecord $record): ExitCode
    {
        $reflection = new \ReflectionClass($class);
        $tempInstance = $reflection->newInstanceWithoutConstructor();

        if ($this->laravelBootstrapper !== null && property_exists($tempInstance, 'laravelBootstrapper')) {
            $reflectionProperty = $reflection->getProperty('laravelBootstrapper');
            $reflectionProperty->setValue($tempInstance, $this->laravelBootstrapper);
        }

        if ($tempInstance->shouldBootLaravel() && $this->laravelBootstrapper !== null) {
            $this->bootLaravelIfNeeded();
        }

        $fullSignature = $tempInstance->getSignature();
        $parsed = $this->parser->parse($fullSignature, $record->arguments);

        $directive = $this->hydrator->hydrate($class, $parsed);

        if ($this->laravelBootstrapper !== null && $directive instanceof AbstractDirective) {
            $reflection = new \ReflectionClass($directive);
            $reflectionProperty = $reflection->getProperty('laravelBootstrapper');
            $reflectionProperty->setValue($directive, $this->laravelBootstrapper);
        }

        $result = $directive->execute();

        if ($result->isSuccess()) {
            $this->renderer->renderSuccess('Directive executed successfully');
        } else {
            $this->renderer->renderError('Directive execution failed');
        }

        return $result;
    }

    private function bootLaravelIfNeeded(): void
    {
        if ($this->laravelBootstrapper === null) {
            $this->renderer->renderWarning('Laravel bootstrapper not available.');

            return;
        }

        if ($this->laravelBootstrapper->bootstrap()) {
            $this->renderer->renderDebug('Laravel bootstrapped successfully.');
        } else {
            $error = $this->laravelBootstrapper->getError();
            $this->renderer->renderWarning(
                $error ?? 'Could not bootstrap Laravel. Running without Laravel features.'
            );
        }
    }
}
