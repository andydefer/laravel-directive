<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Services;

use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Tests\UnitTestCase;

/**
 * @covers \AndyDefer\Directive\Services\DirectiveNamingService
 */
final class DirectiveNamingServiceTest extends UnitTestCase
{
    private DirectiveNamingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveNamingService;
    }

    // ==================== Generate Class Name Tests ====================

    public function test_generate_class_name_converts_simple_name(): void
    {
        // Act: Generate class name from simple hyphenated name
        $result = $this->service->generateClassName('user-create');

        // Assert: Should convert to PascalCase with suffix
        $this->assertSame('UserCreateDirective', $result);
    }

    public function test_generate_class_name_converts_single_word(): void
    {
        // Act: Generate class name from single word
        $result = $this->service->generateClassName('list');

        // Assert: Should capitalize first letter and add suffix
        $this->assertSame('ListDirective', $result);
    }

    public function test_generate_class_name_converts_multiple_hyphens(): void
    {
        // Act: Generate class name with multiple hyphens
        $result = $this->service->generateClassName('db-migrate-fresh');

        // Assert: Should capitalize each segment
        $this->assertSame('DbMigrateFreshDirective', $result);
    }

    public function test_generate_class_name_converts_with_numbers(): void
    {
        // Act: Generate class name containing numbers
        $result = $this->service->generateClassName('api-v2');

        // Assert: Numbers should be preserved
        $this->assertSame('ApiV2Directive', $result);
    }

    public function test_generate_class_name_converts_complex_name(): void
    {
        // Act: Generate class name from complex name
        $result = $this->service->generateClassName('user-profile-create-v2');

        // Assert: All segments should be capitalized
        $this->assertSame('UserProfileCreateV2Directive', $result);
    }

    public function test_generate_class_name_handles_uppercase_input(): void
    {
        // Act: Generate class name from uppercase input
        $result = $this->service->generateClassName('USER-CREATE');

        // Assert: Should convert to proper case
        $this->assertSame('UserCreateDirective', $result);
    }

    public function test_generate_class_name_handles_mixed_case_input(): void
    {
        // Act: Generate class name from mixed case input
        $result = $this->service->generateClassName('User-Create');

        // Assert: Should normalize case
        $this->assertSame('UserCreateDirective', $result);
    }

    // ==================== Generate Signature With Option Tests ====================

    public function test_generate_signature_with_option_adds_placeholder(): void
    {
        // Act: Generate signature with option
        $result = $this->service->generateSignatureWithOption('user-create');

        // Assert: Should append option placeholder
        $this->assertSame('user-create {--option}', $result);
    }

    public function test_generate_signature_with_option_handles_empty_name(): void
    {
        // Act: Generate signature with empty name
        $result = $this->service->generateSignatureWithOption('');

        // Assert: Should only have placeholder
        $this->assertSame(' {--option}', $result);
    }

    // ==================== Replace Stub Variables Tests ====================

    public function test_replace_stub_variables(): void
    {
        // Arrange: Create stub template
        $stub = 'class {{class}} extends BaseDirective {
    protected string $signature = "{{signature}}";
    protected string $description = "{{description}}";
    // Generated: {{date}}
}';
        $className = 'UserCreateDirective';
        $signature = 'user-create';

        // Act: Replace stub variables
        $result = $this->service->replaceStubVariables($stub, $className, $signature);

        // Assert: All placeholders should be replaced
        $this->assertStringContainsString('class UserCreateDirective', $result);
        $this->assertStringContainsString('user-create {--option}', $result);
        $this->assertStringContainsString('Generated directive for user-create', $result);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result);
    }

    public function test_replace_stub_variables_with_empty_stub(): void
    {
        // Arrange: Empty stub
        $stub = '';
        $className = 'TestDirective';
        $signature = 'test';

        // Act: Replace variables
        $result = $this->service->replaceStubVariables($stub, $className, $signature);

        // Assert: Should return empty string
        $this->assertSame('', $result);
    }

    public function test_replace_stub_variables_preserves_unmatched_placeholders(): void
    {
        // Arrange: Stub with unknown placeholder
        $stub = '{{class}} {{unknown}}';
        $className = 'TestDirective';
        $signature = 'test';

        // Act: Replace variables
        $result = $this->service->replaceStubVariables($stub, $className, $signature);

        // Assert: Unknown placeholder should remain unchanged
        $this->assertSame('TestDirective {{unknown}}', $result);
    }

    // ==================== Extract Base Name Tests ====================

    public function test_extract_base_name_from_class_name(): void
    {
        // Act: Extract base name
        $result = $this->service->extractBaseName('UserCreateDirective');

        // Assert: Should convert to kebab-case without suffix
        $this->assertSame('user-create', $result);
    }

    public function test_extract_base_name_from_single_word(): void
    {
        // Act: Extract base name
        $result = $this->service->extractBaseName('ListDirective');

        // Assert: Should return single word
        $this->assertSame('list', $result);
    }

    public function test_extract_base_name_from_name_with_numbers(): void
    {
        // Act: Extract base name
        $result = $this->service->extractBaseName('ApiV2Directive');

        // Assert: Should handle numbers correctly
        $this->assertSame('api-v2', $result);
    }

    public function test_extract_base_name_from_class_name_without_suffix(): void
    {
        // Act: Extract base name from class without suffix
        $result = $this->service->extractBaseName('UserCreate');

        // Assert: Should still convert to kebab-case
        $this->assertSame('user-create', $result);
    }

    public function test_extract_base_name_from_complex_class_name(): void
    {
        // Act: Extract base name
        $result = $this->service->extractBaseName('UserProfileCreateV2Directive');

        // Assert: Should handle multiple segments
        $this->assertSame('user-profile-create-v2', $result);
    }

    public function test_extract_base_name_from_camel_case_without_directive(): void
    {
        // Act: Extract base name from camelCase
        $result = $this->service->extractBaseName('userCreate');

        // Assert: Should detect uppercase letters
        $this->assertSame('user-create', $result);
    }

    // ==================== Integration Tests ====================

    public function test_round_trip_conversion(): void
    {
        // Arrange: Original directive name
        $original = 'user-profile-create-v2';

        // Act: Convert to class name and back
        $className = $this->service->generateClassName($original);
        $extracted = $this->service->extractBaseName($className);

        // Assert: Should return original name
        $this->assertSame($original, $extracted);
    }

    public function test_multiple_round_trip_conversions(): void
    {
        $testCases = [
            'user-create',
            'list',
            'db-migrate-fresh',
            'api-v2',
            'cache-clear-force',
            'generate-report-daily',
        ];

        foreach ($testCases as $original) {
            $className = $this->service->generateClassName($original);
            $extracted = $this->service->extractBaseName($className);
            $this->assertSame($original, $extracted, "Failed for: {$original}");
        }
    }

    public function test_generate_stub_produces_valid_php(): void
    {
        // Arrange: Basic PHP class stub
        $stub = '<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\Attributes\AsDirective;

#[AsDirective(name: "{{signature}}")]
class {{class}}
{
    public function __invoke(): void
    {
        // {{description}}
        // Generated: {{date}}
    }
}';
        $className = 'TestDirective';
        $signature = 'test-command';

        // Act: Generate stub
        $result = $this->service->replaceStubVariables($stub, $className, $signature);

        // Assert: Basic PHP syntax validation
        $this->assertStringStartsWith('<?php', $result);
        $this->assertStringContainsString('namespace App\Directives;', $result);
        $this->assertStringContainsString('#[AsDirective(name: "test-command {--option}")]', $result);
        $this->assertStringContainsString('class TestDirective', $result);
    }
}
