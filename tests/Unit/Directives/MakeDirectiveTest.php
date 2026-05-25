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

        // Créer un stub temporaire PLUS COMPLET
        $stubPath = $this->tempDir . '/directive.stub';
        file_put_contents($stubPath, '<?php

declare(strict_types=1);

namespace App\Directives;

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
        $this->assertSame('Create a new directive class', $description);
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

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with('user-create')
            ->willReturn(new ValidationResultRecord(isValid: true));

        $this->namingService->expects($this->once())
            ->method('generateClassName')
            ->with('user-create')
            ->willReturn('UserCreateDirective');

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

        $this->namingService->expects($this->once())
            ->method('generateClassName')
            ->with('user-create')
            ->willReturn('UserCreateDirective');

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

        $this->namingService->expects($this->once())
            ->method('generateClassName')
            ->with('test-command')
            ->willReturn('TestCommandDirective');

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

    public function test_execute_shows_usage_examples_on_error(): void
    {
        $reflection = new \ReflectionClass(MakeDirective::class);
        $property = $reflection->getProperty('arguments');
        $property->setValue($this->directive, new ParameterCollection);

        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Directive name is required');

        // Permettre plusieurs appels à line
        $this->interaction->expects($this->exactly(4))
            ->method('line')
            ->with($this->logicalOr(
                $this->equalTo('Usage: directive make-directive <name>'),
                $this->equalTo('Example: directive make-directive user-create'),
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
