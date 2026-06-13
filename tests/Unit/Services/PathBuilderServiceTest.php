<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Contracts\Configs\FileCreatorConfigInterface;
use AndyDefer\Directive\Services\PathBuilderService;
use AndyDefer\Directive\Services\PathSegmentsParserService;
use AndyDefer\Directive\Services\StringCaseConverterService;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class PathBuilderServiceTest extends UnitTestCase
{
    private PathBuilderService $builder;
    private PathSegmentsParserService $parser;
    private FileCreatorConfigInterface&MockObject $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = $this->createMock(FileCreatorConfigInterface::class);
        $this->config->method('workingDirectory')->willReturn('/var/www');
        $this->config->method('fileExtension')->willReturn('.php');

        $this->builder = new PathBuilderService($this->config);

        $caseConverter = new StringCaseConverterService();
        $this->parser = new PathSegmentsParserService($caseConverter);
    }

    // ============================================================================
    // buildFilePath() Tests
    // ============================================================================

    public function test_build_file_path_without_subpath(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildFilePath('/src/Repositories', $segments);

        $this->assertSame('/var/www/src/Repositories/UserRepository.php', $result);
    }

    public function test_build_file_path_with_subpath(): void
    {
        $segments = $this->parser->parse('Admin/UserRepository');
        $result = $this->builder->buildFilePath('/src/Repositories', $segments);

        $this->assertSame('/var/www/src/Repositories/Admin/UserRepository.php', $result);
    }

    public function test_build_file_path_with_deep_subpath(): void
    {
        $segments = $this->parser->parse('Admin/User/ProfileRepository');
        $result = $this->builder->buildFilePath('/src/Repositories', $segments);

        $this->assertSame('/var/www/src/Repositories/Admin/User/ProfileRepository.php', $result);
    }

    public function test_build_file_path_with_custom_extension(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildFilePath('/src/Repositories', $segments, '.txt');

        $this->assertSame('/var/www/src/Repositories/UserRepository.txt', $result);
    }

    public function test_build_file_path_with_extension_without_dot(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildFilePath('/src/Repositories', $segments, 'txt');

        $this->assertSame('/var/www/src/Repositories/UserRepository.txt', $result);
    }

    public function test_build_file_path_trailing_slash_in_base_directory(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildFilePath('/src/Repositories/', $segments);

        $this->assertSame('/var/www/src/Repositories/UserRepository.php', $result);
    }

    // ============================================================================
    // buildNamespace() Tests
    // ============================================================================

    public function test_build_namespace_without_subpath(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildNamespace('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories', $result);
    }

    public function test_build_namespace_with_subpath(): void
    {
        $segments = $this->parser->parse('Admin/UserRepository');
        $result = $this->builder->buildNamespace('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories\\Admin', $result);
    }

    public function test_build_namespace_with_deep_subpath(): void
    {
        $segments = $this->parser->parse('Admin/User/ProfileRepository');
        $result = $this->builder->buildNamespace('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories\\Admin\\User', $result);
    }

    public function test_build_namespace_with_kebab_case_segments(): void
    {
        $segments = $this->parser->parse('user-profile/Admin/UserRepository');
        $result = $this->builder->buildNamespace('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories\\UserProfile\\Admin', $result);
    }

    // ============================================================================
    // buildRelativePath() Tests
    // ============================================================================

    public function test_build_relative_path_without_subpath(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildRelativePath('/src/Repositories', $segments);

        $this->assertSame('/src/Repositories/UserRepository.php', $result);
    }

    public function test_build_relative_path_with_subpath(): void
    {
        $segments = $this->parser->parse('Admin/UserRepository');
        $result = $this->builder->buildRelativePath('/src/Repositories', $segments);

        $this->assertSame('/src/Repositories/Admin/UserRepository.php', $result);
    }

    public function test_build_relative_path_with_custom_extension(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildRelativePath('/src/Repositories', $segments, '.xml');

        $this->assertSame('/src/Repositories/UserRepository.xml', $result);
    }

    // ============================================================================
    // buildDirectoryPath() Tests
    // ============================================================================

    public function test_build_directory_path_without_subpath(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildDirectoryPath('/src/Repositories', $segments);

        $this->assertSame('/var/www/src/Repositories', $result);
    }

    public function test_build_directory_path_with_subpath(): void
    {
        $segments = $this->parser->parse('Admin/UserRepository');
        $result = $this->builder->buildDirectoryPath('/src/Repositories', $segments);

        $this->assertSame('/var/www/src/Repositories/Admin', $result);
    }

    public function test_build_directory_path_with_deep_subpath(): void
    {
        $segments = $this->parser->parse('Admin/User/ProfileRepository');
        $result = $this->builder->buildDirectoryPath('/src/Repositories', $segments);

        $this->assertSame('/var/www/src/Repositories/Admin/User', $result);
    }

    // ============================================================================
    // buildFullyQualifiedClassName() Tests
    // ============================================================================

    public function test_build_fqcn_without_subpath(): void
    {
        $segments = $this->parser->parse('UserRepository');
        $result = $this->builder->buildFullyQualifiedClassName('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories\\UserRepository', $result);
    }

    public function test_build_fqcn_with_subpath(): void
    {
        $segments = $this->parser->parse('Admin/UserRepository');
        $result = $this->builder->buildFullyQualifiedClassName('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories\\Admin\\UserRepository', $result);
    }

    public function test_build_fqcn_with_deep_subpath(): void
    {
        $segments = $this->parser->parse('Admin/User/ProfileRepository');
        $result = $this->builder->buildFullyQualifiedClassName('App\\Repositories', $segments);

        $this->assertSame('App\\Repositories\\Admin\\User\\ProfileRepository', $result);
    }
}
