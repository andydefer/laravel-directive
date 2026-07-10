<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Helpers;

final class TestHelper
{
    public static function getDirectories(): array
    {
        return [
            '/app/Directives',
            '/bootstrap',
            '/config',
            '/vendor',
            '/vendor/composer',
        ];
    }

    public static function createComposerJsonContent(): string
    {
        return <<<'JSON'
{
    "name": "laravel-directive/test-app",
    "type": "project",
    "require": {
        "php": "^8.1",
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
JSON;
    }

    public static function createAutoloadContent(): string
    {
        return <<<'PHP'
<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
PHP;
    }

    public static function createBootstrapAppContent(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Facade;

$app = new Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->singleton(
    Kernel::class,
    Illuminate\Foundation\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Illuminate\Foundation\Console\Kernel::class
);

$app->singleton(
    ExceptionHandler::class,
    Handler::class
);

Facade::setFacadeApplication($app);

return $app;
PHP;
    }

    public static function createConfigAppContent(): string
    {
        return <<<'PHP'
<?php

return [
    'name' => 'Laravel Test Application',
    'env' => 'testing',
    'debug' => true,
    'url' => 'http://localhost',
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => 'base64:' . base64_encode(random_bytes(32)),
    'cipher' => 'AES-256-CBC',
    'providers' => [
        AndyDefer\Directive\DirectiveServiceProvider::class,
    ],
];
PHP;
    }

    public static function createAdminDirectiveContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Admin\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class AdminDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'admin';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
PHP;
    }

    public static function createDirective(string $className, string $signature, string $description, string $executeContent, array $aliases = []): string
    {
        $aliasCode = '';
        if (! empty($aliases)) {
            $aliasCode = sprintf(
                '    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from([%s]);
    }',
                implode(', ', array_map(fn ($a) => "'$a'", $aliases))
            );
        }

        return <<<PHP
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class {$className} extends AbstractDirective
{
    public function getSignature(): string
    {
        return '{$signature}';
    }

    public function getDescription(): string
    {
        return '{$description}';
    }

{$aliasCode}

    protected function execute(): ExitCode
    {
        {$executeContent}
    }
}
PHP;
    }

