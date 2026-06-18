<?php

// src/Services/DirectiveTestingService.php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\ParameterVOCollection;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Records\DirectiveResponseRecord;
use AndyDefer\Directive\ValueObjects\ParameterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpServices\Services\PrimitiveTypeConverterService;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Throwable;

final class DirectiveTestingService
{
    private PrimitiveTypeConverterService $typeConverter;

    private DirectiveInteractionService $interaction;

    private string $tempDir;

    private ?Application $application;

    private string $originalCwd;

    private array $calls = [];

    private array $registeredDirectives = [];

    public function __construct(?Application $application = null)
    {
        $this->application = $application;
        $this->typeConverter = new PrimitiveTypeConverterService;
        $this->interaction = new DirectiveInteractionService(new RenderDispatcher, new InputDispatcher);

        $this->originalCwd = getcwd();

        $this->setupTempDirectory();
    }

    public function registerDirective(string $class): self
    {
        if (! in_array($class, $this->registeredDirectives)) {
            $this->registeredDirectives[] = $class;
        }

        return $this;
    }

    public function run(string $class, array $arguments = []): DirectiveResponseRecord
    {
        try {
            $directive = $this->createDirective($class);
        } catch (InvalidArgumentException $e) {
            return new DirectiveResponseRecord(ExitCode::NOT_FOUND, $e->getMessage());
        } catch (Throwable $e) {
            return new DirectiveResponseRecord(ExitCode::FAILURE, $e->getMessage());
        }

        return $this->executeDirective($directive, $arguments);
    }

    public function destroy(): void
    {
        if ($this->originalCwd !== '' && is_dir($this->originalCwd)) {
            chdir($this->originalCwd);
        }

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function getInteraction(): DirectiveInteractionService
    {
        return $this->interaction;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function clearCalls(): void
    {
        $this->calls = [];
    }

    private function createDirective(string $class): AbstractDirective
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Directive class {$class} does not exist");
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $paramType = $param->getType();
            $paramName = $paramType?->getName();

            $args[] = match (true) {
                $paramName === DirectiveContext::class => $this->createDirectiveContext($class),
                $paramName === DirectiveInteractionService::class => $this->interaction,
                $this->application !== null && $paramType?->isBuiltin() === false && class_exists($paramName) => $this->application->make($paramName),
                default => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            };
        }

        return $reflection->newInstanceArgs($args);
    }

    private function createDirectiveContext(string $class): DirectiveContext
    {
        $blueprint = new DirectiveBlueprintRecord($class, '', '');
        $aliases = new StringTypedCollection;

        return new DirectiveContext(
            $blueprint,
            $aliases,
            $this->application,
            $this->registeredDirectives
        );
    }

    private function executeDirective(AbstractDirective $directive, array $arguments): DirectiveResponseRecord
    {
        $context = $this->createDirectiveContext(get_class($directive));

        try {
            $parsed = $this->parseArguments($directive->getSignature(), $arguments);
        } catch (InvalidArgumentException $e) {
            return new DirectiveResponseRecord(ExitCode::INVALID_ARGUMENT, $e->getMessage());
        } catch (Throwable $e) {
            return new DirectiveResponseRecord(ExitCode::FAILURE, $e->getMessage());
        }

        $context->setArguments($parsed['arguments']);
        $context->setOptions($parsed['options']);
        $context->setVariadicArguments($parsed['variadic']);

        $hydratedDirective = $this->hydrateDirective($directive, $context);

        $this->calls = [];

        ob_start();
        try {
            $exitCode = $hydratedDirective->run();
            $parentOutput = ob_get_clean();

            $this->calls = $hydratedDirective->getCalls();

            $childOutput = '';
            $calls = $this->calls;
            foreach ($calls as $call) {
                $childOutput .= $this->executeCall($call);
            }

            return new DirectiveResponseRecord($exitCode, $parentOutput.$childOutput);
        } catch (Throwable $e) {
            ob_end_clean();

            return new DirectiveResponseRecord(ExitCode::FAILURE, $e->getMessage());
        }
    }

    private function executeCall(DirectiveExecutionRecord $record): string
    {
        $class = $this->findDirectiveClass($record->signature);
        if ($class === null) {
            return '';
        }

        $reflection = new \ReflectionClass($class);
        $tempInstance = $reflection->newInstanceWithoutConstructor();

        $context = $this->createDirectiveContext($class);

        try {
            $parsed = $this->parseArguments(
                $tempInstance->getSignature(),
                $record->arguments->toArray()
            );
        } catch (Throwable $e) {
            return '';
        }

        $context->setArguments($parsed['arguments']);
        $context->setOptions($parsed['options']);
        $context->setVariadicArguments($parsed['variadic']);

        $directive = $this->hydrateDirective($tempInstance, $context);

        try {
            ob_start();
            $directive->run();
            $output = ob_get_clean();

            $childCalls = $directive->getCalls();
            foreach ($childCalls as $childCall) {
                $output .= $this->executeCall($childCall);
            }

            return $output;
        } catch (Throwable $e) {
            return '';
        }
    }

    private function findDirectiveClass(string $signature): ?string
    {
        foreach ($this->registeredDirectives as $class) {
            $reflection = new \ReflectionClass($class);
            $instance = $reflection->newInstanceWithoutConstructor();

            $fullSignature = $instance->getSignature();
            $baseSignature = explode(' ', $fullSignature)[0];
            $baseSignature = explode('{', $baseSignature)[0];
            $baseSignature = rtrim($baseSignature, '-');

            if ($baseSignature === $signature) {
                return $class;
            }

            if ($instance->getAliases()->contains($signature)) {
                return $class;
            }
        }

        return null;
    }

    private function parseArguments(string $signature, array $arguments): array
    {
        $parser = new DirectiveParserService;
        $argumentCollection = new StringTypedCollection;

        foreach ($arguments as $arg) {
            $argumentCollection->add((string) $arg);
        }

        $parsed = $parser->parse($signature, $argumentCollection);

        $argumentsVO = new ParameterVOCollection;
        foreach ($parsed->arguments as $arg) {
            $type = $this->typeConverter->detectType($arg->value);
            $value = $this->typeConverter->convert($arg->value, $type);
            $argumentsVO->add(new ParameterVO($arg->name, $value, $type));
        }

        $optionsVO = new ParameterVOCollection;
        foreach ($parsed->options as $opt) {
            $value = match ($opt->value) {
                'true' => true,
                'false' => false,
                default => $opt->value,
            };
            $type = $this->typeConverter->detectType($value);
            $optionsVO->add(new ParameterVO($opt->name, $value, $type));
        }

        return [
            'arguments' => $argumentsVO,
            'options' => $optionsVO,
            'variadic' => $parsed->variadic_arguments,
        ];
    }

    private function hydrateDirective(AbstractDirective $directive, DirectiveContext $context): AbstractDirective
    {
        $reflection = new \ReflectionClass($directive);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $directive;
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getType()?->getName();

            $args[] = match (true) {
                $paramName === DirectiveContext::class => $context,
                $paramName === DirectiveInteractionService::class => $this->interaction,
                default => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            };
        }

        return $reflection->newInstanceArgs($args);
    }

    private function setupTempDirectory(): void
    {
        $this->tempDir = sys_get_temp_dir().'/directive_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
        chdir($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
