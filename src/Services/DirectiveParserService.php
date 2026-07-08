<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use AndyDefer\Directive\Contracts\Services\DirectiveParserInterface;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\SignatureParser;

/**
 * Service for parsing and validating directive signatures.
 *
 * This service acts as a wrapper around the SignatureParser, providing
 * a unified interface for all directive parsing operations including
 * signature validation, query parsing, and parser registry management.
 */
final class DirectiveParserService implements DirectiveParserInterface
{
    /**
     * @param  SignatureParser  $parser  The underlying signature parser
     */
    public function __construct(
        private readonly SignatureParser $parser,
    ) {}

    /**
     * Parses a query string against a signature.
     *
     * @param  string  $signature  The signature definition
     * @param  string  $query  The query string to parse
     * @return ParsedSignatureRecord The parsed signature data
     */
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        return $this->parser->parse($signature, $query);
    }

    /**
     * Validates a query against a signature.
     *
     * @param  string  $signature  The signature definition
     * @param  string  $query  The query string to validate
     * @return ValidationResultRecord The validation result
     */
    public function validate(string $signature, string $query): ValidationResultRecord
    {
        return $this->parser->validate($signature, $query);
    }

    /**
     * Checks if a query is valid against a signature.
     *
     * @param  string  $signature  The signature definition
     * @param  string  $query  The query string to check
     * @return bool True if the query is valid, false otherwise
     */
    public function isValid(string $signature, string $query): bool
    {
        return $this->parser->isValid($signature, $query);
    }

    /**
     * Gets validation errors for a query against a signature.
     *
     * @param  string  $signature  The signature definition
     * @param  string  $query  The query string to validate
     * @return StringTypedCollection Collection of validation error messages
     */
    public function getValidationErrors(string $signature, string $query): StringTypedCollection
    {
        return $this->parser->getValidationErrors($signature, $query);
    }

    /**
     * Validates a signature definition.
     *
     * @param  string  $signature  The signature definition to validate
     * @return ValidationResultRecord The validation result
     */
    public function validateSignature(string $signature): ValidationResultRecord
    {
        return $this->parser->validateSignature($signature);
    }

    /**
     * Checks if a signature definition is valid.
     *
     * @param  string  $signature  The signature definition to check
     * @return bool True if the signature is valid, false otherwise
     */
    public function isSignatureValid(string $signature): bool
    {
        return $this->parser->isSignatureValid($signature);
    }

    /**
     * Adds a custom parser to the registry.
     *
     * @param  ParserInterface  $parser  The parser to add
     */
    public function addParser(ParserInterface $parser): self
    {
        $this->parser->addParser($parser);

        return $this;
    }

    /**
     * Removes a parser from the registry.
     *
     * @param  string  $parserClass  The parser class name to remove
     */
    public function removeParser(string $parserClass): self
    {
        $this->parser->removeParser($parserClass);

        return $this;
    }

    /**
     * Gets all registered parsers.
     *
     * @return array<int, ParserInterface> The list of registered parsers
     */
    public function getParsers(): array
    {
        return $this->parser->getParsers();
    }

    /**
     * Extracts individual elements from a signature.
     *
     * @param  string  $signature  The signature to extract elements from
     * @return StringTypedCollection Collection of signature elements
     */
    public function extractSignatureElements(string $signature): StringTypedCollection
    {
        return $this->parser->extractSignatureElements($signature);
    }

    /**
     * Extracts individual elements from a query.
     *
     * @param  string  $query  The query to extract elements from
     * @return StringTypedCollection Collection of query elements
     */
    public function extractQueryElements(string $query): StringTypedCollection
    {
        return $this->parser->extractQueryElements($query);
    }
}
