<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Configs\EnvSignatureValidationConfig;
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
        $this->service = new SignatureValidationService(new EnvSignatureValidationConfig);
    }

    // ==================== Valid Directive Names ====================

    public function test_validates_simple_name(): void
    {
        // Act: Validate a simple hyphenated name
        $result = $this->service->validate('user-create');

        // Assert: Should be valid with no error
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_single_word(): void
    {
        // Act: Validate a single-word directive
        $result = $this->service->validate('list');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_with_multiple_hyphens(): void
    {
        // Act: Validate a name with multiple hyphens
        $result = $this->service->validate('db-migrate-fresh');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_with_numbers(): void
    {
        // Act: Validate a name containing numbers
        $result = $this->service->validate('api-v2');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_with_numbers_at_end(): void
    {
        // Act: Validate a name ending with numbers
        $result = $this->service->validate('user-create2');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_long_options(): void
    {
        // Act: Validate a long option
        $result = $this->service->validate('--help');

        // Assert: Should be valid (long options are accepted)
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_short_options(): void
    {
        // Act: Validate a single short option
        $result = $this->service->validate('-h');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_short_option_multiple(): void
    {
        // Act: Validate multiple grouped short options
        $result = $this->service->validate('-vl');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    // ==================== Invalid Directive Names ====================

    public function test_rejects_empty_name(): void
    {
        // Act: Validate an empty string
        $result = $this->service->validate('');

        // Assert: Should be invalid with appropriate error
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('cannot be empty', $result->error);
    }

    public function test_rejects_space(): void
    {
        // Act: Validate a name containing a space
        $result = $this->service->validate('user create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_at_symbol(): void
    {
        // Act: Validate a name containing @ symbol
        $result = $this->service->validate('user@create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_colon(): void
    {
        // Act: Validate a name containing colon
        $result = $this->service->validate('user:create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_underscore(): void
    {
        // Act: Validate a name containing underscore
        $result = $this->service->validate('user_create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_starts_with_number(): void
    {
        // Act: Validate a name starting with a number
        $result = $this->service->validate('123-user');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_starts_with_hyphen(): void
    {
        // Act: Validate a name starting with a hyphen
        $result = $this->service->validate('-user');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_consecutive_hyphens(): void
    {
        // Act: Validate a name with consecutive hyphens
        $result = $this->service->validate('user--create');

        // Assert: Should be invalid with specific error
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('consecutive hyphens', $result->error);
    }

    public function test_rejects_ending_with_hyphen(): void
    {
        // Act: Validate a name ending with a hyphen
        $result = $this->service->validate('user-create-');

        // Assert: Should be invalid with specific error
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('end with a hyphen', $result->error);
    }

    public function test_rejects_numbers_only(): void
    {
        // Act: Validate a name that is only numbers
        $result = $this->service->validate('123');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_special_characters(): void
    {
        // Act: Validate a name with special characters
        $result = $this->service->validate('user$create');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    // ==================== Edge Cases ====================

    public function test_validates_directive_with_single_character(): void
    {
        // Act: Validate a single character directive
        $result = $this->service->validate('a');

        // Assert: Should be valid
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_directive_with_max_length_name(): void
    {
        // Act: Validate a very long but valid name
        $longName = 'a'.str_repeat('-b', 100);
        $result = $this->service->validate($longName);

        // Assert: Should be valid (no length limit enforced)
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_uppercase_letters(): void
    {
        // Act: Validate a name with uppercase letters
        $result = $this->service->validate('UserCreate');

        // Assert: Should be valid (uppercase allowed)
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_rejects_leading_hyphen_short_option_like(): void
    {
        // Act: Validate a single hyphen (invalid short option)
        $result = $this->service->validate('-');

        // Assert: Should be invalid
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }
}
