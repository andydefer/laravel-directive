<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Enums;

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Tests\TestCase;

final class RenderTypeTest extends TestCase
{
    public function test_get_stub_name_returns_correct_filename(): void
    {
        $this->assertSame('help.stub', RenderType::HELP->getStubName());
        $this->assertSame('list.stub', RenderType::LIST->getStubName());
        $this->assertSame('not-found.stub', RenderType::NOT_FOUND->getStubName());
        $this->assertSame('success.stub', RenderType::SUCCESS->getStubName());
        $this->assertSame('error.stub', RenderType::ERROR->getStubName());
        $this->assertSame('empty.stub', RenderType::EMPTY->getStubName());
        $this->assertSame('conflict.stub', RenderType::CONFLICT->getStubName());
        $this->assertSame('table.stub', RenderType::TABLE->getStubName());
        $this->assertSame('validation-error.stub', RenderType::VALIDATION_ERROR->getStubName());
        $this->assertSame('display-message.stub', RenderType::DISPLAY_MESSAGE->getStubName());
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
}