    public static function createUserCreateDirective(): string
    {
        return self::createDirective(
            className: 'UserCreateDirective',
            signature: 'user-create {name} {email} {--admin}',
            description: 'Create a new user',
            executeContent: <<<'PHP'
$name = $this->argument('name');
$email = $this->argument('email');
$isAdmin = $this->flag('admin');
$message = "User {$name} ({$email}) created";
if ($isAdmin) {
    $message .= " as admin";
}
$this->info($message);
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createCacheClearDirective(): string
    {
        return self::createDirective(
            className: 'CacheClearDirective',
            signature: 'cache-clear {--force}',
            description: 'Clear application cache',
            executeContent: <<<'PHP'
$force = $this->flag('force');
$message = "Cache cleared";
if ($force) {
    $message .= " (forced)";
}
$this->info($message);
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createAliasTestDirective(): string
    {
        return self::createDirective(
            className: 'AliasTestDirective',
            signature: 'original-name',
            description: 'Directive with alias',
            aliases: ['alias'],
            executeContent: <<<'PHP'
$this->info('Alias works!');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createEchoDirective(): string
    {
        return self::createDirective(
            className: 'EchoDirective',
            signature: 'echo {message=Hello World} {extra=?}',
            description: 'Echo a message',
            aliases: ['echo'],
            executeContent: <<<'PHP'
$message = $this->argument('message');
$extra = $this->argument('extra');
$output = $message;
if ($extra) {
    $output .= " " . $extra;
}
$this->line($output);
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createGreetingDirective(): string
    {
        return self::createDirective(
            className: 'GreetingDirective',
            signature: 'greeting {name=World}',
            description: 'Say hello to someone',
            executeContent: <<<'PHP'
$name = $this->argument('name');
$this->info("Hello, {$name}!");
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createCalculatorDirective(): string
    {
        return self::createDirective(
            className: 'CalculatorDirective',
            signature: 'calculator {operation} {a} {b=?}',
            description: 'Test calculator directive for arithmetic operations',
            aliases: ['calc', 'math'],
            executeContent: <<<'PHP'
$operation = $this->argument('operation');
$a = (int) $this->argument('a');
$b = (int) $this->argument('b', 0);

switch ($operation) {
    case 'add':
        $result = $a + $b;
        break;
    case 'sub':
        $result = $a - $b;
        break;
    case 'mul':
        $result = $a * $b;
        break;
    case 'div':
        if ($b === 0) {
            $this->error('Division by zero');
            return ExitCode::FAILURE;
        }
        $result = $a / $b;
        break;
    default:
        $this->error("Unknown operation: {$operation}");
        return ExitCode::INVALID_ARGUMENT;
}

$this->info((string) $result);
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createVariadicDirective(): string
    {
        return self::createDirective(
            className: 'VariadicDirective',
            signature: 'variadic {name} {files*} {--verbose}',
            description: 'Test variadic arguments',
            executeContent: <<<'PHP'
$name = $this->argument('name');
$this->info("Name: {$name}");
$files = $this->getVariadicArguments();
foreach ($files as $file) {
    $this->line("- {$file}");
}
if ($this->flag('verbose')) {
    $this->info('Verbose mode enabled');
}
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createHelperFileContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Directives;

// No class defined here, just some functions
function test_helper(): void {}
PHP;
    }

    public static function createNoNamespaceDirectiveContent(): string
    {
        return <<<'PHP'
<?php

final class NoNamespaceDirective extends AndyDefer\Directive\AbstractDirective
{
    public function getSignature(): string
    {
        return 'no-namespace';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
PHP;
    }

    public static function createAbstractDirectiveContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

abstract class AbstractTestDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'abstract-test';
    }

    abstract public function execute(): ExitCode;
}
PHP;
    }

    public static function createNonDirectiveClassContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Directives;

class NonDirectiveClass
{
    public function test(): void
    {
        // Not a directive
    }
}
PHP;
    }

    public static function createDeepDirectiveContent(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Deep\Nested\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class DeepDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deep';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
PHP;
    }

    public static function createBeforeAfterDirective(): string
    {
        return self::createDirective(
            className: 'BeforeAfterDirective',
            signature: 'before-after',
            description: 'Test before and after hooks',
            executeContent: <<<'PHP'
$this->info('Execute hook executed');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createBeforeFailingDirective(): string
    {
        return self::createDirective(
            className: 'BeforeFailingDirective',
            signature: 'before-failing',
            description: 'Test failing before hook',
            executeContent: <<<'PHP'
$this->info('Execute hook should not be reached');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createAfterFailingDirective(): string
    {
        return self::createDirective(
            className: 'AfterFailingDirective',
            signature: 'after-failing',
            description: 'Test failing after hook',
            executeContent: <<<'PHP'
$this->info('Execute hook executed');
throw new \RuntimeException('After hook failure');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createNestedBeforeAfterDirective(): string
    {
        return self::createDirective(
            className: 'NestedBeforeAfterDirective',
            signature: 'nested-before-after',
            description: 'Test nested before and after hooks',
            executeContent: <<<'PHP'
$this->info('Parent execute hook executed');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createParentDirective(): string
    {
        return self::createDirective(
            className: 'ParentDirective',
            signature: 'parent',
            description: 'Parent directive that calls children',
            executeContent: <<<'PHP'
$this->info('Parent directive started');
$this->call('calculator add 10 5');
$this->call('calculator pow 2 3');
$this->call('greeting John');
$this->info('Parent directive finished');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createCircularDirective(): string
    {
        return self::createDirective(
            className: 'CircularDirective',
            signature: 'circular',
            description: 'Directive with circular call',
            executeContent: <<<'PHP'
$this->info('Circular directive started');
$this->call('circular');
$this->info('Circular directive finished');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createTestCallDirective(): string
    {
        return self::createDirective(
            className: 'TestCallDirective',
            signature: 'test:call',
            description: 'Test directive with calls',
            executeContent: <<<'PHP'
$this->info('Test call directive started');
$this->call('greeting Alice');
$this->call('calculator add 5 3');
$this->info('Test call directive finished');
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createTestConcreteDirective(): string
    {
        return self::createDirective(
            className: 'TestConcreteDirective',
            signature: 'test:concrete {name} {email} {format=zip} {files*} {--force} {--verbose}',
            description: 'Test concrete directive for AbstractDirective tests',
            executeContent: <<<'PHP'
$name = $this->argument('name');
$email = $this->argument('email');
$this->info("Name: {$name}, Email: {$email}");
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createTestEchoDirective(): string
    {
        return self::createDirective(
            className: 'TestEchoDirective',
            signature: 'test:echo {message=?} {extra=?}',
            description: 'Test echo directive',
            aliases: ['echo'],
            executeContent: <<<'PHP'
$message = $this->argument('message') ?? 'Hello World';
$this->line($message);
if ($this->argument('extra')) {
    $this->line($this->argument('extra'));
}
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createTestGreetingDirective(): string
    {
        return self::createDirective(
            className: 'TestGreetingDirective',
            signature: 'greeting {name=?}',
            description: 'Test greeting directive',
            executeContent: <<<'PHP'
$name = $this->argument('name') ?? 'World';
$this->info("Hello, {$name}!");
return ExitCode::SUCCESS;
PHP
        );
    }

    public static function createTestDirective(): string
    {
        return self::createDirective(
            className: 'TestDirective',
            signature: 'test:directive {name} {email} {format=zip} {files*} {--force} {--verbose}',
            description: 'Test directive',
            executeContent: <<<'PHP'
$name = $this->argument('name');
$email = $this->argument('email');
$this->info("Name: {$name}, Email: {$email}");
return ExitCode::SUCCESS;
PHP
        );
    }
}
