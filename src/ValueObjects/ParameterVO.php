<?php

declare(strict_types=1);

namespace AndyDefer\Directive\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpServices\Enums\PrimitiveType;
use AndyDefer\PhpServices\Services\PrimitiveTypeConverterService;
use InvalidArgumentException;

final class ParameterVO extends AbstractValueObject
{
    private PrimitiveTypeConverterService $converter;

    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
        public readonly PrimitiveType $type,
    ) {
        if (empty($name)) {
            throw new InvalidArgumentException('Parameter name cannot be empty');
        }

        $this->converter = new PrimitiveTypeConverterService;
    }

    public function getValue(): StrictDataObject
    {
        return new StrictDataObject([
            'name' => $this->name,
            'value' => $this->converter->convert($this->value, $this->type),
        ]);
    }
}
