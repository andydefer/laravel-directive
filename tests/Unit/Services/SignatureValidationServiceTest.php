<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tests\UnitTestCase;

final class SignatureValidationServiceTest extends UnitTestCase
{
    private SignatureValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SignatureValidationService;
    }

    // ==================== Valid directive names ====================

    public function test_validates_simple_name(): void
    {
        $result = $this->service->validate('user-create');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_single_word(): void
    {
        $result = $this->service->validate('list');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_with_multiple_hyphens(): void
    {
        $result = $this->service->validate('db-migrate-fresh');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_with_numbers(): void
    {
        $result = $this->service->validate('api-v2');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_with_numbers_at_end(): void
    {
        $result = $this->service->validate('user-create2');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_long_options(): void
    {
        $result = $this->service->validate('--help');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_short_options(): void
    {
        $result = $this->service->validate('-h');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_short_option_multiple(): void
    {
        $result = $this->service->validate('-vl');
        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    // ==================== Invalid directive names ====================

    public function test_rejects_empty_name(): void
    {
        $result = $this->service->validate('');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('cannot be empty', $result->error);
    }

    public function test_rejects_space(): void
    {
        $result = $this->service->validate('user create');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_at_symbol(): void
    {
        $result = $this->service->validate('user@create');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_colon(): void
    {
        $result = $this->service->validate('user:create');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_underscore(): void
    {
        $result = $this->service->validate('user_create');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_starts_with_number(): void
    {
        $result = $this->service->validate('123-user');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_starts_with_hyphen(): void
    {
        $result = $this->service->validate('-user');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_consecutive_hyphens(): void
    {
        $result = $this->service->validate('user--create');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('consecutive hyphens', $result->error);
    }

    public function test_rejects_ending_with_hyphen(): void
    {
        $result = $this->service->validate('user-create-');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('end with a hyphen', $result->error);
    }

    public function test_rejects_numbers_only(): void
    {
        $result = $this->service->validate('123');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }

    public function test_rejects_special_characters(): void
    {
        $result = $this->service->validate('user$create');
        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid directive name', $result->error);
    }
}
