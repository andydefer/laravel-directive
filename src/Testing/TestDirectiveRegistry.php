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
        $signature = $directive->getSignature();

        echo "\n========== REGISTER ==========\n";
        echo "Class: {$className}\n";
        echo "Signature: {$signature}\n";

        if (isset($this->directives[$className])) {
            echo "  -> Already registered by class\n";
            return;
        }

        $this->directives[$className] = $directive;

        // Indexer la signature complète
        $this->index[$signature] = $className;
        echo "  -> Indexed by full signature: {$signature}\n";

        // Indexer le nom de base
        $baseSignature = explode(' ', $signature)[0];
        $baseSignature = explode('{', $baseSignature)[0];
        $this->index[$baseSignature] = $className;
        echo "  -> Indexed by base signature: {$baseSignature}\n";

        // Indexer les alias
        foreach ($directive->getAliases() as $alias) {
            $this->index[$alias] = $className;
            echo "  -> Indexed by alias: {$alias}\n";
        }

        echo "Current index keys: " . json_encode(array_keys($this->index)) . "\n";
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
        echo "\n========== GET DIRECTIVE ==========\n";
        echo "Identifier: {$identifier}\n";
        echo "Index keys: " . json_encode(array_keys($this->index)) . "\n";

        if (isset($this->directives[$identifier])) {
            echo "  -> Found by class\n";
            return $this->directives[$identifier];
        }

        if (isset($this->index[$identifier])) {
            $className = $this->index[$identifier];
            echo "  -> Found by index: {$className}\n";
            return $this->directives[$className];
        }

        echo "  -> NOT FOUND\n";
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
