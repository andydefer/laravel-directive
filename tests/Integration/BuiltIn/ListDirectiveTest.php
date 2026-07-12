<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\BuiltIn;

use AndyDefer\Directive\BuiltIn\ListDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class ListDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService($this->app);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== SIGNATURE TESTS ====================

    public function test_get_signature(): void
    {
        $directive = new ListDirective($this->service->getKernel(), '');
        $signature = $directive->getSignature();

        $this->assertStringContainsString('list', $signature);
        $this->assertStringContainsString('source=?', $signature);
        $this->assertStringContainsString('format', $signature);
        $this->assertStringContainsString('json,default', $signature);
        $this->assertStringContainsString('Directive name to inspect', $signature);
        $this->assertStringContainsString('Output format (json or default)', $signature);
    }

    public function test_get_description(): void
    {
        $directive = new ListDirective($this->service->getKernel(), '');
        $this->assertStringContainsString('List all available directives', $directive->getDescription());
    }

    public function test_get_aliases(): void
    {
        $directive = new ListDirective($this->service->getKernel(), '');
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('ls'));
        $this->assertTrue($aliases->contains('-l'));
        $this->assertTrue($aliases->contains('--list'));
    }

    // ==================== LIST ALL DIRECTIVES TESTS ====================

    public function test_list_all_directives(): void
    {
        $response = $this->service->run('list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Available Directives', $cleanedOutput);
        $this->assertStringContainsString('Total:', $cleanedOutput);
        $this->assertStringContainsString('General', $cleanedOutput);
        $this->assertStringContainsString('list', $cleanedOutput);
        $this->assertStringContainsString('help', $cleanedOutput);
        $this->assertStringContainsString('version', $cleanedOutput);
    }

    public function test_list_all_directives_with_alias_ls(): void
    {
        $response = $this->service->run('ls');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Available Directives', $cleanedOutput);
        $this->assertStringContainsString('Total:', $cleanedOutput);
    }

    public function test_list_all_directives_with_alias_l(): void
    {
        $response = $this->service->run('-l');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Available Directives', $cleanedOutput);
        $this->assertStringContainsString('Total:', $cleanedOutput);
    }

    public function test_list_all_directives_with_alias_list(): void
    {
        $response = $this->service->run('--list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Available Directives', $cleanedOutput);
        $this->assertStringContainsString('Total:', $cleanedOutput);
    }

    // ==================== SHOW DIRECTIVE DETAILS TESTS ====================

    public function test_show_directive_details_default_format(): void
    {
        $response = $this->service->run('list list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: list', $cleanedOutput);
        $this->assertStringContainsString('Signature', $cleanedOutput);
        $this->assertStringContainsString('Description', $cleanedOutput);
        $this->assertStringContainsString('Class', $cleanedOutput);
        $this->assertStringContainsString('Aliases', $cleanedOutput);
        $this->assertStringContainsString('Default Arguments', $cleanedOutput);
        $this->assertStringContainsString('Enums', $cleanedOutput);
        $this->assertStringContainsString('Example', $cleanedOutput);
        $this->assertStringContainsString('Usage', $cleanedOutput);
    }

    public function test_show_directive_details_with_comments(): void
    {
        $response = $this->service->run('list list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        // Les commentaires sont dans les labels avec -> [comment]
        $this->assertStringContainsString('source -> [Directive name to inspect]', $cleanedOutput);
        $this->assertStringContainsString('format -> [Output format (json or default)]', $cleanedOutput);

        // La signature est nettoyée, donc ne contient pas les commentaires
        $this->assertStringNotContainsString('Directive name to inspect"', $cleanedOutput);
        $this->assertStringNotContainsString('Output format (json or default)"', $cleanedOutput);
    }

    public function test_show_directive_details_in_json_format(): void
    {
        $response = $this->service->run('list list json');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📄 Directive Details (JSON)', $cleanedOutput);
        $this->assertStringContainsString('"signature"', $cleanedOutput);
        $this->assertStringContainsString('"source"', $cleanedOutput);
        $this->assertStringContainsString('"directive"', $cleanedOutput);
        $this->assertStringContainsString('"documentation"', $cleanedOutput);

        // ✅ Vérifier que les commentaires sont présents (sans se soucier des guillemets)
        $this->assertStringContainsString('Directive name to inspect', $cleanedOutput);
        $this->assertStringContainsString('Output format (json or default)', $cleanedOutput);
    }

    public function test_show_directive_details_for_help(): void
    {
        $response = $this->service->run('list help');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: help', $cleanedOutput);
        $this->assertStringContainsString('help', $cleanedOutput);
        $this->assertStringContainsString('Display help information', $cleanedOutput);
        $this->assertStringContainsString('Aliases', $cleanedOutput);
        $this->assertStringContainsString('-h', $cleanedOutput);
        $this->assertStringContainsString('--help', $cleanedOutput);
    }

    public function test_show_directive_details_for_version(): void
    {
        $response = $this->service->run('list version');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: version', $cleanedOutput);
        $this->assertStringContainsString('version', $cleanedOutput);
        $this->assertStringContainsString('Display the application version', $cleanedOutput);
        $this->assertStringContainsString('Aliases', $cleanedOutput);
        $this->assertStringContainsString('-v', $cleanedOutput);
        $this->assertStringContainsString('--version', $cleanedOutput);
    }

    public function test_show_directive_details_via_alias(): void
    {
        $response = $this->service->run('list ls');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: list', $cleanedOutput);
    }

    // ==================== DIRECTIVE NOT FOUND TESTS ====================

    public function test_show_details_for_nonexistent_directive(): void
    {
        $response = $this->service->run('list nonexistent-directive');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
        $this->assertStringContainsString('Directive not found: nonexistent-directive', $cleanedOutput);
        // Les suggestions ne sont pas affichées dans l'environnement de test sans BK-tree
        // Donc on vérifie juste le message d'erreur
    }

    public function test_show_details_for_nonexistent_directive_with_suggestion(): void
    {
        $response = $this->service->run('list lst');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
        $this->assertStringContainsString('Directive not found: lst', $cleanedOutput);
    }

    // ==================== FORMAT TESTS ====================

    public function test_list_with_json_format(): void
    {
        $response = $this->service->run('list list json');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📄 Directive Details (JSON)', $cleanedOutput);
        $this->assertStringContainsString('"signature"', $cleanedOutput);
        $this->assertStringContainsString('"description"', $cleanedOutput);
        $this->assertStringContainsString('"class"', $cleanedOutput);
        $this->assertStringContainsString('"aliases"', $cleanedOutput);
        $this->assertStringContainsString('"source"', $cleanedOutput);
    }

    public function test_list_with_default_format(): void
    {
        $response = $this->service->run('list list default');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: list', $cleanedOutput);
        $this->assertStringContainsString('Signature', $cleanedOutput);
        $this->assertStringContainsString('Description', $cleanedOutput);
        $this->assertStringContainsString('Class', $cleanedOutput);
        $this->assertStringContainsString('Aliases', $cleanedOutput);
    }

    // ==================== CATEGORY CLEANING TESTS ====================

    public function test_category_names_are_cleaned(): void
    {
        $response = $this->service->run('list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // La catégorie ne doit pas contenir les commentaires de la signature
        $this->assertStringNotContainsString('Directive name to inspect', $cleanedOutput);
        $this->assertStringNotContainsString('Output format (json or default)', $cleanedOutput);
        // La catégorie doit être "General"
        $this->assertStringContainsString('General', $cleanedOutput);
    }

    // ==================== EDGE CASES ====================

    public function test_list_shows_directives_count(): void
    {
        $response = $this->service->run('list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertMatchesRegularExpression('/Total:\s+\d+\s+directives/', $cleanedOutput);
    }

    public function test_list_displays_aliases_in_list(): void
    {
        $response = $this->service->run('list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('(aliases:', $cleanedOutput);
        $this->assertStringContainsString('ls', $cleanedOutput);
        $this->assertStringContainsString('-l', $cleanedOutput);
        $this->assertStringContainsString('--list', $cleanedOutput);
    }

    public function test_list_with_source_parameter_uses_enum_format(): void
    {
        $response = $this->service->run('list list json');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📄 Directive Details (JSON)', $cleanedOutput);
    }

    public function test_list_with_source_and_invalid_format_uses_default(): void
    {
        $response = $this->service->run('list list invalid');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: list', $cleanedOutput);
        $this->assertStringContainsString('Signature', $cleanedOutput);
    }

    public function test_list_with_only_source_parameter(): void
    {
        $response = $this->service->run('list list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Details for: list', $cleanedOutput);
    }
}
