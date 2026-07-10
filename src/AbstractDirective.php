<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Container\Container;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveCallRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use Throwable;

/**
 * Abstract base class for all directives.
 *
 * Provides common functionality for directive execution including argument
 * parsing, flag handling, output methods, and internal call execution.
 *
 * Directives are self-contained CLI commands that define a signature,
 * aliases, and execution logic.
 */
abstract class AbstractDirective implements DirectiveInterface
{
    /**
     * The internal calls made by this directive.
     *
     * @var array<int, DirectiveCallRecord>
     */
    private array $calls = [];

    /**
     * The execution stack for detecting circular dependencies.
     *
     * @var array<int, string>
     */
    private static array $executionStack = [];

    private Console $console;

    private ParsedSignatureRecord $parsed;

    private SignatureStructureVO $structure;

    private DirectiveParserService $parser;

    private DirectiveKernel $kernel;

    private MapCollection $context;

    /**
     * @param  DirectiveKernel  $kernel  The directive kernel
     * @param  string  $query  The query string to execute
     */
    public function __construct(
        DirectiveKernel $kernel,
        protected readonly string $query = '',
    ) {
        $this->kernel = $kernel;
        $this->context = $kernel->getContext();
        $this->console = $this->kernel->getContainer()->make(Console::class);
        $this->parser = $this->kernel->getContainer()->make(DirectiveParserService::class);
        $this->parsed = $this->parser->parse($this->getSignature(), $query);
        $this->structure = new SignatureStructureVO($this->getSignature());
    }

    /**
     * {@inheritdoc}
     */
    final public function getContainer(): ?Container
    {
        return $this->kernel->getContainer();
    }

    /**
     * {@inheritdoc}
     */
    final public function getKernel(): ?DirectiveKernel
    {
        return $this->kernel;
    }

    /**
     * {@inheritdoc}
     */
    final public function getConsole(): Console
    {
        return $this->console;
    }

    /**
     * {@inheritdoc}
     */
    final public function getParsed(): ParsedSignatureRecord
    {
        return $this->parsed;
    }

    /**
     * {@inheritdoc}
     */
    final public function getStructure(): SignatureStructureVO
    {
        return $this->structure;
    }

    /**
     * {@inheritdoc}
     */
    final public function argument(string $key): mixed
    {
        return $this->parsed->required->get($key) ?? $this->parsed->default->get($key);
    }

    /**
     * {@inheritdoc}
     */
    final public function hasArgument(string $key): bool
    {
        return $this->parsed->required->has($key) || $this->parsed->default->has($key);
    }

    /**
     * {@inheritdoc}
     */
    final public function flag(string $key): bool
    {
        return $this->parsed->flags->get($key);
    }

    /**
     * {@inheritdoc}
     */
    final public function hasFlag(string $key): bool
    {
        return $this->parsed->flags->has($key);
    }

    /**
     * {@inheritdoc}
     */
    final public function isFlagActive(string $key): bool
    {
        return $this->parsed->flags->isActive($key);
    }

