<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use AndyDefer\Directive\Tests\UnitTestCase;

final class MakeDirectiveTest extends UnitTestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_get_signature_returns_make_directive(): void
    {
        $directive = $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);
        $signature = $directive->getSignature();
        $this->assertSame('make-directive {name}', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $directive = $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);
        $description = $directive->getDescription();
        $this->assertSame(
            'Create a new directive class (supports subdirectories like "user/hello-directive")',
            $description
        );
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);
        $aliases = $directive->getAliases();
        $this->assertTrue($aliases->contains('create-directive'));
        $this->assertTrue($aliases->contains('make-cmd'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_execute_returns_error_when_name_missing(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive');

        // Le kernel retourne INVALID_ARGUMENT quand l'argument 'name' est manquant
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Not enough arguments', $response->getOutput());
    }

    public function test_execute_creates_file_with_correct_replacements(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive', ['user-create']);

        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('✅ Directive created successfully!', $response->getOutput());

        // Vérifier le contenu du fichier
        $expectedPath = $this->directiveTempDir . '/app/Directives/UserCreateDirective.php';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class UserCreateDirective', $content);
        $this->assertStringContainsString("return 'user-create'", $content);
        $this->assertStringContainsString('namespace App\\Directives;', $content);
    }

    public function test_execute_creates_directory_when_not_exists(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive', ['test-command']);

        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('✅ Directive created successfully!', $response->getOutput());

        // Vérifier que le dossier a été créé
        $this->assertDirectoryExists($this->directiveTempDir . '/app/Directives');

        // Vérifier que le fichier a été créé
        $expectedPath = $this->directiveTempDir . '/app/Directives/TestCommandDirective.php';
        $this->assertFileExists($expectedPath);
    }

    public function test_execute_creates_file_in_subdirectory(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive', ['user/domain/hello-directive']);

        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('✅ Directive created successfully!', $response->getOutput());

        // Vérifier le dossier et le fichier
        $expectedPath = $this->directiveTempDir . '/app/Directives/User/Domain/HelloDirective.php';
        $this->assertFileExists($expectedPath);
        $this->assertDirectoryExists($this->directiveTempDir . '/app/Directives/User/Domain');

        // Vérifier le contenu
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Directives\\User\\Domain;', $content);
        $this->assertStringContainsString('class HelloDirective', $content);
        $this->assertStringContainsString("return 'hello-directive'", $content);
    }

    public function test_execute_adds_directive_suffix_automatically(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive', ['hello']);

        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('✅ Directive created successfully!', $response->getOutput());

        $expectedPath = $this->directiveTempDir . '/app/Directives/HelloDirective.php';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class HelloDirective', $content);
        $this->assertStringNotContainsString('HelloDirectiveDirective', $content);
        $this->assertStringContainsString("return 'hello'", $content);
    }

    public function test_execute_does_not_double_directive_suffix(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive', ['hello-directive']);

        $this->assertSame(ExitCode::SUCCESS, $response->getExitCode());
        $this->assertStringContainsString('✅ Directive created successfully!', $response->getOutput());

        $expectedPath = $this->directiveTempDir . '/app/Directives/HelloDirective.php';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class HelloDirective', $content);
        $this->assertStringNotContainsString('HelloDirectiveDirective', $content);
        $this->assertStringContainsString("return 'hello-directive'", $content);
    }

    public function test_execute_rejects_invalid_directive_name(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        $response = $this->runDirective('make-directive', ['user@create']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Invalid directive name', $response->getOutput());
    }

    public function test_execute_shows_usage_examples_on_error(): void
    {
        $this->registerDirectiveClass(\AndyDefer\Directive\Directives\MakeDirective::class);

        // Pour voir le message d'usage, il faut que le nom soit valide mais qu'il y ait une autre erreur?
        // Ou on peut tester avec un nom invalide qui déclenche l'affichage des exemples
        $response = $this->runDirective('make-directive', ['invalid@name']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Invalid directive name', $response->getOutput());
        $this->assertStringContainsString('Valid examples:', $response->getOutput());
        $this->assertStringContainsString('user-create', $response->getOutput());
        $this->assertStringContainsString('clean-log', $response->getOutput());
        $this->assertStringContainsString('db-migrate-fresh', $response->getOutput());
        $this->assertStringContainsString('user/domain/hello-directive', $response->getOutput());
    }
}
