<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Feature;

use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveIntegrationTest extends IntegrationTestCase
{
    private DirectiveKernel $kernel;

    private string $fixturesDirectivesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesDirectivesPath = realpath(__DIR__.'/../Fixtures/Directives');

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesDirectivesPath);
        $this->app->instance(DirectiveConfig::class, $config);
        $this->app->register(DirectiveServiceProvider::class);

        $this->kernel = $this->app->make(DirectiveKernel::class);

        // Debug: Vérifier que le chemin est bien pris en compte
        $resolvedConfig = $this->app->make(DirectiveConfig::class);
    }

    private function runAndCaptureOutput(array $argv): array
    {

        ob_start();
        $result = $this->kernel->run($argv);
        $output = ob_get_clean();

        return [
            'result' => $result,
            'output' => $output,
        ];
    }

    public function test_kernel_shows_help_when_no_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Directive System', $response['output']);
        $this->assertStringContainsString('USAGE:', $response['output']);
    }

    public function test_kernel_returns_success_for_help_command(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--help']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Directive System', $response['output']);
    }

    public function test_kernel_returns_success_for_list_command(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertTrue(
            str_contains($response['output'], 'Available Directives') ||
                str_contains($response['output'], 'No Directives Found'),
            'Output should contain either "Available Directives" or "No Directives Found"'
        );
    }

    public function test_kernel_returns_success_for_existing_directive(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_handles_directive_with_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', 'Custom Message']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Custom Message', $response['output']);
    }

    public function test_kernel_returns_not_found_for_unknown_command(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'unknown-command']);

        $this->assertSame(ExitCode::NOT_FOUND, $response['result']);
        $this->assertStringContainsString('not found', $response['output']);
    }

    public function test_kernel_handles_directive_with_options(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '--verbose']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_respects_directive_aliases(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'echo']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_handles_empty_string_as_argument(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
    }

    public function test_kernel_handles_special_characters_in_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', 'Hello @#$% World!']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello @#$% World!', $response['output']);
    }

    public function test_kernel_handles_multiple_arguments(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', 'arg1 arg2 arg3']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('arg1 arg2 arg3', $response['output']);
    }

    public function test_kernel_handles_help_with_short_option(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '-h']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Directive System', $response['output']);
    }

    public function test_kernel_handles_list_with_short_option(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '-l']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertTrue(
            str_contains($response['output'], 'Available Directives') ||
                str_contains($response['output'], 'No Directives Found'),
            'Output should contain either "Available Directives" or "No Directives Found"'
        );
    }

    public function test_kernel_handles_mixed_short_and_long_options(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '-v', '--verbose', '--force']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_handles_option_with_value(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '--message=HelloWorld']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Hello World', $response['output']);
    }

    public function test_kernel_returns_not_found_for_invalid_alias(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'invalid-alias']);

        $this->assertSame(ExitCode::NOT_FOUND, $response['result']);
        $this->assertStringContainsString('not found', $response['output']);
    }

    public function test_kernel_handles_very_long_argument(): void
    {
        $longArgument = str_repeat('a', 1000);
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', $longArgument]);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString($longArgument, $response['output']);
    }

    public function test_kernel_handles_unicode_characters_in_argument(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo', '你好世界']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('你好世界', $response['output']);
    }

    public function test_kernel_list_displays_registered_directives(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('test-echo', $response['output']);
    }

    public function test_kernel_boots_laravel_when_directive_requests_it(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-laravel']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Test Laravel directive executed', $response['output']);
    }

    public function test_kernel_does_not_boot_laravel_when_directive_does_not_request_it(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-echo']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringNotContainsString('Laravel bootstrapped', $response['output']);
    }

    public function test_kernel_executes_directive_with_laravel_features_when_available(): void
    {
        $response = $this->runAndCaptureOutput(['directive', 'test-laravel-db']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Database query successful', $response['output']);
    }

    public function test_discover_automatically_finds_directives_in_fixtures(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('test-echo', $response['output']);
        $this->assertStringContainsString('Test echo directive', $response['output']);
    }

    public function test_kernel_ignores_invalid_directive_files(): void
    {
        $invalidDir = sys_get_temp_dir().'/invalid_directives_'.uniqid();
        mkdir($invalidDir, 0777, true);

        $invalidContent = <<<'PHP'
<?php

namespace App\Directives;

use AndyDefer\Directive\Contracts\DirectiveInterface;

final class InvalidDirective implements DirectiveInterface
{
    public function execute(): ExitCode { return ExitCode::SUCCESS; }
}
PHP;

        file_put_contents($invalidDir.'/InvalidDirective.php', $invalidContent);

        $config = DirectiveConfig::default()->withDirectivesPath($invalidDir);
        $this->app->instance(DirectiveConfig::class, $config);

        $kernel = $this->app->make(DirectiveKernel::class);
        $response = $this->runAndCaptureOutput(['directive', '--list']);

        $this->assertStringNotContainsString('InvalidDirective', $response['output']);
        $this->assertStringNotContainsString('invalid', $response['output']);

        unlink($invalidDir.'/InvalidDirective.php');
        rmdir($invalidDir);
    }

    public function test_kernel_displays_version(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '--version']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Laravel Directive', $response['output']);
        $this->assertStringContainsString('Version:', $response['output']);
    }

    public function test_kernel_displays_version_with_short_option(): void
    {
        $response = $this->runAndCaptureOutput(['directive', '-v']);

        $this->assertSame(ExitCode::SUCCESS, $response['result']);
        $this->assertStringContainsString('Laravel Directive', $response['output']);
        $this->assertStringContainsString('Version:', $response['output']);
    }
}
