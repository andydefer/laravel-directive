<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\AlgoKIT\Algorithms\BKTree;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\ExecutionStatsRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\ExecutionStatsLogger;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\StorageKit\Storage\MemoryStorage;
use ReflectionClass;
use Throwable;

final class DirectiveKernel extends DirectiveDiscoveryService
{
    private MapCollection $context;

    private ExecutionStatsLogger $logger;

    private ?ExecutionStatsRecord $lastStats = null;

    private ?string $lastError = null;

    private float $startTime;

    /**
     * Whether verbose mode is enabled.
     * When enabled, problems are displayed as logs after execution.
     */
    private bool $verbose = false;

    private int $startMemory;

    private BKTree $bkTree;

    private bool $bkTreeInitialized = false;

    private array $directivesCache = [];

    private function __construct(
        private readonly Container $container,
    ) {
        parent::__construct($container);
        $this->context = new MapCollection;

        try {
            $this->logger = $this->container->make(ExecutionStatsLogger::class);
        } catch (Throwable $e) {
            $this->addProblem(
                'logger_resolution',
                'Failed to resolve ExecutionStatsLogger from container',
                $e->getMessage(),
                []
            );
            throw $e;
        }

        try {
            $this->bkTree = new BKTree(new MemoryStorage, 'directive_suggestions');
        } catch (Throwable $e) {
            $this->addProblem(
                'bk_tree_initialization',
                'Failed to initialize BKTree for directive suggestions',
                $e->getMessage(),
                []
            );
            throw $e;
        }
    }

