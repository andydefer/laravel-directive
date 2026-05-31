<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Testing;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Contracts\DirectiveLoaderInterface;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;

final class TestDirectiveRegistry implements DirectiveLoaderInterface
{
    /**
     * Stockage par classe (FQCN)
     * @var array<class-string<AbstractDirective>, AbstractDirective>
     */
    private array $directives = [];

    /**
     * Index de recherche : signature/alias/nom de base -> FQCN
     * @var array<string, class-string<AbstractDirective>>
     */
    private array $index = [];

    /**
     * Enregistre une directive par son instance
     */
    public function register(AbstractDirective $directive): void
    {
        $className = get_class($directive);

        if (isset($this->directives[$className])) {
            return;
        }

        $this->directives[$className] = $directive;

        $signature = $directive->getSignature();

        // Indexer la signature complète
        $this->index[$signature] = $className;

        // Indexer le nom de base (sans les {} ni les paramètres)
        $baseSignature = explode(' ', $signature)[0];
        $baseSignature = explode('{', $baseSignature)[0];
        $this->index[$baseSignature] = $className;

        // Indexer les alias
        foreach ($directive->getAliases() as $alias) {
            $this->index[$alias] = $className;
        }
    }

    /**
     * Enregistre plusieurs directives
     *
     * @param array<AbstractDirective> $directives
     */
    public function registerAll(array $directives): void
    {
        foreach ($directives as $directive) {
            $this->register($directive);
        }
    }

    public function load(): DirectiveMetadataCollection
    {
        $results = new DirectiveMetadataCollection;

        foreach ($this->directives as $className => $directive) {
            $results->add(new DirectiveMetadataRecord(
                signature: $directive->getSignature(),
                class: $className,
                description: $directive->getDescription(),
                aliases: $directive->getAliases(),
            ));
        }

        return $results;
    }

    /**
     * Récupère une directive par FQCN, signature, alias ou nom de base
     */
    public function getDirective(string $identifier): ?AbstractDirective
    {
        // 1. Chercher par FQCN
        if (isset($this->directives[$identifier])) {
            return $this->directives[$identifier];
        }

        // 2. Chercher dans l'index (signature exacte, alias, ou nom de base)
        if (isset($this->index[$identifier])) {
            $className = $this->index[$identifier];
            return $this->directives[$className] ?? null;
        }

        return null;
    }

    /**
     * Vérifie si une directive est enregistrée par son FQCN
     */
    public function hasDirective(string $className): bool
    {
        return isset($this->directives[$className]);
    }

    /**
     * Retourne toutes les directives enregistrées
     *
     * @return array<class-string<AbstractDirective>, AbstractDirective>
     */
    public function getAllDirectives(): array
    {
        return $this->directives;
    }

    /**
     * Retourne toutes les clés de l'index (signatures, alias, noms de base)
     *
     * @return array<string>
     */
    public function getAllSignatures(): array
    {
        return array_keys($this->index);
    }

    /**
     * Vide le registry
     */
    public function clear(): void
    {
        $this->directives = [];
        $this->index = [];
    }
}
