<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contexts;

use AndyDefer\Directive\Collections\CreatedPathCollection;
use AndyDefer\Directive\Collections\ExecutionResultCollection;
use AndyDefer\Directive\Collections\StepResultCollection;
use AndyDefer\Directive\Contracts\Configs\DirectiveTestingConfigInterface;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\PathType;
use AndyDefer\Directive\Enums\StepResultStatus;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\Directive\Records\CreatedPathRecord;
use AndyDefer\Directive\Records\DatabaseConnectionRecord;
use AndyDefer\Directive\Records\ExecutionResultRecord;
use AndyDefer\Directive\Records\StepResultRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Testing\ClosureDirectiveRegistry;
use AndyDefer\Directive\Testing\TestDirectiveRegistry;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PDO;

final class DirectiveTestingContext
{
    // Directory state
    private ?string $tempDir = null;

    private ?string $originalCwd = null;

    private bool $inTempDirectory = false;

    // Laravel state
    private ?Application $laravelApp = null;

    private ?LaravelBootstrapperContext $laravelBootstrapperContext = null;

    private bool $bootLaravel = false;

    // Service container state
    private ?Container $container = null;

    private ?DirectiveKernel $kernel = null;

    private ?DirectiveInteractionService $interaction = null;

    // Registry state
    private TestDirectiveRegistry $registry;

    private ClosureDirectiveRegistry $closureRegistry;

    // Configuration
    private DirectiveTestingConfigInterface $config;

    // Execution tracking
    private StringTypedCollection $executedDirectives;

    private ExecutionResultCollection $executionResults;

    private CreatedPathCollection $createdPaths;

    private StepResultCollection $stepResults;

    // Database state
    private ?PDO $databaseConnection = null;

    private ?DatabaseConnectionRecord $databaseConnectionRecord = null;

    // Mode flags
    private bool $integratedMode = false;

    private bool $initialized = false;

    public function __construct(bool $bootLaravel = false)
    {
        $this->bootLaravel = $bootLaravel;
        $this->registry = new TestDirectiveRegistry;
        $this->closureRegistry = new ClosureDirectiveRegistry;
        $this->executedDirectives = new StringTypedCollection;
        $this->executionResults = new ExecutionResultCollection;
        $this->createdPaths = new CreatedPathCollection;
        $this->stepResults = new StepResultCollection;
    }

    // ========== Getters ==========

    public function getTempDir(): ?string
    {
        return $this->tempDir;
    }

    public function getOriginalCwd(): ?string
    {
        return $this->originalCwd;
    }

    public function isInTempDirectory(): bool
    {
        return $this->inTempDirectory;
    }

    public function getLaravelApp(): ?Application
    {
        return $this->laravelApp;
    }

    public function getLaravelBootstrapperContext(): ?LaravelBootstrapperContext
    {
        return $this->laravelBootstrapperContext;
    }

    public function getContainer(): ?Container
    {
        return $this->container;
    }

    public function getKernel(): ?DirectiveKernel
    {
        return $this->kernel;
    }

    public function getInteraction(): ?DirectiveInteractionService
    {
        return $this->interaction;
    }

    public function getRegistry(): TestDirectiveRegistry
    {
        return $this->registry;
    }

    public function getClosureRegistry(): ClosureDirectiveRegistry
    {
        return $this->closureRegistry;
    }

    public function getConfig(): DirectiveTestingConfigInterface
    {
        return $this->config;
    }

    public function getExecutedDirectives(): StringTypedCollection
    {
        return $this->executedDirectives;
    }

    public function getExecutionResults(): ExecutionResultCollection
    {
        return $this->executionResults;
    }

    public function getCreatedPaths(): CreatedPathCollection
    {
        return $this->createdPaths;
    }

    public function getStepResults(): StepResultCollection
    {
        return $this->stepResults;
    }

    public function getDatabaseConnection(): ?PDO
    {
        return $this->databaseConnection;
    }

    public function getDatabaseConnectionRecord(): ?DatabaseConnectionRecord
    {
        return $this->databaseConnectionRecord;
    }

