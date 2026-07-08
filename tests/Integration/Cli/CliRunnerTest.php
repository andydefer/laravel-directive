<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Integration\Cli;

use AndyDefer\Directive\Cli\CliRunner;
use AndyDefer\Directive\Tests\Helpers\TestHelper;
use AndyDefer\Directive\Tests\IntegrationTestCase;

final class CliRunnerTest extends IntegrationTestCase
{
    private static ?string $appRoot = null;

    private string $originalCwd;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$appRoot === null) {
            self::$appRoot = sys_get_temp_dir().'/laravel_app_'.uniqid();
            self::createRealisticLaravelStructure(self::$appRoot);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$appRoot !== null && is_dir(self::$appRoot)) {
            self::removeDirectory(self::$appRoot);
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd();
        chdir(self::$appRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        parent::tearDown();
    }

    private function runDirective(CliRunner $runner, array $argv): array
    {
        ob_start();
        $exitCode = $runner->run($argv);
        $output = ob_get_clean();

        return [
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }

    private function runDirectiveSilent(CliRunner $runner, array $argv): int
    {
        ob_start();
        $exitCode = $runner->run($argv);
        ob_end_clean();

        return $exitCode;
    }

    private static function createRealisticLaravelStructure(string $appRoot): void
    {
        foreach (TestHelper::getDirectories() as $dir) {
            if (! is_dir($appRoot.$dir)) {
                mkdir($appRoot.$dir, 0777, true);
            }
        }

        file_put_contents($appRoot.'/composer.json', TestHelper::createComposerJsonContent());
        file_put_contents($appRoot.'/vendor/autoload.php', TestHelper::createAutoloadContent());
        file_put_contents($appRoot.'/bootstrap/app.php', TestHelper::createBootstrapAppContent());
        file_put_contents($appRoot.'/config/app.php', TestHelper::createConfigAppContent());

        $directives = [
            'UserCreateDirective.php' => TestHelper::createUserCreateDirective(),
            'CacheClearDirective.php' => TestHelper::createCacheClearDirective(),
        ];

        foreach ($directives as $filename => $content) {
            file_put_contents($appRoot.'/app/Directives/'.$filename, $content);
            require_once $appRoot.'/app/Directives/'.$filename;
        }
    }

    private static function addDirective(string $filename, string $content): void
    {
        file_put_contents(self::$appRoot.'/app/Directives/'.$filename, $content);
        require_once self::$appRoot.'/app/Directives/'.$filename;
    }

    private static function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_runner_finds_app_directive(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'user-create', 'John^Doe', 'john@example.com']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('User John Doe (john@example.com) created', $result['output']);
    }

    public function test_runner_finds_app_directive_with_option(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'user-create', 'Jane^Doe', 'jane@example.com', '--admin']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('User Jane Doe (jane@example.com) created as admin', $result['output']);
    }

    public function test_runner_finds_another_app_directive(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'cache-clear']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Cache cleared', $result['output']);
    }

    public function test_runner_handles_force_option(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'cache-clear', '--force']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Cache cleared (forced)', $result['output']);
    }

    public function test_runner_displays_all_directives(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', '--list']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('user-create', $result['output']);
        $this->assertStringContainsString('cache-clear', $result['output']);
    }

    public function test_runner_returns_not_found_for_unknown(): void
    {
        $runner = new CliRunner($this->app);

        $exitCode = $this->runDirectiveSilent($runner, ['directive', 'unknown-command']);

        $this->assertSame(3, $exitCode);
    }

    public function test_runner_shows_help(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', '--help']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Available Directives', $result['output']);
    }

    public function test_runner_shows_version(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', '--version']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Laravel Directive', $result['output']);
    }

    public function test_runner_with_no_arguments_shows_help(): void
    {
        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Available Directives', $result['output']);
    }

    public function test_runner_finds_directive_with_alias(): void
    {
        $content = TestHelper::createAliasTestDirective();
        self::addDirective('AliasTestDirective.php', $content);

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'alias']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Alias works!', $result['output']);
    }

    public function test_runner_finds_echo_directive(): void
    {
        $content = TestHelper::createEchoDirective();
        self::addDirective('EchoDirective.php', $content);

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'echo', 'Hello^World']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Hello World', $result['output']);
    }

    public function test_runner_finds_greeting_directive(): void
    {
        $content = TestHelper::createGreetingDirective();
        self::addDirective('GreetingDirective.php', $content);

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'greeting', 'Alice']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Hello, Alice!', $result['output']);
    }

    public function test_runner_finds_calculator_directive(): void
    {
        $content = TestHelper::createCalculatorDirective();
        self::addDirective('CalculatorDirective.php', $content);

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'calculator', 'add', '10', '5']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('15', $result['output']);
    }

    public function test_runner_finds_calculator_directive_with_alias(): void
    {
        $content = TestHelper::createCalculatorDirective();
        self::addDirective('CalculatorDirective.php', $content);

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'calc', 'mul', '10', '5']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('50', $result['output']);
    }

    public function test_runner_finds_variadic_directive(): void
    {
        $content = TestHelper::createVariadicDirective();
        self::addDirective('VariadicDirective.php', $content);

        $runner = new CliRunner($this->app);

        $result = $this->runDirective($runner, ['directive', 'variadic', 'John', '[file1.txt, file2.txt]', '--verbose']);

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('Name: John', $result['output']);
        $this->assertStringContainsString('file1.txt', $result['output']);
        $this->assertStringContainsString('file2.txt', $result['output']);
        $this->assertStringContainsString('Verbose mode enabled', $result['output']);
    }
}
