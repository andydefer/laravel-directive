<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Enums;

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Tests\UnitTestCase;

final class InputTypeTest extends UnitTestCase
{
    public function test_get_prompt_suffix_returns_correct_suffix(): void
    {
        $this->assertSame(' ', InputType::SIMPLE_QUESTION->getPromptSuffix());
        $this->assertSame(' (y/n) ', InputType::CONFIRMATION->getPromptSuffix());
        $this->assertSame('', InputType::USER_CHOICE->getPromptSuffix());
    }

    public function test_all_cases_are_covered(): void
    {
        $types = InputType::cases();
        $this->assertCount(3, $types);
        $this->assertContains(InputType::SIMPLE_QUESTION, $types);
        $this->assertContains(InputType::CONFIRMATION, $types);
        $this->assertContains(InputType::USER_CHOICE, $types);
    }
}
