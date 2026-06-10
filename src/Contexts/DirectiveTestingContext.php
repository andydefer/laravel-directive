<?php

// src/Contexts/DirectiveTestingContext.php

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
    private ?string $temp_dir = null;
    private ?string $original_cwd = null;
    private bool $in_temp_directory = false;

    // Laravel state
    private ?Application $laravel_app = null;
    private bool $boot_laravel = false;

    // Service container state
    private ?Container $container = null;
    private ?DirectiveKernel $kernel = null;
    private ?DirectiveInteractionService $interaction = null;

    // Registry state
    private TestDirectiveRegistry $registry;
    private ClosureDirectiveRegistry $closure_registry;

    // Configuration
    private DirectiveTestingConfigInterface $config;

    // Execution tracking
    private StringTypedCollection $executed_directives;
    private ExecutionResultCollection $execution_results;
    private CreatedPathCollection $created_paths;
    private StepResultCollection $step_results;

    // Sub-contexts
    private LaravelContext $laravel_context;
    private FileSystemContext $file_system_context;

    // Database state
    private ?PDO $database_connection = null;
    private ?DatabaseConnectionRecord $database_connection_record = null;

    // Mode flags
    private bool $integrated_mode = false;
    private bool $initialized = false;

    public function __construct(bool $boot_laravel = false)
    {
        $this->boot_laravel = $boot_laravel;
        $this->registry = new TestDirectiveRegistry;
        $this->closure_registry = new ClosureDirectiveRegistry;
        $this->executed_directives = new StringTypedCollection;
        $this->execution_results = new ExecutionResultCollection;
        $this->created_paths = new CreatedPathCollection;
        $this->step_results = new StepResultCollection;
        $this->laravel_context = new LaravelContext;
        $this->file_system_context = new FileSystemContext;
    }

    // ========== Getters ==========

    public function getTempDir(): ?string
    {
        return $this->temp_dir;
    }

    public function getOriginalCwd(): ?string
    {
        return $this->original_cwd;
    }

    public function isInTempDirectory(): bool
    {
        return $this->in_temp_directory;
    }

    public function getLaravelApp(): ?Application
    {
        return $this->laravel_app;
    }

    public function shouldBootLaravel(): bool
    {
        return $this->boot_laravel;
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
        return $this->closure_registry;
    }

    public function getConfig(): DirectiveTestingConfigInterface
    {
        return $this->config;
    }

    public function getExecutedDirectives(): StringTypedCollection
    {
        return $this->executed_directives;
    }

    public function getExecutionResults(): ExecutionResultCollection
    {
        return $this->execution_results;
    }

    public function getExecutionResult(string $directive_class): ?ExecutionResultRecord
    {
        return $this->execution_results->getByDirectiveClass($directive_class);
    }

    public function getCreatedPaths(): CreatedPathCollection
    {
        return $this->created_paths;
    }

    public function getStepResults(): StepResultCollection
    {
        return $this->step_results;
    }

    public function getStepResult(TestingStep $step_name): ?StepResultRecord
    {
        return $this->step_results->getByStepName($step_name);
    }

    public function getLaravelContext(): LaravelContext
    {
        return $this->laravel_context;
    }

    public function getFileSystemContext(): FileSystemContext
    {
        return $this->file_system_context;
    }

    public function getDatabaseConnection(): ?PDO
    {
        return $this->database_connection;
    }

    public function getDatabaseConnectionRecord(): ?DatabaseConnectionRecord
    {
        return $this->database_connection_record;
    }

    public function isIntegratedMode(): bool
    {
        return $this->integrated_mode;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    // ========== Setters ==========

    public function setTempDir(?string $temp_dir): void
    {
        $this->temp_dir = $temp_dir;
    }

    public function setOriginalCwd(string $original_cwd): void
    {
        $this->original_cwd = $original_cwd;
    }

    public function setInTempDirectory(bool $in_temp_directory): void
    {
        $this->in_temp_directory = $in_temp_directory;
    }

    public function setLaravelApp(Application $app): void
    {
        $this->laravel_app = $app;
    }

    public function setBootLaravel(bool $boot_laravel): void
    {
        $this->boot_laravel = $boot_laravel;
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

    public function setRegistry(TestDirectiveRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function setConfig(DirectiveTestingConfigInterface $config): void
    {
        $this->config = $config;
    }

    public function setDatabaseConnection(PDO $connection): void
    {
        $this->database_connection = $connection;
    }

    public function setDatabaseConnectionRecord(DatabaseConnectionRecord $record): void
    {
        $this->database_connection_record = $record;
    }

    public function setIntegratedMode(bool $integrated_mode): void
    {
        $this->integrated_mode = $integrated_mode;
    }

    public function setInitialized(bool $initialized): void
    {
        $this->initialized = $initialized;
    }

    // ========== Adders ==========

    public function addExecutedDirective(string $directive_class): void
    {
        $this->executed_directives->add($directive_class);
    }

    public function addExecutionResult(string $directive_class, mixed $result, ?DateTimeVO $executed_at = null): void
    {
        $record = new ExecutionResultRecord(
            directive_class: $directive_class,
            result: $result,
            executed_at: $executed_at ?? new DateTimeVO(null),
        );
        $this->execution_results->add($record);
    }

    public function addCreatedPath(string $path, PathType $type, ?DateTimeVO $created_at = null): void
    {
        $record = new CreatedPathRecord(
            path: $path,
            type: $type,
            created_at: $created_at ?? new DateTimeVO(null),
        );
        $this->created_paths->add($record);
    }

    public function addStepResult(TestingStep $step_name, StepResultStatus $status, string $message, ?DateTimeVO $executed_at = null): void
    {
        $record = new StepResultRecord(
            step_name: $step_name,
            status: $status,
            message: $message,
            executed_at: $executed_at ?? new DateTimeVO(null),
        );
        $this->step_results->add($record);
    }

    // ========== Question Methods ==========

    public function hasTempDir(): bool
    {
        return $this->temp_dir !== null && is_dir($this->temp_dir);
    }

    public function hasLaravelApp(): bool
    {
        return $this->laravel_app !== null;
    }

    public function hasLaravelStructure(): bool
    {
        if ($this->temp_dir === null) {
            return false;
        }

        return is_dir($this->temp_dir . '/bootstrap')
            && is_dir($this->temp_dir . '/config')
            && is_dir($this->temp_dir . '/storage')
            && file_exists($this->temp_dir . '/bootstrap/app.php');
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
        return $this->database_connection !== null;
    }

    public function hasBeenExecuted(string $directive_class): bool
    {
        return $this->executed_directives->contains($directive_class);
    }

    public function hasCreatedPaths(): bool
    {
        return $this->created_paths->isNotEmpty();
    }

    public function hasStepResult(TestingStep $step_name): bool
    {
        return $this->step_results->getByStepName($step_name) !== null;
    }

    // ========== Counters ==========

    public function getCreatedPathsCount(): int
    {
        return $this->created_paths->count();
    }

    public function getExecutedDirectivesCount(): int
    {
        return $this->executed_directives->count();
    }

    public function getStepsExecutedCount(): int
    {
        return $this->step_results->count();
    }

    // ========== Reset ==========

    public function reset(): void
    {
        $this->temp_dir = null;
        $this->original_cwd = null;
        $this->in_temp_directory = false;
        $this->laravel_app = null;
        $this->container = null;
        $this->kernel = null;
        $this->interaction = null;
        $this->registry->clear();
        $this->closure_registry->clear();
        $this->executed_directives = new StringTypedCollection;
        $this->execution_results = new ExecutionResultCollection;
        $this->created_paths = new CreatedPathCollection;
        $this->step_results = new StepResultCollection;
        $this->laravel_context->reset();
        $this->file_system_context->reset();
        $this->database_connection = null;
        $this->database_connection_record = null;
        $this->integrated_mode = false;
        $this->initialized = false;
    }

    public function fullReset(): void
    {
        $this->reset();
        $this->registry = new TestDirectiveRegistry;
        $this->closure_registry = new ClosureDirectiveRegistry;
    }
}
