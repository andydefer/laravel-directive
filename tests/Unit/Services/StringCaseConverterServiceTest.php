<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\StringCaseConverterService;
use AndyDefer\Directive\Tests\UnitTestCase;

final class StringCaseConverterServiceTest extends UnitTestCase
{
    private StringCaseConverterService $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new StringCaseConverterService();
    }

    // ============================================================================
    // toPascalCase() Tests
    // ============================================================================

    public function test_to_pascal_case_converts_kebab_case(): void
    {
        $result = $this->converter->toPascalCase('user-profile');
        $this->assertSame('UserProfile', $result);
    }

    public function test_to_pascal_case_converts_snake_case(): void
    {
        $result = $this->converter->toPascalCase('user_profile');
        $this->assertSame('UserProfile', $result);
    }

    public function test_to_pascal_case_converts_multiple_segments(): void
    {
        $result = $this->converter->toPascalCase('send-welcome-email-task');
        $this->assertSame('SendWelcomeEmailTask', $result);
    }

    public function test_to_pascal_case_converts_mixed_separators(): void
    {
        $result = $this->converter->toPascalCase('user-profile_name_test');
        $this->assertSame('UserProfileNameTest', $result);
    }

    public function test_to_pascal_case_handles_single_word(): void
    {
        $result = $this->converter->toPascalCase('user');
        $this->assertSame('User', $result);
    }

    public function test_to_pascal_case_handles_empty_string(): void
    {
        $result = $this->converter->toPascalCase('');
        $this->assertSame('', $result);
    }

    public function test_to_pascal_case_handles_string_without_separators(): void
    {
        $result = $this->converter->toPascalCase('username');
        $this->assertSame('Username', $result);
    }

    // ============================================================================
    // toKebabCase() Tests
    // ============================================================================

    public function test_to_kebab_case_converts_pascal_case(): void
    {
        $result = $this->converter->toKebabCase('UserProfile');
        $this->assertSame('user-profile', $result);
    }

    public function test_to_kebab_case_converts_multiple_words(): void
    {
        $result = $this->converter->toKebabCase('SendWelcomeEmailTask');
        $this->assertSame('send-welcome-email-task', $result);
    }

    public function test_to_kebab_case_handles_single_word(): void
    {
        $result = $this->converter->toKebabCase('User');
        $this->assertSame('user', $result);
    }

    public function test_to_kebab_case_handles_acronyms(): void
    {
        $result = $this->converter->toKebabCase('XMLParser');
        $this->assertSame('x-m-l-parser', $result);
    }

    public function test_to_kebab_case_handles_numbers(): void
    {
        $result = $this->converter->toKebabCase('UserProfile123');
        $this->assertSame('user-profile123', $result);
    }

    public function test_to_kebab_case_handles_empty_string(): void
    {
        $result = $this->converter->toKebabCase('');
        $this->assertSame('', $result);
    }

    // ============================================================================
    // toSnakeCase() Tests
    // ============================================================================

    public function test_to_snake_case_converts_pascal_case(): void
    {
        $result = $this->converter->toSnakeCase('UserProfile');
        $this->assertSame('user_profile', $result);
    }

    public function test_to_snake_case_converts_multiple_words(): void
    {
        $result = $this->converter->toSnakeCase('SendWelcomeEmailTask');
        $this->assertSame('send_welcome_email_task', $result);
    }

    public function test_to_snake_case_handles_single_word(): void
    {
        $result = $this->converter->toSnakeCase('User');
        $this->assertSame('user', $result);
    }

    public function test_to_snake_case_handles_acronyms(): void
    {
        $result = $this->converter->toSnakeCase('XMLParser');
        $this->assertSame('x_m_l_parser', $result);
    }

    public function test_to_snake_case_handles_empty_string(): void
    {
        $result = $this->converter->toSnakeCase('');
        $this->assertSame('', $result);
    }

    // ============================================================================
    // kebabToSnake() Tests
    // ============================================================================

    public function test_kebab_to_snake_converts_kebab_case(): void
    {
        $result = $this->converter->kebabToSnake('user-profile');
        $this->assertSame('user_profile', $result);
    }

    public function test_kebab_to_snake_converts_multiple_segments(): void
    {
        $result = $this->converter->kebabToSnake('send-welcome-email-task');
        $this->assertSame('send_welcome_email_task', $result);
    }

    public function test_kebab_to_snake_handles_single_word(): void
    {
        $result = $this->converter->kebabToSnake('user');
        $this->assertSame('user', $result);
    }

    public function test_kebab_to_snake_handles_empty_string(): void
    {
        $result = $this->converter->kebabToSnake('');
        $this->assertSame('', $result);
    }

    public function test_kebab_to_snake_handles_string_without_separators(): void
    {
        $result = $this->converter->kebabToSnake('username');
        $this->assertSame('username', $result);
    }

    // ============================================================================
    // snakeToKebab() Tests
    // ============================================================================

    public function test_snake_to_kebab_converts_snake_case(): void
    {
        $result = $this->converter->snakeToKebab('user_profile');
        $this->assertSame('user-profile', $result);
    }

    public function test_snake_to_kebab_converts_multiple_segments(): void
    {
        $result = $this->converter->snakeToKebab('send_welcome_email_task');
        $this->assertSame('send-welcome-email-task', $result);
    }

    public function test_snake_to_kebab_handles_single_word(): void
    {
        $result = $this->converter->snakeToKebab('user');
        $this->assertSame('user', $result);
    }

    public function test_snake_to_kebab_handles_empty_string(): void
    {
        $result = $this->converter->snakeToKebab('');
        $this->assertSame('', $result);
    }

    // ============================================================================
    // Combined/Integration Tests
    // ============================================================================

    public function test_roundtrip_kebab_to_pascal_to_kebab(): void
    {
        $original = 'user-profile';
        $pascal = $this->converter->toPascalCase($original);
        $result = $this->converter->toKebabCase($pascal);

        $this->assertSame($original, $result);
    }

    public function test_roundtrip_snake_to_pascal_to_snake(): void
    {
        $original = 'user_profile';
        $pascal = $this->converter->toPascalCase($original);
        $result = $this->converter->toSnakeCase($pascal);

        $this->assertSame($original, $result);
    }

    public function test_roundtrip_kebab_to_snake_to_kebab(): void
    {
        $original = 'user-profile';
        $snake = $this->converter->kebabToSnake($original);
        $result = $this->converter->snakeToKebab($snake);

        $this->assertSame($original, $result);
    }
}
