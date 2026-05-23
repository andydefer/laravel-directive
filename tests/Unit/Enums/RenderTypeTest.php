<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Enums;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Tests\TestCase;

final class RenderTypeTest extends TestCase
{
    public function test_success_returns_correct_message(): void
    {
        $replacements = ['{{message}}' => 'Operation completed'];
        $result = RenderType::SUCCESS->render($replacements);

        $this->assertStringContainsString('Operation completed', $result);
        $this->assertStringContainsString("\033[32m", $result);
        $this->assertStringContainsString("\033[0m", $result);
    }

    public function test_error_returns_correct_message(): void
    {
        $replacements = ['{{message}}' => 'Something went wrong'];
        $result = RenderType::ERROR->render($replacements);

        $this->assertStringContainsString('Something went wrong', $result);
        $this->assertStringContainsString("\033[31m", $result);
        $this->assertStringContainsString("\033[0m", $result);
    }

    public function test_help_contains_required_sections(): void
    {
        $result = RenderType::HELP->render();

        $this->assertStringContainsString('Directive System', $result);
        $this->assertStringContainsString('USAGE:', $result);
        $this->assertStringContainsString('COMMANDS:', $result);
        $this->assertStringContainsString('EXAMPLES:', $result);
        $this->assertStringContainsString('CREATE YOUR OWN DIRECTIVE:', $result);
    }

    public function test_list_contains_placeholders(): void
    {
        $result = RenderType::LIST->render();

        $this->assertStringContainsString('{{count}}', $result);
        $this->assertStringContainsString('{{rows}}', $result);
    }

    public function test_list_with_replacements_renders_correctly(): void
    {
        $replacements = [
            '{{count}}' => '2',
            '{{rows}}' => "  test-cmd          Test command",
        ];
        $result = RenderType::LIST->render($replacements);

        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('test-cmd', $result);
        $this->assertStringContainsString('Test command', $result);
    }

    public function test_not_found_contains_signature_placeholder(): void
    {
        $result = RenderType::NOT_FOUND->render();

        $this->assertStringContainsString('{{signature}}', $result);
    }

    public function test_not_found_with_replacements_renders_correctly(): void
    {
        $replacements = ['{{signature}}' => 'unknown-cmd'];
        $result = RenderType::NOT_FOUND->render($replacements);

        $this->assertStringContainsString('unknown-cmd', $result);
        $this->assertStringContainsString('not found', $result);
        $this->assertStringContainsString('Suggestions:', $result);
    }

    public function test_empty_contains_helpful_message(): void
    {
        $result = RenderType::EMPTY->render();

        // Check for key phrases instead of exact strings
        $this->assertStringContainsString('No Directives Found', $result);
        $this->assertStringContainsString('first directive', $result);
        $this->assertStringContainsString('mkdir', $result);
        $this->assertStringContainsString('app/Directives', $result);
        $this->assertStringContainsString('HelloDirective', $result);
    }

    public function test_conflict_contains_placeholders(): void
    {
        $result = RenderType::CONFLICT->render();

        $this->assertStringContainsString('{{name}}', $result);
        $this->assertStringContainsString('{{options}}', $result);
    }

    public function test_conflict_with_replacements_renders_correctly(): void
    {
        $replacements = [
            '{{name}}' => 'add-user',
            '{{options}}' => "1. UserCreateDirective (signature: user-create)\n2. UserCreateAgainDirective (signature: user-create-again)",
        ];
        $result = RenderType::CONFLICT->render($replacements);

        $this->assertStringContainsString('add-user', $result);
        $this->assertStringContainsString('UserCreateDirective', $result);
        $this->assertStringContainsString('UserCreateAgainDirective', $result);
    }

    public function test_table_contains_placeholder(): void
    {
        $result = RenderType::TABLE->render();

        $this->assertSame('{{table}}', $result);
    }

    public function test_table_with_replacements_renders_correctly(): void
    {
        $replacements = ['{{table}}' => "| Name | Email |\n| John | john@example.com |"];
        $result = RenderType::TABLE->render($replacements);

        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('Email', $result);
        $this->assertStringContainsString('john@example.com', $result);
    }

    public function test_validation_error_contains_error_placeholder(): void
    {
        $result = RenderType::VALIDATION_ERROR->render();

        $this->assertStringContainsString('{{error}}', $result);
    }

    public function test_validation_error_with_replacements_renders_correctly(): void
    {
        $replacements = ['{{error}}' => 'Invalid signature format: "create@user"'];
        $result = RenderType::VALIDATION_ERROR->render($replacements);

        $this->assertStringContainsString('Invalid signature format: "create@user"', $result);
        $this->assertStringContainsString('Valid examples:', $result);
        $this->assertStringContainsString('user-create', $result);
    }

    public function test_display_message_contains_color_placeholders(): void
    {
        $result = RenderType::DISPLAY_MESSAGE->render();

        $this->assertStringContainsString('{{color}}', $result);
        $this->assertStringContainsString('{{message}}', $result);
        $this->assertStringContainsString('{{reset}}', $result);
    }

    public function test_display_message_with_replacements_renders_correctly(): void
    {
        $replacements = [
            '{{color}}' => "\033[32m",
            '{{message}}' => 'Hello World',
            '{{reset}}' => "\033[0m",
        ];
        $result = RenderType::DISPLAY_MESSAGE->render($replacements);

        $this->assertStringContainsString('Hello World', $result);
        $this->assertStringContainsString("\033[32m", $result);
        $this->assertStringContainsString("\033[0m", $result);
    }

    public function test_get_default_message_returns_correct_message(): void
    {
        $this->assertSame('Directive executed successfully', RenderType::SUCCESS->getDefaultMessage());
        $this->assertSame('Directive execution failed', RenderType::ERROR->getDefaultMessage());
        $this->assertSame('', RenderType::HELP->getDefaultMessage());
        $this->assertSame('', RenderType::LIST->getDefaultMessage());
        $this->assertSame('', RenderType::NOT_FOUND->getDefaultMessage());
        $this->assertSame('', RenderType::EMPTY->getDefaultMessage());
        $this->assertSame('', RenderType::CONFLICT->getDefaultMessage());
        $this->assertSame('', RenderType::TABLE->getDefaultMessage());
        $this->assertSame('', RenderType::VALIDATION_ERROR->getDefaultMessage());
        $this->assertSame('', RenderType::DISPLAY_MESSAGE->getDefaultMessage());
    }

    public function test_render_without_replacements_returns_raw_content(): void
    {
        $result = RenderType::SUCCESS->render();
        $this->assertStringContainsString('{{message}}', $result);
    }
}
