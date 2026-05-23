<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Enums;

use AndyDefer\Directive\Enums\ShortOption;
use AndyDefer\Directive\Tests\TestCase;

final class ShortOptionTest extends TestCase
{
    public function test_get_allowed_characters_returns_all_values(): void
    {
        $allowed = ShortOption::getAllowedCharacters();

        $this->assertCount(7, $allowed);
        $this->assertContains('h', $allowed);
        $this->assertContains('l', $allowed);
        $this->assertContains('v', $allowed);
        $this->assertContains('q', $allowed);
        $this->assertContains('f', $allowed);
        $this->assertContains('d', $allowed);
        $this->assertContains('V', $allowed);
    }

    public function test_is_allowed_returns_true_for_valid_options(): void
    {
        $this->assertTrue(ShortOption::isAllowed('h'));
        $this->assertTrue(ShortOption::isAllowed('l'));
        $this->assertTrue(ShortOption::isAllowed('v'));
        $this->assertTrue(ShortOption::isAllowed('q'));
        $this->assertTrue(ShortOption::isAllowed('f'));
        $this->assertTrue(ShortOption::isAllowed('d'));
        $this->assertTrue(ShortOption::isAllowed('V'));
    }

    public function test_is_allowed_returns_false_for_invalid_options(): void
    {
        $this->assertFalse(ShortOption::isAllowed('x'));
        $this->assertFalse(ShortOption::isAllowed('z'));
        $this->assertFalse(ShortOption::isAllowed('a'));
    }

    public function test_parse_returns_valid_characters_for_single_option(): void
    {
        $result = ShortOption::parse('-h');
        $this->assertSame(['h'], $result);

        $result = ShortOption::parse('-l');
        $this->assertSame(['l'], $result);
    }

    public function test_parse_returns_valid_characters_for_multiple_options(): void
    {
        $result = ShortOption::parse('-vl');
        $this->assertSame(['v', 'l'], $result);

        $result = ShortOption::parse('-hvf');
        $this->assertSame(['h', 'v', 'f'], $result);
    }

    public function test_parse_returns_null_for_invalid_short_option(): void
    {
        $this->assertNull(ShortOption::parse('-x'));
        $this->assertNull(ShortOption::parse('-vx'));
        $this->assertNull(ShortOption::parse('-'));
        $this->assertNull(ShortOption::parse('--h'));
        $this->assertNull(ShortOption::parse('h'));
    }

    public function test_is_valid_returns_true_for_valid_short_options(): void
    {
        $this->assertTrue(ShortOption::isValid('-h'));
        $this->assertTrue(ShortOption::isValid('-l'));
        $this->assertTrue(ShortOption::isValid('-vl'));
        $this->assertTrue(ShortOption::isValid('-hvf'));
    }

    public function test_is_valid_returns_false_for_invalid_short_options(): void
    {
        $this->assertFalse(ShortOption::isValid('-x'));
        $this->assertFalse(ShortOption::isValid('-vx'));
        $this->assertFalse(ShortOption::isValid('-'));
        $this->assertFalse(ShortOption::isValid('--h'));
    }

    public function test_get_label_returns_correct_label(): void
    {
        $this->assertSame('Help', ShortOption::HELP->getLabel());
        $this->assertSame('List', ShortOption::LIST->getLabel());
        $this->assertSame('Verbose output', ShortOption::VERBOSE->getLabel());
    }

    public function test_get_long_option_returns_correct_string(): void
    {
        $this->assertSame('help', ShortOption::HELP->getLongOption());
        $this->assertSame('list', ShortOption::LIST->getLongOption());
        $this->assertSame('verbose', ShortOption::VERBOSE->getLongOption());
    }

    public function test_get_description_returns_correct_description(): void
    {
        $this->assertStringContainsString('help message', ShortOption::HELP->getDescription());
        $this->assertStringContainsString('List all', ShortOption::LIST->getDescription());
    }

    public function test_get_display_string_returns_formatted_string(): void
    {
        $this->assertSame('-h, --help', ShortOption::HELP->getDisplayString());
        $this->assertSame('-l, --list', ShortOption::LIST->getDisplayString());
        $this->assertSame('-v, --verbose', ShortOption::VERBOSE->getDisplayString());
    }
}
