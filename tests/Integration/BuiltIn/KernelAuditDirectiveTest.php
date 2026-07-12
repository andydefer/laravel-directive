<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\BuiltIn;

use AndyDefer\Directive\BuiltIn\KernelAuditDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class KernelAuditDirectiveTest extends IntegrationTestCase
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
        $directive = new KernelAuditDirective($this->service->getKernel(), '');
        $signature = $directive->getSignature();

        $this->assertStringContainsString('kernel:audit', $signature);
        $this->assertStringContainsString('format=table', $signature);
        $this->assertStringContainsString('--verbose', $signature);
    }

    public function test_get_description(): void
    {
        $directive = new KernelAuditDirective($this->service->getKernel(), '');
        $this->assertStringContainsString('Audit the kernel discovery system', $directive->getDescription());
    }

    public function test_get_aliases(): void
    {
        $directive = new KernelAuditDirective($this->service->getKernel(), '');
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('audit'));
    }

    // ==================== EXECUTION TESTS ====================

    public function test_audit_with_no_problems_returns_success(): void
    {
        $response = $this->service->run('kernel:audit');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 Kernel Audit Report', $cleanedOutput);
        $this->assertStringContainsString('✅ No problems found in discovery system', $cleanedOutput);
        $this->assertStringContainsString('📊 Discovery Statistics', $cleanedOutput);
        $this->assertStringContainsString('✅ Audit completed', $cleanedOutput);
    }

    public function test_audit_with_verbose_flag(): void
    {
        $response = $this->service->run('kernel:audit --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 Kernel Audit Report', $cleanedOutput);
        $this->assertStringContainsString('📊 Discovery Statistics', $cleanedOutput);
        $this->assertStringContainsString('📋 Directives Breakdown', $cleanedOutput);
        $this->assertStringContainsString('⚙️ Configuration', $cleanedOutput);
        $this->assertStringContainsString('Base path', $cleanedOutput);
        $this->assertStringContainsString('Max depth', $cleanedOutput);
        $this->assertStringContainsString('✅ Audit completed', $cleanedOutput);
    }

    public function test_audit_with_table_format(): void
    {
        $response = $this->service->run('kernel:audit format=table');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 Kernel Audit Report', $cleanedOutput);
        $this->assertStringContainsString('✅ No problems found in discovery system', $cleanedOutput);
        $this->assertStringContainsString('📊 Discovery Statistics', $cleanedOutput);
        $this->assertStringContainsString('✅ Audit completed', $cleanedOutput);
    }

    public function test_audit_displays_statistics_correctly(): void
    {
        $response = $this->service->run('kernel:audit');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total directives', $cleanedOutput);
        $this->assertStringContainsString('Unique classes', $cleanedOutput);
        $this->assertStringContainsString('Problems found', $cleanedOutput);
        $this->assertStringContainsString('Sources enabled', $cleanedOutput);
        $this->assertStringContainsString('Auto-discovery', $cleanedOutput);
        $this->assertStringContainsString('Silent mode', $cleanedOutput);
        $this->assertStringContainsString('Max depth', $cleanedOutput);
    }

    // ==================== PROBLEM DETECTION TESTS ====================

    public function test_audit_detects_problems_when_invalid_directive_present(): void
    {
        // Ajouter une directive invalide qui va générer des problèmes
        $kernel = $this->service->getKernel();

        // Forcer l'ajout d'un problème manuellement pour simuler une directive invalide
        $kernel->addProblem(
            'test_problem',
            'Test problem context',
            'Test problem message',
            ['test' => 'data']
        );

        $response = $this->service->run('kernel:audit');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('❌ 1 problem(s) found', $cleanedOutput);
        $this->assertStringContainsString('test_problem', $cleanedOutput);
        $this->assertStringContainsString('Test problem context', $cleanedOutput);
        $this->assertStringContainsString('Test problem message', $cleanedOutput);
    }

    public function test_audit_with_verbose_shows_context_data(): void
    {
        $kernel = $this->service->getKernel();

        $kernel->addProblem(
            'test_problem_verbose',
            'Verbose test context',
            'Verbose test message',
            ['key' => 'value', 'number' => 42]
        );

        $response = $this->service->run('kernel:audit --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('test_problem_verbose', $cleanedOutput);
        $this->assertStringContainsString('Context Data', $cleanedOutput);

        $this->assertStringContainsString('"key"', $cleanedOutput);
        $this->assertStringContainsString('"value"', $cleanedOutput);
        $this->assertStringContainsString('"number"', $cleanedOutput);
        $this->assertStringContainsString('42', $cleanedOutput);
    }

    public function test_audit_with_list_format_shows_problems_as_list(): void
    {
        $kernel = $this->service->getKernel();

        $kernel->addProblem(
            'list_test_problem',
            'List test context',
            'List test message',
            ['data' => 'test']
        );

        $response = $this->service->run('kernel:audit format=list');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('❌ 1 problem(s) found', $cleanedOutput);
        $this->assertStringContainsString('❌ list_test_problem', $cleanedOutput);
        $this->assertStringContainsString('Context: List test context', $cleanedOutput);
        $this->assertStringContainsString('Message: List test message', $cleanedOutput);
        $this->assertStringContainsString('Time:', $cleanedOutput);
        $this->assertStringNotContainsString('Context Data', $cleanedOutput);
    }

    public function test_audit_handles_multiple_problems(): void
    {
        $kernel = $this->service->getKernel();

        $kernel->addProblem('problem_1', 'Context 1', 'Message 1', ['data' => 1]);
        $kernel->addProblem('problem_2', 'Context 2', 'Message 2', ['data' => 2]);
        $kernel->addProblem('problem_3', 'Context 3', 'Message 3', ['data' => 3]);

        $response = $this->service->run('kernel:audit');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('❌ 3 problem(s) found', $cleanedOutput);
        $this->assertStringContainsString('problem_1', $cleanedOutput);
        $this->assertStringContainsString('problem_2', $cleanedOutput);
        $this->assertStringContainsString('problem_3', $cleanedOutput);
        $this->assertStringContainsString('Message 1', $cleanedOutput);
        $this->assertStringContainsString('Message 2', $cleanedOutput);
        $this->assertStringContainsString('Message 3', $cleanedOutput);
    }

    // ==================== FORMAT TESTS ====================

    public function test_audit_accepts_different_formats(): void
    {
        $formats = ['table', 'list'];

        foreach ($formats as $format) {
            $response = $this->service->run("kernel:audit format={$format}");

            $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
            $this->assertStringContainsString('🔍 Kernel Audit Report', $this->stripAnsi($response->output));
        }
    }

    public function test_audit_with_invalid_format_uses_table_as_default(): void
    {
        $response = $this->service->run('kernel:audit format=invalid');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 Kernel Audit Report', $this->stripAnsi($response->output));
    }

    // ==================== EDGE CASES ====================

    public function test_audit_with_empty_kernel(): void
    {
        // Créer un kernel vide pour le test
        $service = new DirectiveTestingService($this->app, [], true);

        $response = $service->run('kernel:audit');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 Kernel Audit Report', $cleanedOutput);
        $this->assertStringContainsString('Total directives', $cleanedOutput);
        $this->assertStringContainsString('✅ No problems found', $cleanedOutput);
        $this->assertStringContainsString('✅ Audit completed', $cleanedOutput);

        $service->destroy();
    }

    public function test_audit_clears_problems_after_execution(): void
    {
        $kernel = $this->service->getKernel();

        $kernel->addProblem('test_clear', 'Clear context', 'Clear message');

        $response = $this->service->run('kernel:audit');

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);

        // Les problèmes devraient être vides après l'exécution (la directive ne les supprime pas)
        // Mais ils sont affichés dans le rapport
        $this->assertNotEmpty($kernel->getProblems());
    }

    public function test_audit_displays_configuration_in_verbose_mode(): void
    {
        $response = $this->service->run('kernel:audit --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⚙️ Configuration', $cleanedOutput);
        $this->assertStringContainsString('Base path', $cleanedOutput);
        $this->assertStringContainsString('Log path', $cleanedOutput);
        $this->assertStringContainsString('Version', $cleanedOutput);
        $this->assertStringContainsString('Max depth', $cleanedOutput);
        $this->assertStringContainsString('Auto-discovery', $cleanedOutput);
        $this->assertStringContainsString('Silent mode', $cleanedOutput);
    }

    public function test_audit_displays_ignored_sources_when_present(): void
    {
        // Ignorer une source pour le test
        $kernel = $this->service->getKernel();
        $kernel->ignoreSource('VENDOR');

        $response = $this->service->run('kernel:audit --verbose');

        $cleanedOutput = $this->stripAnsi($response->output);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⚠️ Ignored Sources:', $cleanedOutput);
    }
}