    public static function init(Container $container): self
    {
        return new self($container);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getContext(): MapCollection
    {
        return $this->context;
    }

    public function setContext(MapCollection $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function resetContext(): self
    {
        $this->context = new MapCollection;

        return $this;
    }

    public function getLastStats(): ?ExecutionStatsRecord
    {
        return $this->lastStats;
    }

    public function getLogger(): ExecutionStatsLogger
    {
        return $this->logger;
    }

    public function setLogBasePath(string $path): self
    {
        try {
            $this->logger->setBasePath($path);
        } catch (Throwable $e) {
            $this->addProblem(
                'set_log_base_path',
                'Failed to set log base path: '.$path,
                $e->getMessage(),
                ['path' => $path]
            );
        }

        return $this;
    }

    public function run(array $argv): ExitCode
    {
        try {
            if ($this->isMissingCommand($argv)) {
                return $this->executeHelpDirective();
            }

            [$commandName, $query] = $this->parseArguments($argv);

            $exitCode = $this->executeDirective($commandName, $query);

            if ($this->verbose) {
                $this->displayProblems();
            }

            return $exitCode;
        } catch (Throwable $e) {
            $this->addProblem(
                'run_execution',
                'Failed to execute command',
                $e->getMessage(),
                ['argv' => $argv]
            );

            if ($this->verbose) {
                $this->displayProblems();
            }

            return ExitCode::RUNTIME_ERROR;
        }
    }

    public function runDirective(string $fqcn, array $argv = []): ExitCode
    {
        try {
            $this->addDirective($fqcn);

            $reflection = new ReflectionClass($fqcn);
            $instance = $reflection->newInstanceWithoutConstructor();
            $signature = $instance->getSignature();
            $parts = explode(' ', $signature);
            $commandName = $parts[0];

            $fullArgv = array_merge(['directive', $commandName], $argv);

            $exitCode = $this->run($fullArgv);

            if ($this->verbose) {
                $this->displayProblems();
            }

            return $exitCode;
        } catch (Throwable $e) {
            $this->addProblem(
                'run_directive',
                'Failed to run directive: '.$fqcn,
                $e->getMessage(),
                ['fqcn' => $fqcn, 'argv' => $argv]
            );

            if ($this->verbose) {
                $this->displayProblems();
            }

            return ExitCode::RUNTIME_ERROR;
        }
    }

    public function runSignature(string $query): ExitCode
    {
        try {
            $argv = array_merge(['directive'], explode(' ', $query));

            $exitCode = $this->run($argv);

            if ($this->verbose) {
                $this->displayProblems();
            }

            return $exitCode;
        } catch (Throwable $e) {
            $this->addProblem(
                'run_signature',
                'Failed to run signature: '.$query,
                $e->getMessage(),
                ['query' => $query]
            );

            if ($this->verbose) {
                $this->displayProblems();
            }

            return ExitCode::RUNTIME_ERROR;
        }
    }

    private function isMissingCommand(array $argv): bool
    {
        return count($argv) < 2;
    }

    private function executeHelpDirective(): ExitCode
    {
        try {
            return $this->executeDirective('help', 'help');
        } catch (Throwable $e) {
            $this->addProblem(
                'execute_help',
                'Failed to execute help directive',
                $e->getMessage(),
                []
            );

            return ExitCode::RUNTIME_ERROR;
        }
    }

    private function parseArguments(array $argv): array
    {
        $query = implode(' ', array_slice($argv, 1));
        $parts = explode(' ', $query);
        $commandName = $parts[0];

        return [$commandName, $query];
    }

    private function executeDirective(string $commandName, string $query): ExitCode
    {
        try {
            $directives = $this->getDirectives();

            $directive = $this->findDirective($directives, $commandName);

            if ($directive === null) {
                /** @var Console $console */
                $console = $this->container->make(Console::class);
                $console->error("Directive not found: {$commandName}");

                $suggestions = $this->getSuggestions($commandName);
                if (! empty($suggestions)) {
                    $console->line('');
                    $console->info('💡 Did you mean:');
                    foreach ($suggestions as $suggestion) {
                        $console->line("  • {$suggestion}");
                    }
                }

                // ✅ Ajouter un problème
                $this->addProblem(
                    'directive_not_found',
                    'Directive not found: '.$commandName,
                    'No directive matching the command name was found',
                    ['command' => $commandName, 'query' => $query]
                );

                return ExitCode::NOT_FOUND;
            }

            $this->startTime = microtime(true);
            $this->startMemory = memory_get_usage();

            $exitCode = $this->instantiateAndRun($directive, $query);

            $this->logExecution($directive, $commandName, $exitCode);

            return $exitCode;
        } catch (Throwable $e) {
            $this->addProblem(
                'execute_directive',
                'Failed to execute directive: '.$commandName,
                $e->getMessage(),
                ['command' => $commandName, 'query' => $query]
            );

            return ExitCode::RUNTIME_ERROR;
        }
    }

    private function getDirectives(): DirectiveMetadataCollection
    {
        try {
            if (empty($this->directivesCache)) {
                $this->directivesCache = $this->discover()->toArray();
                $this->initializeBKTree();
            }

            $collection = new DirectiveMetadataCollection;
            foreach ($this->directivesCache as $directive) {
                $collection->add($directive);
            }

            return $collection;
        } catch (Throwable $e) {
            $this->addProblem(
                'get_directives',
                'Failed to get directives from cache',
                $e->getMessage(),
                []
            );

            return new DirectiveMetadataCollection;
        }
    }

    private function getSuggestions(string $commandName, int $limit = 5): array
    {
        try {
            $results = $this->bkTree->search($commandName, 2, $limit);

            return array_map(
                fn ($result) => $result->word,
                $results->toArray()
            );
        } catch (Throwable $e) {
            $this->addProblem(
                'get_suggestions',
                'Failed to get suggestions for command: '.$commandName,
                $e->getMessage(),
                ['command' => $commandName, 'limit' => $limit]
            );

            return [];
        }
    }

    private function initializeBKTree(): void
    {
        if ($this->bkTreeInitialized) {
            return;
        }

        try {
            foreach ($this->directivesCache as $directive) {
                $this->indexDirective($directive);
            }
            $this->bkTreeInitialized = true;
        } catch (Throwable $e) {
            $this->addProblem(
                'initialize_bk_tree',
                'Failed to initialize BKTree with directives',
                $e->getMessage(),
                ['directives_count' => count($this->directivesCache)]
            );
        }
    }

    private function indexDirective(DirectiveMetadataRecord $directive): void
    {
        try {
            $parts = explode(' ', $directive->signature);
            $commandName = $parts[0];
            $this->bkTree->insert($commandName);

            foreach ($directive->aliases as $alias) {
                $this->bkTree->insert($alias);
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'index_directive',
                'Failed to index directive in BKTree: '.$directive->signature,
                $e->getMessage(),
                ['signature' => $directive->signature, 'class' => $directive->class]
            );
        }
    }

    private function logExecution(DirectiveMetadataRecord $directive, string $commandName, ExitCode $exitCode): void
    {
        try {
            $duration = microtime(true) - $this->startTime;
            $memoryUsed = memory_get_usage() - $this->startMemory;
            $peakMemory = memory_get_peak_usage();

            $record = new ExecutionStatsRecord(
                command: $commandName,
                directiveClass: $directive->class,
                signature: $directive->signature,
                exitCode: $exitCode,
                duration: $duration,
                memoryUsage: $memoryUsed,
                peakMemoryUsage: $peakMemory,
                callsCount: 0,
                error: $this->lastError,
            );

            $this->lastStats = $record;
            $this->lastError = null;

            $this->logger->log($record, $this->context);
        } catch (Throwable $e) {
            $this->addProblem(
                'log_execution',
                'Failed to log execution for directive: '.$commandName,
                $e->getMessage(),
                ['command' => $commandName, 'exit_code' => $exitCode->value]
            );
        }
    }

    private function findDirective(DirectiveMetadataCollection $directives, string $commandName): ?DirectiveMetadataRecord
    {
        try {
            foreach ($directives as $directive) {
                if ($this->matchesCommandName($directive, $commandName)) {
                    return $directive;
                }

                if ($this->matchesAlias($directive, $commandName)) {
                    return $directive;
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'find_directive',
                'Failed to find directive: '.$commandName,
                $e->getMessage(),
                ['command' => $commandName]
            );
        }

        return null;
    }

    private function matchesCommandName(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        try {
            $signatureParts = explode(' ', $directive->signature);
            $directiveName = $signatureParts[0];

            return $directiveName === $commandName;
        } catch (Throwable $e) {
            $this->addProblem(
                'matches_command_name',
                'Failed to match command name: '.$commandName,
                $e->getMessage(),
                ['command' => $commandName, 'signature' => $directive->signature]
            );

            return false;
        }
    }

    private function matchesAlias(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        try {
            foreach ($directive->aliases as $alias) {
                if ($alias === $commandName) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            $this->addProblem(
                'matches_alias',
                'Failed to match alias: '.$commandName,
                $e->getMessage(),
                ['command' => $commandName]
            );
        }

        return false;
    }

    private function instantiateAndRun(DirectiveMetadataRecord $directive, string $query): ExitCode
    {
        try {
            $instance = new $directive->class($this, $query);

            return $instance->run();
        } catch (Throwable $e) {
            $this->addProblem(
                'instantiate_and_run',
                'Failed to instantiate and run directive: '.$directive->class,
                $e->getMessage(),
                ['class' => $directive->class, 'query' => $query]
            );

            return ExitCode::RUNTIME_ERROR;
        }
    }

    // ==================== VERBOSE MODE ====================

    /**
     * Enable or disable verbose mode.
     * When enabled, problems are displayed as logs after execution.
     */
    public function verbose(bool $enabled = true): self
    {
        $this->verbose = $enabled;

        return $this;
    }

    /**
     * Enable output (disable verbose mode).
     */
    public function withOutput(): self
    {
        $this->verbose = false;

        return $this;
    }

    /**
     * Disable output (enable verbose mode).
     */
    public function withoutOutput(): self
    {
        $this->verbose = true;

        return $this;
    }

    /**
     * Check if verbose mode is enabled.
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * Display all problems encountered during execution using console logs.
     */
    private function displayProblems(): void
    {
        $problems = $this->getProblems();

        if ($problems->isEmpty()) {
            return;
        }

        /** @var Console $console */
        $console = $this->container->make(Console::class);

        $console->logError('=== '.$problems->count().' Problem(s) Encountered ===');
        $console->line();

        foreach ($problems as $index => $problem) {
            if ($index > 0) {
                $console->line('---');
            }

            // Construire l'objet problème complet
            $problemData = [
                'key' => $problem->get('key'),
                'context' => $problem->get('context'),
                'message' => $problem->get('message'),
                'timestamp' => $problem->get('timestamp'),
                'context_data' => $this->normalizeContextData($problem->get('context_data')),
            ];

            // Afficher en JSON avec indentation
            $console->json($problemData);
        }

        $console->line();
        $console->logError('=== End of Problems ===');
    }

    /**
     * Normalize context data for JSON display.
     */
    private function normalizeContextData(mixed $contextData): mixed
    {
        if ($contextData === null) {
            return null;
        }

        if (is_scalar($contextData)) {
            return $contextData;
        }

        if (is_array($contextData)) {
            return array_map([$this, 'normalizeContextData'], $contextData);
        }

        if (is_object($contextData)) {
            if (method_exists($contextData, 'toArray')) {
                return $this->normalizeContextData($contextData->toArray());
            }

            // Convertir l'objet en tableau standard
            $arrayData = (array) $contextData;
            $cleanData = [];
            foreach ($arrayData as $key => $value) {
                $cleanKey = preg_replace('/^\0(?:.*)\0/', '', $key);
                $cleanKey = preg_replace('/^\0/', '', $cleanKey);
                $cleanData[$cleanKey] = $this->normalizeContextData($value);
            }

            return $cleanData;
        }

        return ['type' => gettype($contextData), 'value' => (string) $contextData];
    }
}
