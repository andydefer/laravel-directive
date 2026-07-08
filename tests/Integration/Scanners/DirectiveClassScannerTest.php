<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Scanners;

use AndyDefer\Directive\Contracts\Scanners\DirectiveScannerInterface;
use AndyDefer\Directive\Tests\Helpers\TestHelper;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class DirectiveClassScannerTest extends IntegrationTestCase
{
    private DirectiveScannerInterface $scanner;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/scanner_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->scanner = $this->app->make(DirectiveScannerInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function createFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function debugDirectory(string $dir, string $prefix = ''): void
    {
        if (! is_dir($dir)) {

            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->debugDirectory($path, $prefix.'  ');
            } else {
            }
        }
    }

    public function test_scan_returns_empty_array_for_non_existent_directory(): void
    {
        $result = $this->scanner->scan('/non/existent/path');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_returns_empty_array_for_directory_without_php_files(): void
    {
        $emptyDir = $this->tempDir.'/empty';
        mkdir($emptyDir, 0777, true);

        $result = $this->scanner->scan($emptyDir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_finds_concrete_directive_class(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/UserCreateDirective.php', TestHelper::createUserCreateDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\UserCreateDirective', $result[0]);
    }

    public function test_scan_ignores_abstract_classes(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/AbstractTestDirective.php', TestHelper::createAbstractDirectiveContent());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_ignores_non_directive_classes(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/NonDirectiveClass.php', TestHelper::createNonDirectiveClassContent());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_finds_multiple_directives(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/UserCreateDirective.php', TestHelper::createUserCreateDirective());
        $this->createFile($dir.'/CacheClearDirective.php', TestHelper::createCacheClearDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('App\\Directives\\UserCreateDirective', $result);
        $this->assertContains('App\\Directives\\CacheClearDirective', $result);
    }

    public function test_scan_respects_max_depth(): void
    {
        $dir = $this->tempDir.'/App/Deep/Nested/Directives';
        $this->createFile($dir.'/DeepDirective.php', TestHelper::createDeepDirectiveContent());

        $this->debugDirectory($this->tempDir.'/App');

        // Debug du contenu du fichier
        $filePath = $this->tempDir.'/App/Deep/Nested/Directives/DeepDirective.php';

        // Depth 1 should not find it (depth 3 required)
        $result1 = $this->scanner->scan($this->tempDir.'/App', 1);
        $this->assertEmpty($result1);

        // Depth 3 should find it
        $result2 = $this->scanner->scan($this->tempDir.'/App', 3);
        $this->assertCount(1, $result2);
        $this->assertSame('App\\Deep\\Nested\\Directives\\DeepDirective', $result2[0]);
    }

    public function test_scan_handles_files_without_class(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/Helper.php', TestHelper::createHelperFileContent());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_handles_files_without_namespace(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/NoNamespaceDirective.php', TestHelper::createNoNamespaceDirectiveContent());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_finds_directives_with_aliases(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/AliasTestDirective.php', TestHelper::createAliasTestDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\AliasTestDirective', $result[0]);
    }

    public function test_scan_finds_directives_with_variadic_arguments(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/VariadicDirective.php', TestHelper::createVariadicDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\VariadicDirective', $result[0]);
    }

    public function test_scan_finds_directives_with_calculator(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/CalculatorDirective.php', TestHelper::createCalculatorDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\CalculatorDirective', $result[0]);
    }

    public function test_scan_finds_directives_with_echo(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/EchoDirective.php', TestHelper::createEchoDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\EchoDirective', $result[0]);
    }

    public function test_scan_finds_directives_with_greeting(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/GreetingDirective.php', TestHelper::createGreetingDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\GreetingDirective', $result[0]);
    }

    public function test_scan_finds_test_echo_directive(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/TestEchoDirective.php', TestHelper::createTestEchoDirective());

        $this->debugDirectory($this->tempDir);

        // Debug du contenu du fichier
        $filePath = $dir.'/TestEchoDirective.php';

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\TestEchoDirective', $result[0]);
    }

    public function test_scan_finds_test_greeting_directive(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/TestGreetingDirective.php', TestHelper::createTestGreetingDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\TestGreetingDirective', $result[0]);
    }

    public function test_scan_finds_test_directive(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/TestDirective.php', TestHelper::createTestDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\TestDirective', $result[0]);
    }

    public function test_scan_finds_test_concrete_directive(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/TestConcreteDirective.php', TestHelper::createTestConcreteDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\TestConcreteDirective', $result[0]);
    }

    public function test_scan_finds_test_call_directive(): void
    {
        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/TestCallDirective.php', TestHelper::createTestCallDirective());

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Directives\\TestCallDirective', $result[0]);
    }

    public function test_scan_handles_nested_directories(): void
    {
        $dir = $this->tempDir.'/App/Admin/Directives';
        $this->createFile($dir.'/AdminDirective.php', TestHelper::createAdminDirectiveContent());

        $result = $this->scanner->scan($this->tempDir.'/App', 3);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('App\\Admin\\Directives\\AdminDirective', $result[0]);
    }

    public function test_scan_handles_invalid_php_files_gracefully(): void
    {
        $invalidContent = '<?php this is not valid php code {';

        $dir = $this->tempDir.'/Directives';
        $this->createFile($dir.'/InvalidDirective.php', $invalidContent);

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scan_handles_empty_directory(): void
    {
        $dir = $this->tempDir.'/Directives';
        mkdir($dir, 0777, true);

        $this->debugDirectory($this->tempDir);

        $result = $this->scanner->scan($dir);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
