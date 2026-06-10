<?php
// src/Strategies/ParameterParsingStrategy.php

declare(strict_types=1);

namespace AndyDefer\Directive\Strategies;

use AndyDefer\Directive\Enums\ParameterTypeOrder;
use AndyDefer\Directive\Records\ParsedParameterRecord;

interface ParameterParsingStrategy
{
    public function supports(string $parameter): bool;
    public function parse(string $parameter, array $context = []): ParsedParameterRecord;
    public function getTypeOrder(): ParameterTypeOrder;
    public function getTypeName(): string;
    public function isOption(): bool;
    public function isVariadic(): bool;
    public function isRequired(): bool;
}
