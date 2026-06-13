<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\PathSegmentsParserService;
use AndyDefer\Directive\Services\StringCaseConverterService;
use AndyDefer\Directive\Tests\UnitTestCase;

final class PathSegmentsParserServiceTest extends UnitTestCase
{
    private PathSegmentsParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $caseConverter = new StringCaseConverterService();
        $this->parser = new PathSegmentsParserService($caseConverter);
    }

    // ============================================================================
    // parse() Tests
    // ============================================================================

    public function test_parse_simple_class_name(): void
    {
        $result = $this->parser->parse('UserRepository');

        $this->assertSame('UserRepository', $result->className);
        $this->assertTrue($result->segments->isEmpty());
        $this->assertTrue($result->pascalSegments->isEmpty());
        $this->assertSame('', $result->subPath);
        $this->assertSame('UserRepository', $result->fullPath);
    }

    public function test_parse_single_directory(): void
    {
        $result = $this->parser->parse('admin' . DIRECTORY_SEPARATOR . 'UserRepository');

        $this->assertSame('UserRepository', $result->className);
        $this->assertSame(['admin'], $result->segments->toArray());
        $this->assertSame(['Admin'], $result->pascalSegments->toArray());
        $this->assertSame('Admin', $result->subPath);
        $this->assertSame('Admin' . DIRECTORY_SEPARATOR . 'UserRepository', $result->fullPath);
    }

    public function test_parse_multiple_directories(): void
    {
        $result = $this->parser->parse('admin' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'ProfileRepository');

        $this->assertSame('ProfileRepository', $result->className);
        $this->assertSame(['admin', 'user'], $result->segments->toArray());
        $this->assertSame(['Admin', 'User'], $result->pascalSegments->toArray());
        $this->assertSame('Admin' . DIRECTORY_SEPARATOR . 'User', $result->subPath);
        $this->assertSame('Admin' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . 'ProfileRepository', $result->fullPath);
    }

    public function test_parse_with_kebab_case_segments(): void
    {
        $result = $this->parser->parse('user-profile' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'UserRepository');

        $this->assertSame('UserRepository', $result->className);
        $this->assertSame(['user-profile', 'admin'], $result->segments->toArray());
        $this->assertSame(['UserProfile', 'Admin'], $result->pascalSegments->toArray());
        $this->assertSame('UserProfile' . DIRECTORY_SEPARATOR . 'Admin', $result->subPath);
        $this->assertSame('UserProfile' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'UserRepository', $result->fullPath);
    }

    public function test_parse_with_snake_case_segments(): void
    {
        $result = $this->parser->parse('user_profile' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'UserRepository');

        $this->assertSame('UserRepository', $result->className);
        $this->assertSame(['user_profile', 'admin'], $result->segments->toArray());
        $this->assertSame(['UserProfile', 'Admin'], $result->pascalSegments->toArray());
        $this->assertSame('UserProfile' . DIRECTORY_SEPARATOR . 'Admin', $result->subPath);
        $this->assertSame('UserProfile' . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'UserRepository', $result->fullPath);
    }

    public function test_parse_deep_nested_path(): void
    {
        $result = $this->parser->parse('App' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'V1' . DIRECTORY_SEPARATOR . 'UserController');

        $this->assertSame('UserController', $result->className);
        $this->assertCount(5, $result->segments);
        $this->assertCount(5, $result->pascalSegments);
        $this->assertSame('App' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'V1', $result->subPath);
        $this->assertSame('App' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . 'V1' . DIRECTORY_SEPARATOR . 'UserController', $result->fullPath);
    }

    // ============================================================================
    // isValid() Tests
    // ============================================================================

    public function test_is_valid_returns_true_for_valid_path(): void
    {
        $this->assertTrue($this->parser->isValid('Admin' . DIRECTORY_SEPARATOR . 'UserRepository'));
        $this->assertTrue($this->parser->isValid('UserRepository'));
        $this->assertTrue($this->parser->isValid('admin' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'repository'));
    }

    public function test_is_valid_returns_false_for_path_with_double_slash(): void
    {
        $doubleSeparator = DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR;
        $this->assertFalse($this->parser->isValid('Admin' . $doubleSeparator . 'UserRepository'));
    }

    public function test_is_valid_returns_false_for_path_with_leading_slash(): void
    {
        $this->assertFalse($this->parser->isValid(DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'UserRepository'));
    }

    public function test_is_valid_returns_false_for_path_with_trailing_slash(): void
    {
        $this->assertFalse($this->parser->isValid('Admin' . DIRECTORY_SEPARATOR . 'UserRepository' . DIRECTORY_SEPARATOR));
    }

    public function test_is_valid_returns_false_for_empty_string(): void
    {
        $this->assertFalse($this->parser->isValid(''));
    }

    // ============================================================================
    // extractClassName() Tests
    // ============================================================================

    public function test_extract_class_name_from_simple_name(): void
    {
        $result = $this->parser->extractClassName('UserRepository');
        $this->assertSame('UserRepository', $result);
    }

    public function test_extract_class_name_from_path(): void
    {
        $result = $this->parser->extractClassName('Admin' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . 'ProfileRepository');
        $this->assertSame('ProfileRepository', $result);
    }

    public function test_extract_class_name_from_deep_path(): void
    {
        $result = $this->parser->extractClassName('App' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'UserController');
        $this->assertSame('UserController', $result);
    }

    // ============================================================================
    // extractDirectorySegments() Tests
    // ============================================================================

    public function test_extract_directory_segments_from_simple_name(): void
    {
        $result = $this->parser->extractDirectorySegments('UserRepository');
        $this->assertEmpty($result);
    }

    public function test_extract_directory_segments_from_path(): void
    {
        $result = $this->parser->extractDirectorySegments('Admin' . DIRECTORY_SEPARATOR . 'User' . DIRECTORY_SEPARATOR . 'ProfileRepository');
        $this->assertSame(['Admin', 'User'], $result);
    }

    public function test_extract_directory_segments_from_deep_path(): void
    {
        $result = $this->parser->extractDirectorySegments('App' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'UserController');
        $this->assertSame(['App', 'Http', 'Controllers'], $result);
    }
}