    public function isIntegratedMode(): bool
    {
        return $this->integratedMode;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    // ========== Setters ==========

    public function setTempDir(?string $tempDir): void
    {
        $this->tempDir = $tempDir;
        $this->inTempDirectory = $tempDir !== null;
    }

    public function setOriginalCwd(string $originalCwd): void
    {
        $this->originalCwd = $originalCwd;
    }

    public function setInTempDirectory(bool $inTempDirectory): void
    {
        $this->inTempDirectory = $inTempDirectory;
    }

    public function setLaravelApp(Application $app): void
    {
        $this->laravelApp = $app;
    }

    public function setLaravelBootstrapperContext(LaravelBootstrapperContext $context): void
    {
        $this->laravelBootstrapperContext = $context;
    }

    public function setBootLaravel(bool $bootLaravel): void
    {
        $this->bootLaravel = $bootLaravel;
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function setKernel(DirectiveKernel $kernel): void
    {
        $this->kernel = $kernel;
    }

    public function setInteraction(DirectiveInteractionService $interaction): void
    {
        $this->interaction = $interaction;
    }

    public function setConfig(DirectiveTestingConfigInterface $config): void
    {
        $this->config = $config;
    }

    public function setDatabaseConnection(PDO $connection): void
    {
        $this->databaseConnection = $connection;
    }

    public function setDatabaseConnectionRecord(DatabaseConnectionRecord $record): void
    {
        $this->databaseConnectionRecord = $record;
    }

    public function setIntegratedMode(bool $integratedMode): void
    {
        $this->integratedMode = $integratedMode;
    }

    public function setInitialized(bool $initialized): void
    {
        $this->initialized = $initialized;
    }

    // ========== Adders ==========

    public function addExecutedDirective(string $directiveClass): void
    {
        $this->executedDirectives->add($directiveClass);
    }

    public function addExecutionResult(string $directiveClass, mixed $result, ?DateTimeVO $executedAt = null): void
    {
        $this->executionResults->add(new ExecutionResultRecord(
            directive_class: $directiveClass,
            result: $result,
            executed_at: $executedAt ?? new DateTimeVO,
        ));
    }

    public function addCreatedPath(string $path, PathType $type, ?DateTimeVO $createdAt = null): void
    {
        $this->createdPaths->add(new CreatedPathRecord(
            path: $path,
            type: $type,
            created_at: $createdAt ?? new DateTimeVO,
        ));
    }

    public function addStepResult(TestingStep $stepName, StepResultStatus $status, string $message, ?DateTimeVO $executedAt = null): void
    {
        $this->stepResults->add(new StepResultRecord(
            step_name: $stepName,
            status: $status,
            message: $message,
            executed_at: $executedAt ?? new DateTimeVO,
        ));
    }

    // ========== Question Methods ==========

    public function hasTempDir(): bool
    {
        return $this->tempDir !== null && is_dir($this->tempDir);
    }

    public function hasLaravelApp(): bool
    {
        return $this->laravelApp !== null;
    }

    public function hasLaravelBootstrapperContext(): bool
    {
        return $this->laravelBootstrapperContext !== null;
    }

    public function hasContainer(): bool
    {
        return $this->container !== null;
    }

    public function hasKernel(): bool
    {
        return $this->kernel !== null;
    }

    public function hasInteraction(): bool
    {
        return $this->interaction !== null;
    }

    public function hasDatabaseConnection(): bool
    {
        return $this->databaseConnection !== null;
    }

    public function hasBeenExecuted(string $directiveClass): bool
    {
        return $this->executedDirectives->contains($directiveClass);
    }

    public function hasCreatedPaths(): bool
    {
        return $this->createdPaths->isNotEmpty();
    }

    public function hasStepResult(TestingStep $stepName): bool
    {
        return $this->stepResults->getByStepName($stepName) !== null;
    }

    // ========== Counters ==========

    public function getCreatedPathsCount(): int
    {
        return $this->createdPaths->count();
    }

    public function getExecutedDirectivesCount(): int
    {
        return $this->executedDirectives->count();
    }

    public function getStepsExecutedCount(): int
    {
        return $this->stepResults->count();
    }

    // ========== Reset ==========

    public function reset(): void
    {
        $this->tempDir = null;
        $this->originalCwd = null;
        $this->inTempDirectory = false;
        $this->laravelApp = null;
        $this->laravelBootstrapperContext = null;
        $this->container = null;
        $this->kernel = null;
        $this->interaction = null;
        $this->registry->clear();
        $this->closureRegistry->clear();
        $this->executedDirectives = new StringTypedCollection;
        $this->executionResults = new ExecutionResultCollection;
        $this->createdPaths = new CreatedPathCollection;
        $this->stepResults = new StepResultCollection;
        $this->databaseConnection = null;
        $this->databaseConnectionRecord = null;
        $this->integratedMode = false;
        $this->initialized = false;
    }

    public function fullReset(): void
    {
        $this->reset();
        $this->registry = new TestDirectiveRegistry;
        $this->closureRegistry = new ClosureDirectiveRegistry;
    }
}
