<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

class DirectiveExecutionService
{
    public function __construct(
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
        private readonly DirectiveRegistrar $registrar,
        private readonly DirectiveInteractionService $interaction,
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
            $directives = $this->registrar->getAllDirectivesMetadata();
            $this->renderer->renderList($directives);
            return ExitCode::SUCCESS;
        }

        $classes = $this->registrar->find($signature);

        if ($classes->isEmpty()) {
            $this->renderer->renderNotFound($signature);
            return ExitCode::NOT_FOUND;
        }

        if ($classes->count() > 1) {
            return $this->handleConflict($record);
        }

        $class = $classes->firstItem();
        return $this->executeDirective($class, $record);
    }

    private function handleConflict(DirectiveExecutionRecord $record): ExitCode
    {
        $classes = $this->registrar->find($record->signature);
        $classNames = new StringTypedCollection();
        $signatures = new StringTypedCollection();
        $descriptions = new StringTypedCollection();

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $instance = $reflection->newInstanceWithoutConstructor();

            $classNames->add($reflection->getShortName());
            $signatures->add($instance->getSignature());
            $descriptions->add($instance->getDescription());
        }

        $conflictRecord = new ConflictDisplayRecord(
            name: $record->signature,
            classNames: $classNames,
            signatures: $signatures,
            descriptions: $descriptions,
        );

        $this->renderer->renderConflict($conflictRecord);

        $choice = $this->interaction->askUserChoice($record->signature, $classes->count());

        if ($choice === 0) {
            $this->renderer->renderError('Invalid choice');
            return ExitCode::INVALID_ARGUMENT;
        }

        $selectedClass = $classes->toArray()[$choice - 1];

        return $this->executeDirective($selectedClass, $record);
    }

    private function executeDirective(string $class, DirectiveExecutionRecord $record): ExitCode
    {
        $reflection = new \ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $instance->getSignature();

        $parsed = $this->parser->parse($signature, $record->arguments);

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
