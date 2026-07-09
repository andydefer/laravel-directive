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

final class DirectiveKernel extends DirectiveDiscoveryService
{
    private MapCollection $context;

    private ExecutionStatsLogger $logger;

    private ?ExecutionStatsRecord $lastStats = null;

    private ?string $lastError = null;

    private float $startTime;

    private int $startMemory;

    private BKTree $bkTree;

    private bool $bkTreeInitialized = false;

    private array $directivesCache = [];

    private function __construct(
        private readonly Container $container,
    ) {
        parent::__construct($container);
        $this->context = new MapCollection;
        $this->logger = $this->container->make(ExecutionStatsLogger::class);
        $this->bkTree = new BKTree(new MemoryStorage, 'directive_suggestions');
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
        $this->logger->setBasePath($path);

        return $this;
    }

    public function run(array $argv): ExitCode
    {
        if ($this->isMissingCommand($argv)) {
            return $this->executeHelpDirective();
        }

        [$commandName, $query] = $this->parseArguments($argv);

        return $this->executeDirective($commandName, $query);
    }

    public function runDirective(string $fqcn, array $argv = []): ExitCode
    {
        $this->addDirective($fqcn);

        $reflection = new ReflectionClass($fqcn);
        $instance = $reflection->newInstanceWithoutConstructor();
        $signature = $instance->getSignature();
        $parts = explode(' ', $signature);
        $commandName = $parts[0];

        $fullArgv = array_merge(['directive', $commandName], $argv);

        return $this->run($fullArgv);
    }

    public function runSignature(string $query): ExitCode
    {
        $argv = array_merge(['directive'], explode(' ', $query));

        return $this->run($argv);
    }

    private function isMissingCommand(array $argv): bool
    {
        return count($argv) < 2;
    }

    private function executeHelpDirective(): ExitCode
    {
        return $this->executeDirective('help', 'help');
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

            return ExitCode::NOT_FOUND;
        }

        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        $exitCode = $this->instantiateAndRun($directive, $query);

        $this->logExecution($directive, $commandName, $exitCode);

        return $exitCode;
    }

    private function getDirectives(): DirectiveMetadataCollection
    {
        if (empty($this->directivesCache)) {
            $this->directivesCache = $this->discover()->toArray();
            $this->initializeBKTree();
        }

        $collection = new DirectiveMetadataCollection;
        foreach ($this->directivesCache as $directive) {
            $collection->add($directive);
        }

        return $collection;
    }

    private function getSuggestions(string $commandName, int $limit = 5): array
    {
        try {
            $results = $this->bkTree->search($commandName, 2, $limit);

            return array_map(
                fn ($result) => $result->word,
                $results->toArray()
            );
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            // Silently ignore initialization errors
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
        } catch (\Throwable $e) {
            // Silently ignore indexing errors
        }
    }

    private function logExecution(DirectiveMetadataRecord $directive, string $commandName, ExitCode $exitCode): void
    {
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
    }

    private function findDirective(DirectiveMetadataCollection $directives, string $commandName): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            if ($this->matchesCommandName($directive, $commandName)) {
                return $directive;
            }

            if ($this->matchesAlias($directive, $commandName)) {
                return $directive;
            }
        }

        return null;
    }

    private function matchesCommandName(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        $signatureParts = explode(' ', $directive->signature);
        $directiveName = $signatureParts[0];

        return $directiveName === $commandName;
    }

    private function matchesAlias(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        foreach ($directive->aliases as $alias) {
            if ($alias === $commandName) {
                return true;
            }
        }

        return false;
    }

    private function instantiateAndRun(DirectiveMetadataRecord $directive, string $query): ExitCode
    {
        $instance = new $directive->class($this, $query);

        return $instance->run();
    }
}
