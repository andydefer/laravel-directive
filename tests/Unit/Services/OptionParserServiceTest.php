<?php

// tests/Unit/Services/OptionParserServiceTest.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Services\OptionParserService;
use AndyDefer\Directive\Tests\UnitTestCase;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class OptionParserServiceTest extends UnitTestCase
{
    private OptionParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OptionParserService;
    }

    public function test_is_option_with_long_option(): void
    {
        $this->assertTrue($this->service->isOption('--force'));
        $this->assertTrue($this->service->isOption('--role=admin'));
        $this->assertFalse($this->service->isOption('force'));
        $this->assertFalse($this->service->isOption('admin'));
    }

    public function test_is_option_with_short_option(): void
    {
        $this->assertTrue($this->service->isOption('-v'));
        $this->assertTrue($this->service->isOption('-f'));
        $this->assertFalse($this->service->isOption('v'));
        $this->assertFalse($this->service->isOption('f'));
    }

    public function test_parse_long_option_flag(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--force');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('force'));
        $this->assertTrue($options->isFlag('force'));
        $this->assertTrue($options->isTrue('force'));
    }

    public function test_parse_long_option_with_value(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--role=admin');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('role'));
        $this->assertFalse($options->isFlag('role'));
        $this->assertSame('admin', $options->get('role'));
    }

    public function test_parse_long_option_with_empty_value(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--role=');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('role'));
        $this->assertTrue($options->isFlag('role'));
        $this->assertTrue($options->isTrue('role'));
    }

    public function test_parse_long_option_with_value_containing_equals(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--message=Hello=World');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('message'));
        $this->assertFalse($options->isFlag('message'));
        $this->assertSame('Hello=World', $options->get('message'));
    }

    public function test_parse_short_option_single(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('-v');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('v'));
        $this->assertTrue($options->isFlag('v'));
        $this->assertTrue($options->isTrue('v'));
    }

    public function test_parse_multiple_short_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('-v', '-f', '-d');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('v'));
        $this->assertTrue($options->has('f'));
        $this->assertTrue($options->has('d'));
        $this->assertTrue($options->isFlag('v'));
        $this->assertTrue($options->isFlag('f'));
        $this->assertTrue($options->isFlag('d'));
    }

    public function test_parse_grouped_short_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('-vfd');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('v'));
        $this->assertTrue($options->has('f'));
        $this->assertTrue($options->has('d'));
        $this->assertTrue($options->isFlag('v'));
        $this->assertTrue($options->isFlag('f'));
        $this->assertTrue($options->isFlag('d'));
    }

    public function test_parse_mixed_options(): void
    {
        $argv = new StringTypedCollection;
        $argv->add('--force', '--role=admin', '-v', '--verbose', '--message=Hello=World');

        $options = new ParsedOptionCollection;

        $this->service->parseOptions($argv, $options);

        $this->assertTrue($options->has('force'));
        $this->assertTrue($options->has('role'));
        $this->assertTrue($options->has('v'));
        $this->assertTrue($options->has('verbose'));
        $this->assertTrue($options->has('message'));

        $this->assertSame('admin', $options->get('role'));
        $this->assertSame('Hello=World', $options->get('message'));
        $this->assertTrue($options->isTrue('force'));
        $this->assertTrue($options->isTrue('v'));
        $this->assertTrue($options->isTrue('verbose'));
    }
}
