<?php
// src/Contexts/FileCreationContext.php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Enums\FileCreationStep;
use AndyDefer\Directive\Records\PathSegmentsRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class FileCreationContext
{
    private FileCreationStep $currentStep = FileCreationStep::START;
    private string $stubPath = '';
    private string $destinationPath = '';
    private string $stubContent = '';
    private ?string $errorMessage = null;
    private bool $force = false;
    private StringTypedCollection $createdDirectories;
    private StringTypedCollection $createdFiles;
    private StringTypedCollection $transformationLogs;
    private ?PathSegmentsRecord $currentSegments = null;
    private ?string $builtNamespace = null;
    private ?string $builtPath = null;
    private string $currentDirectory = '';

    public function __construct(bool $force = false)
    {
        $this->force = $force;
        $this->createdDirectories = new StringTypedCollection();
        $this->createdFiles = new StringTypedCollection();
        $this->transformationLogs = new StringTypedCollection();
    }

    // ========== GETTERS ==========

    public function getCurrentStep(): FileCreationStep
    {
        return $this->currentStep;
    }

    public function getStubPath(): string
    {
        return $this->stubPath;
    }

    public function getDestinationPath(): string
    {
        return $this->destinationPath;
    }

    public function getStubContent(): string
    {
        return $this->stubContent;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function shouldForce(): bool
    {
        return $this->force;
    }

    public function getCreatedDirectories(): StringTypedCollection
    {
        return $this->createdDirectories;
    }

    public function getCreatedFiles(): StringTypedCollection
    {
        return $this->createdFiles;
    }

    public function getTransformationLogs(): StringTypedCollection
    {
        return $this->transformationLogs;
    }

    public function getCurrentSegments(): ?PathSegmentsRecord
    {
        return $this->currentSegments;
    }

    public function getBuiltNamespace(): ?string
    {
        return $this->builtNamespace;
    }

    public function getBuiltPath(): ?string
    {
        return $this->builtPath;
    }

    public function getCurrentDirectory(): string
    {
        return $this->currentDirectory;
    }

    // ========== SETTERS ==========

    public function setCurrentStep(FileCreationStep $step): void
    {
        $this->currentStep = $step;
    }

    public function setStubPath(string $path): void
    {
        $this->stubPath = $path;
    }

    public function setDestinationPath(string $path): void
    {
        $this->destinationPath = $path;
    }

    public function setStubContent(string $content): void
    {
        $this->stubContent = $content;
    }

    public function setErrorMessage(string $message): void
    {
        $this->errorMessage = $message;
        $this->currentStep = FileCreationStep::FAILED;
    }

    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    public function setCurrentSegments(PathSegmentsRecord $segments): void
    {
        $this->currentSegments = $segments;
    }

    public function setBuiltNamespace(string $namespace): void
    {
        $this->builtNamespace = $namespace;
    }

    public function setBuiltPath(string $path): void
    {
        $this->builtPath = $path;
    }

    public function setCurrentDirectory(string $directory): void
    {
        $this->currentDirectory = $directory;
    }

    // ========== ADDERS ==========

    public function addCreatedDirectory(string $path): void
    {
        $this->createdDirectories->add($path);
    }

    public function addCreatedFile(string $path): void
    {
        $this->createdFiles->add($path);
    }

    public function addTransformationLog(string $operation, string $input, string $output): void
    {
        $this->transformationLogs->add("{$operation}: '{$input}' → '{$output}'");
    }

    // ========== METHODS ==========

    public function reset(): void
    {
        $this->currentStep = FileCreationStep::START;
        $this->stubPath = '';
        $this->destinationPath = '';
        $this->stubContent = '';
        $this->errorMessage = null;
        $this->createdDirectories = new StringTypedCollection();
        $this->createdFiles = new StringTypedCollection();
        $this->transformationLogs = new StringTypedCollection();
        $this->currentSegments = null;
        $this->builtNamespace = null;
        $this->builtPath = null;
        $this->currentDirectory = '';
    }

    // ========== QUESTION METHODS ==========

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    public function isCompleted(): bool
    {
        return $this->currentStep === FileCreationStep::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->currentStep === FileCreationStep::FAILED;
    }

    public function isInProgress(): bool
    {
        return !$this->isCompleted() && !$this->isFailed();
    }

    public function hasCreatedDirectories(): bool
    {
        return $this->createdDirectories->isNotEmpty();
    }

    public function hasCreatedFiles(): bool
    {
        return $this->createdFiles->isNotEmpty();
    }

    public function getCreatedDirectoriesCount(): int
    {
        return $this->createdDirectories->count();
    }

    public function getCreatedFilesCount(): int
    {
        return $this->createdFiles->count();
    }

    public function getTransformationLogsCount(): int
    {
        return $this->transformationLogs->count();
    }
}
