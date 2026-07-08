<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Contracts\Services;

use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;

/**
 * Interface for parsing and validating directive signatures.
 *
 * Combines the functionality of signature parsing with parser registry management.
 * This interface serves as the main entry point for all directive parsing operations,
 * including signature validation, query parsing, and parser extension.
 *
 * @see ParserRegistryInterface For parser registration and management
 * @see SignatureParserInterface For core parsing functionality
 */
interface DirectiveParserInterface extends ParserRegistryInterface, SignatureParserInterface
{
    // This interface combines two separate concerns:
    // 1. Parsing and validating directive signatures (SignatureParserInterface)
    // 2. Managing custom parsers (ParserRegistryInterface)
    //
    // No additional methods are required as both parent interfaces
    // provide the complete API surface needed.
}
