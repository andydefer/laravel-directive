<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Strategies;

use AndyDefer\Directive\Enums\MessageType;
use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\DisplayMessageRecord;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Strategies\DisplayMessageRenderStrategy;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Utils\EmptyRecord;

final class DisplayMessageRenderStrategyTest extends UnitTestCase
{
    private DisplayMessageRenderStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DisplayMessageRenderStrategy;
    }

    public function test_supports_display_message_type(): void
    {
        $this->assertTrue($this->strategy->supports(RenderType::DISPLAY_MESSAGE));
        $this->assertFalse($this->strategy->supports(RenderType::HELP));
        $this->assertFalse($this->strategy->supports(RenderType::LIST));
        $this->assertFalse($this->strategy->supports(RenderType::SUCCESS));
        $this->assertFalse($this->strategy->supports(RenderType::ERROR));
        $this->assertFalse($this->strategy->supports(RenderType::CONFLICT));
        $this->assertFalse($this->strategy->supports(RenderType::TABLE));
        $this->assertFalse($this->strategy->supports(RenderType::VALIDATION_ERROR));
    }

    public function test_execute_with_info_message_returns_correct_replacements(): void
    {
        $messageRecord = new DisplayMessageRecord('Info message', MessageType::INFO);
        $record = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: $messageRecord);

        $replacements = $this->strategy->execute($record, RenderType::DISPLAY_MESSAGE);

        $this->assertSame(3, $replacements->count());

        $placeholders = $replacements->getPlaceholders()->toArray();
        $values = $replacements->getValues()->toArray();

        $this->assertContains('{{color}}', $placeholders);
        $this->assertContains('{{message}}', $placeholders);
        $this->assertContains('{{reset}}', $placeholders);

        $this->assertContains('Info message', $values);
        $this->assertContains("\033[32m", $values);
        $this->assertContains("\033[0m", $values);
    }

    public function test_execute_with_error_message_returns_correct_replacements(): void
    {
        $messageRecord = new DisplayMessageRecord('Error message', MessageType::ERROR);
        $record = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: $messageRecord);

        $replacements = $this->strategy->execute($record, RenderType::DISPLAY_MESSAGE);

        $values = $replacements->getValues()->toArray();

        $this->assertContains("\033[31m", $values);
        $this->assertContains("\033[0m", $values);
    }

    public function test_execute_with_warning_message_returns_correct_replacements(): void
    {
        $messageRecord = new DisplayMessageRecord('Warning message', MessageType::WARNING);
        $record = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: $messageRecord);

        $replacements = $this->strategy->execute($record, RenderType::DISPLAY_MESSAGE);

        $values = $replacements->getValues()->toArray();

        $this->assertContains("\033[33m", $values);
        $this->assertContains("\033[0m", $values);
    }

    public function test_execute_with_line_message_returns_no_color_codes(): void
    {
        $messageRecord = new DisplayMessageRecord('Line message', MessageType::LINE);
        $record = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: $messageRecord);

        $replacements = $this->strategy->execute($record, RenderType::DISPLAY_MESSAGE);

        $values = $replacements->getValues()->toArray();

        $this->assertContains('Line message', $values);
        $this->assertContains('', $values);
        $this->assertContains('', $values);
    }

    public function test_execute_with_invalid_record_returns_empty_replacements(): void
    {
        $record = new EmptyRecord;

        $replacements = $this->strategy->execute($record, RenderType::DISPLAY_MESSAGE);

        $this->assertTrue($replacements->isEmpty());
    }

    public function test_execute_with_render_record_but_no_message_record_returns_empty(): void
    {
        $record = new RenderRecord(type: RenderType::DISPLAY_MESSAGE, messageRecord: null);

        $replacements = $this->strategy->execute($record, RenderType::DISPLAY_MESSAGE);

        $this->assertTrue($replacements->isEmpty());
    }
}
