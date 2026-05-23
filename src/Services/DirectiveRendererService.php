<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Tasks\RenderTask;
use AndyDefer\Records\Collections\TypedCollection;

class DirectiveRendererService
{
    public function __construct(
        private readonly RenderTask $renderTask,
    ) {}

    public function renderHelp(): void
    {
        $record = new RenderRecord(type: RenderType::HELP);
        echo $this->renderTask->execute($record, RenderType::HELP);
    }

    public function renderList(TypedCollection $directives): void
    {
        $record = new RenderRecord(type: RenderType::LIST, directives: $directives);
        echo $this->renderTask->execute($record, RenderType::LIST);
    }

    public function renderNotFound(string $signature): void
    {
        $record = new RenderRecord(type: RenderType::NOT_FOUND, signature: $signature);
        echo $this->renderTask->execute($record, RenderType::NOT_FOUND);
    }

    public function renderSuccess(string $message): void
    {
        $record = new RenderRecord(type: RenderType::SUCCESS, message: $message);
        echo $this->renderTask->execute($record, RenderType::SUCCESS);
    }

    public function renderError(string $message): void
    {
        $record = new RenderRecord(type: RenderType::ERROR, message: $message);
        echo $this->renderTask->execute($record, RenderType::ERROR);
    }

    public function renderConflict(ConflictDisplayRecord $record): void
    {
        echo $this->renderTask->execute($record, RenderType::CONFLICT);
    }

    public function renderTable(DisplayTableRecord $record): void
    {
        echo $this->renderTask->execute($record, RenderType::TABLE);
    }

    public function renderValidationError(ValidationResultRecord $record): void
    {
        echo $this->renderTask->execute($record, RenderType::VALIDATION_ERROR);
    }
}
