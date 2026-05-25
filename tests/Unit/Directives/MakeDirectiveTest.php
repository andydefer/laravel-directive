<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Directives\MakeDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\DirectiveNamingService;
use AndyDefer\Directive\Services\SignatureValidationService;
use AndyDefer\Directive\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class MakeDirectiveTest extends UnitTestCase
{
    private DirectiveInteractionService&MockObject $interaction;
    private SignatureValidationService&MockObject $signatureValidator;
    private DirectiveNamingService&MockObject $namingService;
    private MakeDirective $directive;
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->signatureValidator = $this->createMock(SignatureValidationService::class);
        $this->namingService = $this->createMock(DirectiveNamingService::class);

        // Sauvegarder le répertoire courant
        $this->originalCwd = getcwd();

        // Créer un répertoire temporaire pour les stubs
        $this->tempDir = sys_get_temp_dir() . '/make_directive_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Créer un stub temporaire avec namespace
        $stubPath = $this->tempDir . '/directive.stub';
        file_put_contents($stubPath, '<?php

declare(strict_types=1);

namespace {{namespace}};

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class {{class}} extends AbstractDirective
{
    public function getSignature(): string
    {
        return \'{{signature}}\';
    }

    public function getDescription(): string
    {
        return \'{{description}}\';
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection();
    }

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    public function execute(): ExitCode
    {
        $this->info(\'Directive executed successfully!\');
        return ExitCode::SUCCESS;
    }
}');

        $this->directive = new MakeDirective(
            interaction: $this->interaction,
            signatureValidator: $this->signatureValidator,
            namingService: $this->namingService,
            stubPath: $stubPath,
        );

        // Changer vers le répertoire temporaire
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Restaurer le répertoire courant
        chdir($this->originalCwd);

        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_get_signature_returns_make_directive(): void
    {
        $signature = $this->directive->getSignature();
        $this->assertSame('make-directive {name}', $signature);
    }

    public function test_get_description_returns_description(): void
    {
        $description = $this->directive->getDescription();
        $this->assertSame(
            'Create a new directive class (supports subdirectories like "user/hello-directive")',
            $description
        );
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $aliases = $this->directive->getAliases();
        $this->assertTrue($aliases->contains('create-directive'));
        $this->assertTrue($aliases->contains('make-cmd'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_execute_returns_error_when_name_missing(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');
        $property->setValue($this->directive, new ParameterCollection);

        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Directive name is required');

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_validates_name_before_creation(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'user-create'));
        $property->setValue($this->directive, $arguments);

        // La validation ne devrait être appelée que sur le nom de base
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info')
            ->with($this->stringContains('✅ Directive created successfully!'));

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::SUCCESS, $result);

        // Vérifier que le fichier a été créé
        $expectedPath = $this->tempDir . '/app/Directives/UserCreateDirective.php';
        $this->assertFileExists($expectedPath);
    }

    public function test_execute_returns_error_when_name_invalid(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'user create'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user create')
            ->willReturn(new ValidationResultRecord(
                isValid: false,
                error: 'Invalid directive name: "user create"'
            ));

        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Invalid directive name: "user create"');

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    public function test_execute_creates_file_with_correct_replacements(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'user-create'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);

        // Vérifier le contenu du fichier
        $expectedPath = $this->tempDir . '/app/Directives/UserCreateDirective.php';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class UserCreateDirective', $content);
        $this->assertStringContainsString("return 'user-create'", $content);
        $this->assertStringContainsString('namespace App\\Directives;', $content);
    }

    public function test_execute_creates_directory_when_not_exists(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'test-command'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('test-command')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);

        // Vérifier que le dossier a été créé
        $this->assertDirectoryExists($this->tempDir . '/app/Directives');

        // Vérifier que le fichier a été créé
        $expectedPath = $this->tempDir . '/app/Directives/TestCommandDirective.php';
        $this->assertFileExists($expectedPath);
    }

    public function test_execute_creates_file_in_subdirectory(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'user/domain/hello-directive'));
        $property->setValue($this->directive, $arguments);

        // La validation doit être appelée sur le nom de base seulement
        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('hello-directive')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);

        // Vérifier le dossier et le fichier
        $expectedPath = $this->tempDir . '/app/Directives/User/Domain/HelloDirective.php';
        $this->assertFileExists($expectedPath);
        $this->assertDirectoryExists($this->tempDir . '/app/Directives/User/Domain');

        // Vérifier le contenu
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Directives\\User\\Domain;', $content);
        $this->assertStringContainsString('class HelloDirective', $content);
        // CORRECTION : La signature doit être "hello-directive" et non "hello"
        $this->assertStringContainsString("return 'hello-directive'", $content);
    }

    public function test_execute_adds_directive_suffix_automatically(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'hello'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('hello')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);

        $expectedPath = $this->tempDir . '/app/Directives/HelloDirective.php';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class HelloDirective', $content);
        $this->assertStringNotContainsString('HelloDirectiveDirective', $content);
        // CORRECTION : La signature doit être "hello" (pas de suffixe "directive")
        $this->assertStringContainsString("return 'hello'", $content);
    }

    public function test_execute_does_not_double_directive_suffix(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'hello-directive'));
        $property->setValue($this->directive, $arguments);

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('hello-directive')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);

        $expectedPath = $this->tempDir . '/app/Directives/HelloDirective.php';
        $this->assertFileExists($expectedPath);

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class HelloDirective', $content);
        $this->assertStringNotContainsString('HelloDirectiveDirective', $content);
        // CORRECTION : La signature doit être "hello-directive" et non "hello"
        $this->assertStringContainsString("return 'hello-directive'", $content);
    }

    public function test_execute_shows_usage_examples_on_error(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');
        $property->setValue($this->directive, new ParameterCollection);

        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Directive name is required');

        $this->interaction->expects($this->exactly(7))
            ->method('line')
            ->with($this->logicalOr(
                $this->equalTo('Usage: directive make-directive <name>'),
                $this->equalTo('Examples:'),
                $this->equalTo('  • directive make-directive user-create'),
                $this->equalTo('  • directive make-directive user/domain/hello-directive'),
                $this->equalTo('  • directive make-directive admin/user-list'),
                $this->equalTo(''),
                $this->equalTo('Use only letters, numbers, and hyphens. Must start with a letter.')
            ));

        $result = $this->directive->execute();
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
