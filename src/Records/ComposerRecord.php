<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Traits\Hydratable;
use AndyDefer\DomainStructures\Utils\Associative;

/**
 * Record representing a composer.json file structure.
 */
final class ComposerRecord extends AbstractRecord
{
    use Hydratable;

    public function __construct(
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?string $type,
        public readonly ?string $license,
        public readonly Associative $require,
        public readonly Associative $require_dev,
        public readonly Associative $autoload,
        public readonly Associative $autoload_dev,
        public readonly Associative $scripts,
        public readonly Associative $extra,
        public readonly ?string $minimum_stability,
        public readonly bool $prefer_stable,
        public readonly Associative $config,
        public readonly ?string $version = null,
    ) {}
}
