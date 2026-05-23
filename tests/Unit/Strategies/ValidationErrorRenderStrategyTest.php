<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Strategies\ValidationErrorRenderStrategy;
use AndyDefer\Directive\Tests\TestCase;

final class ValidationErrorRenderStrategyTest extends TestCase
{
    private ValidationErrorRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ValidationErrorRenderStrategy();
    }

    public function test_supports_validation_error_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::VALIDATION_ERROR));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
        $this->assertFalse($this->strategy->supports(RenderType::SUCCESS));
    }

    public function test_execute_with_validation_record(): void
    {
        $record = new ValidationResultRecord(
            isValid: false,
            error: 'Invalid signature format: "create@user"'
        );

        $replacements = $this->strategy->execute($record, RenderType::VALIDATION_ERROR);

        $this->assertSame(1, $replacements->count());

        $values = $replacements->getValues()->toArray();
        $this->assertContains('Invalid signature format: "create@user"', $values);
    }

    public function test_execute_without_error_uses_default(): void
    {
        $record = new ValidationResultRecord(isValid: false, error: null);

        $replacements = $this->strategy->execute($record, RenderType::VALIDATION_ERROR);

        $values = $replacements->getValues()->toArray();
        $this->assertContains('Invalid signature', $values);
    }

    public function test_execute_with_invalid_record_uses_default(): void
    {
        $record = new \stdClass();

        $replacements = $this->strategy->execute($record, RenderType::VALIDATION_ERROR);

        $values = $replacements->getValues()->toArray();
        $this->assertContains('Invalid signature', $values);
    }
}
