<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

/**
 * Record pour stocker les résultats de recherche.
 *
 * @author Andy Defer
 */
final class SearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $filePath,
        public readonly int $lineNumber,
        public readonly StrictDataObject $data,
        public readonly float $score,
        public readonly float $maxPossible,
        public readonly float $percentage,
        public readonly int $timestamp,
    ) {}
}
