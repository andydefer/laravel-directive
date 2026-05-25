<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Records\Collections\TypedCollection;

final class TestDirectiveRegistry implements DirectiveLoaderInterface
{
    /** @var array<string, AbstractDirective> */
    private array $directives = [];

    private ?DirectiveInteractionService $interaction = null;

    public function setInteraction(DirectiveInteractionService $interaction): void
    {
        $this->interaction = $interaction;
    }

    public function register(string $signature, AbstractDirective $directive): void
    {
        $this->directives[$signature] = $directive;

        foreach ($directive->getAliases() as $alias) {
            $this->directives[$alias] = $directive;
        }
    }

    public function registerByClass(string $className, array $constructorArgs = []): AbstractDirective
    {
        $reflection = new \ReflectionClass($className);

        if (empty($constructorArgs) && $reflection->getConstructor()) {
            $params = $reflection->getConstructor()->getParameters();
            foreach ($params as $param) {
                $paramType = $param->getType();
                if ($paramType && $paramType->getName() === DirectiveInteractionService::class) {
                    if ($this->interaction === null) {
                        throw new \RuntimeException('DirectiveInteractionService not set in TestDirectiveRegistry. Call setInteraction() first.');
                    }
                    $constructorArgs[] = $this->interaction;
                } else {
                    $constructorArgs[] = null;
                }
            }
        }

        $directive = $reflection->newInstanceArgs($constructorArgs);
        $this->register($directive->getSignature(), $directive);
        return $directive;
    }

    public function load(): TypedCollection
    {
        $results = new TypedCollection(DirectiveMetadataRecord::class);

        foreach ($this->directives as $signature => $directive) {
            $results->add(new DirectiveMetadataRecord(
                signature: $signature,
                class: get_class($directive),
                description: $directive->getDescription(),
                aliases: $directive->getAliases(),
            ));
        }

        return $results;
    }

    public function getDirective(string $signature): ?AbstractDirective
    {
        return $this->directives[$signature] ?? null;
    }

    public function clear(): void
    {
        $this->directives = [];
    }
}
