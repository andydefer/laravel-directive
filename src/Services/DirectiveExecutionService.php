<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Tasks\DisplayErrorTask;
use AndyDefer\Directive\Tasks\DisplayMessageTask;
use AndyDefer\Records\Collections\TypedCollection;

/**
 * Service responsible for executing directives.
 */
class DirectiveExecutionService
{
    private TypedCollection $directives;

    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
        private readonly DisplayMessageTask $displayMessage,
        private readonly DisplayErrorTask $displayError,
    ) {
        $this->directives = $this->discovery->discover();
    }

    /**
     * Execute a directive.
     */
    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        $signature = $record->signature;

        if ($this->isListCommand($signature)) {
            return $this->handleListCommand();
        }

        if ($this->isHelpCommand($signature)) {
            return $this->handleHelpCommand();
        }

        return $this->executeDirective($record);
    }

    /**
     * Check if a directive exists.
     */
    public function exists(string $signature): bool
    {
        return $this->findDirective($signature) !== null;
    }

    /**
     * List all available directives.
     */
    public function listDirectives(): TypedCollection
    {
        return $this->directives;
    }

    /**
     * Find a directive by its signature.
     */
    public function findDirectiveBySignature(string $signature): ?DirectiveMetadataRecord
    {
        return $this->findDirective($signature);
    }

    /**
     * Execute a directive and return the exit code.
     */
    private function executeDirective(DirectiveExecutionRecord $record): ExitCode
    {
        $directive = $this->findDirective($record->signature);

        if ($directive === null) {
            $this->displayError->execute(
                $this->renderer->renderNotFound($record->signature)
            );

            return ExitCode::NOT_FOUND;
        }

        $parsed = $this->parser->parse($directive->signature, $record->arguments);
        $command = $this->hydrator->hydrate($directive->class, $parsed);

        return $command->execute();
    }

    /**
     * Handle the --list command.
     */
    private function handleListCommand(): ExitCode
    {
        $this->displayMessage->execute(
            new DisplayMessageRecord(
                $this->renderer->renderList($this->directives),
                MessageType::LINE
            )
        );

        return ExitCode::SUCCESS;
    }

    /**
     * Handle the --help command.
     */
    private function handleHelpCommand(): ExitCode
    {
        $this->displayMessage->execute(
            new DisplayMessageRecord(
                $this->renderer->renderHelp(),
                MessageType::LINE
            )
        );

        return ExitCode::SUCCESS;
    }

    /**
     * Find a directive by signature or alias.
     */
    private function findDirective(string $signature): ?DirectiveMetadataRecord
    {
        foreach ($this->directives as $directive) {
            // Extraire le nom de base (ex: 'test:echo' depuis 'test:echo {message?}')
            $baseSignature = explode(' {', $directive->signature)[0];

            if ($baseSignature === $signature) {
                return $directive;
            }
            if ($directive->aliases->contains($signature)) {
                return $directive;
            }
        }
        return null;
    }

    /**
     * Check if the command is a list command.
     */
    private function isListCommand(string $signature): bool
    {
        return $signature === '--list' || $signature === '-l';
    }

    /**
     * Check if the command is a help command.
     */
    private function isHelpCommand(string $signature): bool
    {
        return $signature === '--help' || $signature === '-h';
    }
}
