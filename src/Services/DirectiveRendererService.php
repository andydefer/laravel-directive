<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;

/**
 * Service for rendering various directive outputs.
 *
 * Acts as a facade over the RenderDispatcher, providing dedicated methods for each
 * render type. Handles conditional rendering (e.g., debug output only when
 * debugging is enabled) and delegates the actual rendering to the RenderDispatcher.
 */
class DirectiveRendererService
{
    public function __construct(
        private readonly RenderDispatcher $renderDispatcher,
    ) {}

    /**
     * Render the help screen.
     *
     * Displays usage instructions and available commands.
     */
    public function renderHelp(): void
    {
        $record = new RenderRecord(type: RenderType::HELP);
        echo $this->renderDispatcher->execute($record, RenderType::HELP);
    }

    /**
     * Render a list of available directives.
     *
     * @param DirectiveMetadataCollection $directives Collection of directive metadata to display
     */
    public function renderList(DirectiveMetadataCollection $directives): void
    {
        $record = new RenderRecord(type: RenderType::LIST, directives: $directives);
        echo $this->renderDispatcher->execute($record, RenderType::LIST);
    }

    /**
     * Render a "directive not found" error message.
     *
     * @param string $signature The directive signature that was not found
     */
    public function renderNotFound(string $signature): void
    {
        $record = new RenderRecord(type: RenderType::NOT_FOUND, signature: $signature);
        echo $this->renderDispatcher->execute($record, RenderType::NOT_FOUND);
    }

    /**
     * Render a success message.
     *
     * @param string $message The success message to display
     */
    public function renderSuccess(string $message): void
    {
        $record = new RenderRecord(type: RenderType::SUCCESS, message: $message);
        echo $this->renderDispatcher->execute($record, RenderType::SUCCESS);
    }

    /**
     * Render an error message.
     *
     * @param string $message The error message to display
     */
    public function renderError(string $message): void
    {
        $record = new RenderRecord(type: RenderType::ERROR, message: $message);
        echo $this->renderDispatcher->execute($record, RenderType::ERROR);
    }

    /**
     * Render a warning message.
     *
     * @param string $message The warning message to display
     */
    public function renderWarning(string $message): void
    {
        $record = new RenderRecord(type: RenderType::WARNING, message: $message);
        echo $this->renderDispatcher->execute($record, RenderType::WARNING);
    }

    /**
     * Render a debug message conditionally.
     *
     * Outputs the message only if DIRECTIVE_DEBUG or APP_DEBUG environment
     * variables are set to 'true'.
     *
     * @param string $message The debug message to display
     */
    public function renderDebug(string $message): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $record = new RenderRecord(type: RenderType::DEBUG, message: $message);
        echo $this->renderDispatcher->execute($record, RenderType::DEBUG);
    }

    /**
     * Render the version information.
     */
    public function renderVersion(): void
    {
        $record = new RenderRecord(type: RenderType::VERSION);
        echo $this->renderDispatcher->execute($record, RenderType::VERSION);
    }

    /**
     * Render a conflict display.
     *
     * @param ConflictDisplayRecord $record The conflict data to display
     */
    public function renderConflict(ConflictDisplayRecord $record): void
    {
        echo $this->renderDispatcher->execute($record, RenderType::CONFLICT);
    }

    /**
     * Render a table display.
     *
     * @param DisplayTableRecord $record The table data to display
     */
    public function renderTable(DisplayTableRecord $record): void
    {
        echo $this->renderDispatcher->execute($record, RenderType::TABLE);
    }

    /**
     * Render a validation error.
     *
     * @param ValidationResultRecord $record The validation error data to display
     */
    public function renderValidationError(ValidationResultRecord $record): void
    {
        echo $this->renderDispatcher->execute($record, RenderType::VALIDATION_ERROR);
    }

    /**
     * Check if debugging is enabled.
     *
     * @return bool True if debugging is enabled, false otherwise
     */
    private function isDebugEnabled(): bool
    {
        return getenv('DIRECTIVE_DEBUG') === 'true' || getenv('APP_DEBUG') === 'true';
    }
}
