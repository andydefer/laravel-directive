<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Strategies;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpJsonl\Contracts\JsonlPathStrategyInterface;

/**
 * Stratégie simple pour la recherche dans les fichiers JSONL.
 * N'écrit pas de fichiers, sert uniquement pour satisfaire le constructeur de JsonlService.
 */
final class SearchPathStrategy implements JsonlPathStrategyInterface
{
    public function __construct(
        private readonly string $basePath = '/tmp'
    ) {}

    public function getFilePath(AbstractRecord $entity): string
    {
        // Pour l'écriture des résultats de recherche
        if ($entity instanceof SearchResultRecord) {
            return $this->basePath.'/search_results/'.$entity->sessionId.'.jsonl';
        }

        return $this->basePath.'/default.jsonl';
    }

    public function getFilesToScan(AbstractRecord $query): array
    {
        return [];
    }

    public function getBaseDirectory(): string
    {
        return $this->basePath;
    }
}
