<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\SignatureParser;

final class DirectiveParserService implements DirectiveParserInterface
{
    public function __construct(
        private readonly SignatureParser $parser,
    ) {}

    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        return $this->parser->parse($signature, $query);
    }

    public function validate(string $signature, string $query): ValidationResultRecord
    {
        return $this->parser->validate($signature, $query);
    }

    public function isValid(string $signature, string $query): bool
    {
        return $this->parser->isValid($signature, $query);
    }

    public function getValidationErrors(string $signature, string $query): StringTypedCollection
    {
        return $this->parser->getValidationErrors($signature, $query);
    }

    public function validateSignature(string $signature): ValidationResultRecord
    {
        return $this->parser->validateSignature($signature);
    }

    public function isSignatureValid(string $signature): bool
    {
        return $this->parser->isSignatureValid($signature);
    }

    public function addParser(ParserInterface $parser): self
    {
        $this->parser->addParser($parser);

        return $this;
    }

    public function removeParser(string $parserClass): self
    {
        $this->parser->removeParser($parserClass);

        return $this;
    }

    public function getParsers(): array
    {
        return $this->parser->getParsers();
    }

    public function extractSignatureElements(string $signature): StringTypedCollection
    {
        return $this->parser->extractSignatureElements($signature);
    }

    public function extractQueryElements(string $query): StringTypedCollection
    {
        return $this->parser->extractQueryElements($query);
    }
}
