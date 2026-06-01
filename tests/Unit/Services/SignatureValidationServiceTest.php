<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tests\UnitTestCase;

/**
 * @covers \AndyDefer\Directive\Services\SignatureValidationService
 */
final class SignatureValidationServiceTest extends UnitTestCase
{
    private SignatureValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SignatureValidationService();
    }

    // ==================== Valid Directive Names ====================

    public function testValidatesSimpleName(): void
    {
        // Act: Validate a simple hyphenated name
        $result = $this->service->validate('user-create');

        // Assert: Should be valid with no error
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesSingleWord(): void
    {
        // Act: Validate a single-word directive
        $result = $this->service->validate('list');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesWithMultipleHyphens(): void
    {
        // Act: Validate a name with multiple hyphens
        $result = $this->service->validate('db-migrate-fresh');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesWithNumbers(): void
    {
        // Act: Validate a name containing numbers
        $result = $this->service->validate('api-v2');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesWithNumbersAtEnd(): void
    {
        // Act: Validate a name ending with numbers
        $result = $this->service->validate('user-create2');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesLongOptions(): void
    {
        // Act: Validate a long option
        $result = $this->service->validate('--help');

        // Assert: Should be valid (long options are accepted)
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesShortOptions(): void
    {
        // Act: Validate a single short option
        $result = $this->service->validate('-h');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesShortOptionMultiple(): void
    {
        // Act: Validate multiple grouped short options
        $result = $this->service->validate('-vl');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    // ==================== Invalid Directive Names ====================

    public function testRejectsEmptyName(): void
    {
        // Act: Validate an empty string
        $result = $this->service->validate('');

        // Assert: Should be invalid with appropriate error
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('cannot be empty', $result->error);
    }

    public function testRejectsSpace(): void
    {
        // Act: Validate a name containing a space
        $result = $this->service->validate('user create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsAtSymbol(): void
    {
        // Act: Validate a name containing @ symbol
        $result = $this->service->validate('user@create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsColon(): void
    {
        // Act: Validate a name containing colon
        $result = $this->service->validate('user:create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsUnderscore(): void
    {
        // Act: Validate a name containing underscore
        $result = $this->service->validate('user_create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsStartsWithNumber(): void
    {
        // Act: Validate a name starting with a number
        $result = $this->service->validate('123-user');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsStartsWithHyphen(): void
    {
        // Act: Validate a name starting with a hyphen
        $result = $this->service->validate('-user');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsConsecutiveHyphens(): void
    {
        // Act: Validate a name with consecutive hyphens
        $result = $this->service->validate('user--create');

        // Assert: Should be invalid with specific error
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('consecutive hyphens', $result->error);
    }

    public function testRejectsEndingWithHyphen(): void
    {
        // Act: Validate a name ending with a hyphen
        $result = $this->service->validate('user-create-');

        // Assert: Should be invalid with specific error
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('end with a hyphen', $result->error);
    }

    public function testRejectsNumbersOnly(): void
    {
        // Act: Validate a name that is only numbers
        $result = $this->service->validate('123');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function testRejectsSpecialCharacters(): void
    {
        // Act: Validate a name with special characters
        $result = $this->service->validate('user$create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    // ==================== Edge Cases ====================

    public function testValidatesDirectiveWithSingleCharacter(): void
    {
        // Act: Validate a single character directive
        $result = $this->service->validate('a');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesDirectiveWithMaxLengthName(): void
    {
        // Act: Validate a very long but valid name
        $longName = 'a' . str_repeat('-b', 100);
        $result = $this->service->validate($longName);

        // Assert: Should be valid (no length limit enforced)
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testValidatesUppercaseLetters(): void
    {
        // Act: Validate a name with uppercase letters
        $result = $this->service->validate('UserCreate');

        // Assert: Should be valid (uppercase allowed)
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function testRejectsLeadingHyphenShortOptionLike(): void
    {
        // Act: Validate a single hyphen (invalid short option)
        $result = $this->service->validate('-');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }
}
