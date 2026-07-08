<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveCallRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\ListCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use Illuminate\Foundation\Application;
use Throwable;

abstract class AbstractDirective implements DirectiveInterface
{
    private array $calls = [];

    private static array $executionStack = [];

    protected Application $app;

    protected Console $console;

    protected ParsedSignatureRecord $parsed;

    private DirectiveParserService $parser;

    public function __construct(Application $app, string $query)
    {
        $this->app = $app;
        $this->console = $app->make(Console::class);
        $this->parser = $app->make(DirectiveParserService::class);
        $this->parsed = $this->parser->parse($this->getSignature(), $query);
    }

    final public function getLaravel(): Application
    {
        return $this->app;
    }

    final public function getConsole(): Console
    {
        return $this->console;
    }

    final public function getParsed(): ParsedSignatureRecord
    {
        return $this->parsed;
    }

    final public function argument(string $key): mixed
    {
        return $this->parsed->required->get($key) ?? $this->parsed->default->get($key);
    }

    final public function hasArgument(string $key): bool
    {
        return $this->parsed->required->has($key) || $this->parsed->default->has($key);
    }

    final public function option(string $key): bool
    {
        return $this->parsed->options->get($key);
    }

    final public function hasOption(string $key): bool
    {
        return $this->parsed->options->isActive($key);
    }

    final public function getVariadicArguments(): StringTypedCollection
    {
        $allValues = new StringTypedCollection;
        foreach ($this->parsed->variadic->getAllValues() as $value) {
            $allValues->add($value);
        }

        return $allValues;
    }

    final public function hasVariadicArguments(): bool
    {
        return $this->parsed->variadic->countAllValues() > 0;
    }

    final public function line(string $message): void
    {
        $this->console->line($message);
    }

    final public function info(string $message): void
    {
        $this->console->info($message);
    }

    final public function error(string $message): void
    {
        $this->console->error($message);
    }

    final public function newLine(): void
    {
        $this->console->newLine();
    }

    final public function separator(string $character = '-', int $length = 80): void
    {
        $this->console->line(str_repeat($character, $length));
    }

    final public function ask(string $question): string
    {
        return $this->console->ask($question);
    }

    final public function confirm(string $question): bool
    {
        return $this->console->confirm($question);
    }

    final public function table(ListCollection|array $headers, ListCollection|array $rows): void
    {
        $this->console->table($headers, $rows);
    }

    final protected function call(string $query): void
    {
        $this->calls[] = new DirectiveCallRecord($query);
    }

    final public function getCalls(): array
    {
        return $this->calls;
    }

    private function executeCall(string $query): ExitCode
    {
        // Extraire le nom de la commande
        $parts = explode(' ', $query);
        $commandName = $parts[0];

        // Trouver la directive
        $discovery = $this->app->make(DirectiveDiscoveryService::class);
        $directives = $discovery->discover();
        $directive = $this->findDirective($directives, $commandName);

        if ($directive === null) {
            $this->console->error("Directive not found: {$commandName}");

            return ExitCode::NOT_FOUND;
        }

        // Vérifier la récursion
        $stackKey = $directive->class.'|'.$query;
        if (in_array($stackKey, self::$executionStack, true)) {
            $this->console->alertWarning("Circular call detected: {$query}");

            return ExitCode::CONFLICT;
        }

        // Ajouter à la pile
        self::$executionStack[] = $stackKey;

        try {
            $hydrator = $this->app->make(DirectiveHydratorService::class);
            $instance = $hydrator->hydrate($directive->class, $query);

            $exitCode = $instance->run();

            array_pop(self::$executionStack);

            return $exitCode;
        } catch (Throwable $e) {
            array_pop(self::$executionStack);
            $this->console->error('Error executing call: '.$e->getMessage());

            return ExitCode::RUNTIME_ERROR;
        }
    }

    private function findDirective(DirectiveMetadataCollection $directives, string $commandName): ?DirectiveMetadataRecord
    {
        foreach ($directives as $directive) {
            $signatureParts = explode(' ', $directive->signature);
            $directiveName = $signatureParts[0];

            if ($directiveName === $commandName) {
                return $directive;
            }

            foreach ($directive->aliases as $alias) {
                if ($alias === $commandName) {
                    return $directive;
                }
            }
        }

        return null;
    }

    private function executeCalls(): ExitCode
    {
        $calls = $this->getCalls();

        foreach ($calls as $call) {
            $callResult = $this->executeCall($call->query);
            if ($callResult !== ExitCode::SUCCESS) {
                return $callResult;
            }
        }

        return ExitCode::SUCCESS;
    }

    final public function run(): ExitCode
    {
        try {
            $this->beforeExecute();
        } catch (Throwable $e) {
            $this->error('Error in before hook: '.$e->getMessage());

            return ExitCode::RUNTIME_ERROR;
        }

        try {
            $exitCode = $this->execute();

            // Exécuter les calls APRÈS execute() et AVANT afterExecute()
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

            return ExitCode::RUNTIME_ERROR;
        }
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    abstract public function getSignature(): string;

    abstract protected function execute(): ExitCode;

    protected function beforeExecute(): void {}

    protected function afterExecute(ExitCode $exitCode): void {}
}