    /**
     * {@inheritdoc}
     */
    final public function getVariadicArguments(): StringTypedCollection
    {
        $values = new StringTypedCollection;

        foreach ($this->parsed->variadic->getAllValues() as $value) {
            $values->add($value);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    final public function hasVariadicArguments(): bool
    {
        return $this->parsed->variadic->countAllValues() > 0;
    }

    /**
     * {@inheritdoc}
     */
    final public function getRequiredArguments(): array
    {
        return $this->parsed->required->toAssociativeArray();
    }

    /**
     * {@inheritdoc}
     */
    final public function getDefaultArguments(): array
    {
        return $this->parsed->default->toAssociativeArray();
    }

    /**
     * {@inheritdoc}
     */
    final public function getFlags(): array
    {
        return $this->parsed->flags->toAssociativeArray();
    }

    /**
     * {@inheritdoc}
     */
    final public function getActiveFlags(): array
    {
        return $this->parsed->flags->getActiveNames();
    }

    /**
     * {@inheritdoc}
     */
    final public function hasRequireds(): bool
    {
        return $this->parsed->required->isNotEmpty();
    }

    /**
     * {@inheritdoc}
     */
    final public function hasDefaults(): bool
    {
        return $this->parsed->default->isNotEmpty();
    }

    /**
     * {@inheritdoc}
     */
    final public function hasFlags(): bool
    {
        return $this->parsed->flags->isNotEmpty();
    }

    /**
     * {@inheritdoc}
     */
    final public function line(string $message): void
    {
        $this->console->line($message);
    }

    /**
     * {@inheritdoc}
     */
    final public function info(string $message): void
    {
        $this->console->info($message);
    }

    /**
     * {@inheritdoc}
     */
    final public function error(string $message): void
    {
        $this->console->error($message);
    }

    /**
     * {@inheritdoc}
     */
    final public function newLine(): void
    {
        $this->console->newLine();
    }

    /**
     * {@inheritdoc}
     */
    final public function separator(string $character = '-', int $length = 80): void
    {
        $this->console->line(str_repeat($character, $length));
    }

    /**
     * {@inheritdoc}
     */
    final public function ask(string $question): string
    {
        return $this->console->ask($question);
    }

    /**
     * {@inheritdoc}
     */
    final public function confirm(string $question): bool
    {
        return $this->console->confirm($question);
    }

    /**
     * {@inheritdoc}
     */
    final public function table(ListCollection|array $headers, ListCollection|array $rows): void
    {
        $this->console->table($headers, $rows);
    }

    /**
     * Queues an internal call to another directive.
     *
     * @param  string  $query  The query to execute
     */
    final protected function call(string $query): void
    {
        $this->calls[] = new DirectiveCallRecord($query);
    }

    /**
     * {@inheritdoc}
     */
    final public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getSignature(): string;

    /**
     * Executes the main directive logic.
     *
     * @return ExitCode The exit code
     */
    abstract protected function execute(): ExitCode;

    /**
     * Hook called before the main execution.
     */
    protected function beforeExecute(): void {}

    /**
     * Hook called after the main execution.
     *
     * @param  ExitCode  $exitCode  The exit code
     */
    protected function afterExecute(ExitCode $exitCode): void {}

    // ==================== CONTEXT METHODS ====================

    /**
     * Get a value from the shared context.
     */
    final protected function contextGet(string $key, mixed $default = null): mixed
    {
        return $this->context->get($key) ?? $default;
    }

    /**
     * Set a value in the shared context.
     *
     * Note: MapCollection is immutable, so we replace the kernel's context.
     */
    final protected function contextSet(string $key, mixed $value): void
    {
        $this->context = $this->context->put($key, $value);
        $this->kernel->setContext($this->context);
    }

    /**
     * Check if a key exists in the shared context.
     */
    final protected function contextHas(string $key): bool
    {
        return $this->context->hasKey($key);
    }

    /**
     * Get the entire context.
     */
    final protected function contextAll(): MapCollection
    {
        return $this->context;
    }

    /**
     * Merge multiple values into the context.
     *
     * @param  array<string, mixed>  $data
     */
    final protected function contextMerge(array $data): void
    {
        $this->context = $this->context->mergeArray($data);
        $this->kernel->setContext($this->context);
    }

    /**
     * Remove a key from the context.
     */
    final protected function contextRemove(string $key): void
    {
        $this->context = $this->context->remove($key);
        $this->kernel->setContext($this->context);
    }

    /**
     * Clear the entire context.
     */
    final protected function contextClear(): void
    {
        $this->context = new MapCollection;
        $this->kernel->setContext($this->context);
    }

    /**
     * Increment a numeric value in the context.
     */
    final protected function contextIncrement(string $key, int $step = 1): int
    {
        $current = (int) $this->contextGet($key, 0);
        $new = $current + $step;
        $this->contextSet($key, $new);

        return $new;
    }

    /**
     * Decrement a numeric value in the context.
     */
    final protected function contextDecrement(string $key, int $step = 1): int
    {
        $current = (int) $this->contextGet($key, 0);
        $new = $current - $step;
        $this->contextSet($key, $new);

        return $new;
    }

    // ==================== EXECUTION METHODS ====================

    /**
     * {@inheritdoc}
     */
    final public function run(): ExitCode
    {
        try {
            $this->beforeExecute();
        } catch (Throwable $e) {
            $this->error('Error in before hook: '.$e->getMessage());

            // ✅ Ajouter un problème dans le kernel
            $this->kernel->addProblem(
                'directive_before_hook',
                'Failed to execute before hook for directive: '.static::class,
                $e->getMessage(),
                ['class' => static::class, 'query' => $this->query]
            );

            return ExitCode::RUNTIME_ERROR;
        }

        try {
            $exitCode = $this->execute();

            $callExitCode = $this->executeCalls();

            if ($callExitCode !== ExitCode::SUCCESS) {
                $this->afterExecute($callExitCode);

                return $callExitCode;
            }

            $this->afterExecute($exitCode);

            return $exitCode;
        } catch (Throwable $e) {
            $this->afterExecute(ExitCode::RUNTIME_ERROR);
            $this->error('Error in execute hook: '.$e->getMessage());

            // ✅ Ajouter un problème dans le kernel
            $this->kernel->addProblem(
                'directive_execute_hook',
                'Failed to execute directive: '.static::class,
                $e->getMessage(),
                ['class' => static::class, 'query' => $this->query]
            );

            return ExitCode::RUNTIME_ERROR;
        }
    }

    /**
     * Executes all queued internal calls.
     *
     * @return ExitCode The exit code
     */
    private function executeCalls(): ExitCode
    {
        foreach ($this->calls as $call) {
            $result = $this->executeCall($call->query);

            if ($result !== ExitCode::SUCCESS) {
                return $result;
            }
        }

        return ExitCode::SUCCESS;
    }

    /**
     * Executes a single internal call.
     *
     * @param  string  $query  The query to execute
     * @return ExitCode The exit code
     */
    private function executeCall(string $query): ExitCode
    {
        $commandName = $this->extractCommandName($query);

        $directive = $this->findDirective($commandName);

        if ($directive === null) {
            $this->console->error("Directive not found: {$commandName}");

            // ✅ Ajouter un problème dans le kernel
            $this->kernel->addProblem(
                'call_directive_not_found',
                'Internal call directive not found: '.$commandName,
                'No directive matching the command name was found for internal call',
                ['command' => $commandName, 'query' => $query]
            );

            return ExitCode::NOT_FOUND;
        }

        if ($this->isCircularCall($directive, $query)) {
            $this->console->alertWarning("Circular call detected: {$query}");

            // ✅ Ajouter un problème dans le kernel
            $this->kernel->addProblem(
                'circular_call_detected',
                'Circular call detected for directive: '.$directive->class,
                'Circular dependency detected in directive calls',
                ['class' => $directive->class, 'query' => $query]
            );

            return ExitCode::CONFLICT;
        }

        return $this->executeDirectiveInstance($directive, $query);
    }

    /**
     * Extracts the command name from a query string.
     *
     * @param  string  $query  The query string
     * @return string The command name
     */
    private function extractCommandName(string $query): string
    {
        $parts = explode(' ', $query);

        return $parts[0];
    }

    /**
     * Finds a directive by command name.
     *
     * @param  string  $commandName  The command name to find
     * @return DirectiveMetadataRecord|null The directive metadata, or null if not found
     */
    private function findDirective(string $commandName): ?DirectiveMetadataRecord
    {
        $directives = $this->kernel->discover();

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

    /**
     * Checks if a directive matches a command name.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive metadata
     * @param  string  $commandName  The command name to check
     * @return bool True if matches, false otherwise
     */
    private function matchesCommandName(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        $signatureParts = explode(' ', $directive->signature);
        $directiveName = $signatureParts[0];

        return $directiveName === $commandName;
    }

    /**
     * Checks if a directive matches a command alias.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive metadata
     * @param  string  $commandName  The command name to check
     * @return bool True if matches, false otherwise
     */
    private function matchesAlias(DirectiveMetadataRecord $directive, string $commandName): bool
    {
        foreach ($directive->aliases as $alias) {
            if ($alias === $commandName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a call would create a circular dependency.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive to execute
     * @param  string  $query  The query string
     * @return bool True if circular, false otherwise
     */
    private function isCircularCall(DirectiveMetadataRecord $directive, string $query): bool
    {
        $stackKey = $directive->class.'|'.$query;

        return in_array($stackKey, self::$executionStack, true);
    }

    /**
     * Executes a directive instance.
     *
     * @param  DirectiveMetadataRecord  $directive  The directive metadata
     * @param  string  $query  The query string
     * @return ExitCode The exit code
     */
    private function executeDirectiveInstance(DirectiveMetadataRecord $directive, string $query): ExitCode
    {
        $stackKey = $directive->class.'|'.$query;
        self::$executionStack[] = $stackKey;

        try {
            $instance = new $directive->class($this->kernel, $query);
            $exitCode = $instance->run();

            array_pop(self::$executionStack);

            return $exitCode;
        } catch (Throwable $e) {
            array_pop(self::$executionStack);
            $this->console->error('Error executing call: '.$e->getMessage());

            // ✅ Ajouter un problème dans le kernel
            $this->kernel->addProblem(
                'execute_call_instance',
                'Failed to execute internal call for directive: '.$directive->class,
                $e->getMessage(),
                ['class' => $directive->class, 'query' => $query]
            );

            return ExitCode::RUNTIME_ERROR;
        }
    }
}
