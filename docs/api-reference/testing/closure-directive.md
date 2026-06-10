# ClosureDirective - Référence Technique

## Description

Directive de test qui exécute une closure au lieu d'une classe complète.

## Hiérarchie

```
AbstractDirective
    └── ClosureDirective
```

## Rôle principal

Permet de créer rapidement des directives pour les tests sans écrire de classes dédiées. La closure reçoit l'instance de la directive comme premier paramètre, donnant accès aux méthodes d'interaction (`line()`, `info()`, `error()`), aux arguments et aux options.

## Installation

```bash
composer require --dev andydefer/php-records
```

Cette classe est destinée uniquement à l'environnement de test.

```php
use AndyDefer\Directive\Testing\ClosureDirective;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;

$context = new DirectiveContext(
    laravelBootstrapper: $laravelBootstrapperContext,
    blueprint: new DirectiveBlueprintRecord(ClosureDirective::class, 'test {name}', 'Test directive'),
    aliases: new StringTypedCollection,
    shouldBootLaravel: false,
);

$directive = new ClosureDirective(
    context: $context,
    interaction: $interaction,
    signature: 'test {name}',
    execute: fn($d) => $d->line('Hello ' . $d->argument('name'))
);
```

## API / Méthodes publiques

### `__construct(DirectiveContext $context, DirectiveInteractionService $interaction, string $signature, \Closure $execute): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `DirectiveContext` | Contexte de la directive contenant blueprint, aliases, configuration |
| `$interaction` | `DirectiveInteractionService` | Service d'interaction pour les sorties |
| `$signature` | `string` | Signature de la directive |
| `$execute` | `\Closure(ClosureDirective): ExitCode` | Logique d'exécution sous forme de closure |

**Exemple :**
```php
$directive = new ClosureDirective(
    context: $context,
    interaction: $interaction,
    signature: 'greet {name}',
    execute: function ($d) {
        $d->line('Hello ' . $d->argument('name'));
        return ExitCode::SUCCESS;
    }
);
```

### `getSignature(): string`

**Retourne :** `string` - Signature de la directive

**Exemple :**
```php
$signature = $directive->getSignature(); // 'greet {name}'
```

### `getDescription(): string`

**Retourne :** `string` - Description par défaut de la directive de test

**Exemple :**
```php
$description = $directive->getDescription(); // 'Test directive created from closure'
```

### `execute(): ExitCode`

**Retourne :** `ExitCode` - Code de sortie retourné par la closure

Exécute la closure et retourne son résultat.

**Exemple :**
```php
$exitCode = $directive->execute(); // ExitCode::SUCCESS
```

## Cas d'utilisation

### Cas 1 : Directive simple avec affichage

```php
public function test_greeting_directive(): void
{
    $context = new DirectiveContext(
        laravelBootstrapper: $this->laravelBootstrapperContext,
        blueprint: new DirectiveBlueprintRecord(ClosureDirective::class, 'greet {name}', 'Greeting directive'),
        aliases: new StringTypedCollection,
        shouldBootLaravel: false,
    );
    
    $directive = new ClosureDirective(
        context: $context,
        interaction: $this->interaction,
        signature: 'greet {name}',
        execute: function ($d) {
            $name = $d->argument('name');
            $d->line("Hello, {$name}!");
            return ExitCode::SUCCESS;
        }
    );
    
    $this->registerDirective($directive);
    $response = $this->runDirective('greet', ['John']);
    
    $this->assertStringContainsString('Hello, John!', $response->output);
}
```

### Cas 2 : Directive avec options

```php
public function test_verbose_directive(): void
{
    $context = new DirectiveContext(
        laravelBootstrapper: $this->laravelBootstrapperContext,
        blueprint: new DirectiveBlueprintRecord(ClosureDirective::class, 'process {--verbose}', 'Process directive'),
        aliases: new StringTypedCollection,
        shouldBootLaravel: false,
    );
    
    $directive = new ClosureDirective(
        context: $context,
        interaction: $this->interaction,
        signature: 'process {--verbose}',
        execute: function ($d) {
            if ($d->hasOption('verbose')) {
                $d->info('Processing in verbose mode');
            }
            return ExitCode::SUCCESS;
        }
    );
    
    $response = $this->runDirective('process', ['--verbose']);
    
    $this->assertStringContainsString('verbose mode', $response->output);
}
```

### Cas 3 : Directive avec logique conditionnelle

```php
public function test_validation_directive(): void
{
    $context = new DirectiveContext(
        laravelBootstrapper: $this->laravelBootstrapperContext,
        blueprint: new DirectiveBlueprintRecord(ClosureDirective::class, 'validate {age}', 'Validation directive'),
        aliases: new StringTypedCollection,
        shouldBootLaravel: false,
    );
    
    $directive = new ClosureDirective(
        context: $context,
        interaction: $this->interaction,
        signature: 'validate {age}',
        execute: function ($d) {
            $age = (int) $d->argument('age');
            
            if ($age < 18) {
                $d->error('Age must be at least 18');
                return ExitCode::INVALID_ARGUMENT;
            }
            
            $d->line('Valid age');
            return ExitCode::SUCCESS;
        }
    );
    
    $response = $this->runDirective('validate', ['16']);
    
    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    $this->assertStringContainsString('must be at least 18', $response->output);
}
```

### Cas 4 : Test avec environment isolé

```php
public function test_isolated_environment(): void
{
    // Utilisation avec DirectiveTestingService
    $service = new DirectiveTestingService(null, $this->context);
    
    $directive = $service->createTestDirective('isolated-test', function ($d) {
        $d->line('Running in isolated environment');
        return ExitCode::SUCCESS;
    });
    
    $response = $service->runDirective('isolated-test');
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('isolated environment', $response->output);
}
```

### Cas 5 : Tests rapides sans créer de classes

```php
public function test_multiple_scenarios(): void
{
    $service = new DirectiveTestingService(null, $this->context);
    
    $scenarios = [
        'addition' => fn($d) => $d->line('1 + 1 = 2'),
        'subtraction' => fn($d) => $d->line('5 - 3 = 2'),
        'multiplication' => fn($d) => $d->line('4 * 4 = 16'),
    ];
    
    foreach ($scenarios as $name => $logic) {
        $service->createTestDirective($name, $logic);
        $response = $service->runDirective($name);
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }
}
```

## Flux d'exécution

```
1. Création du DirectiveContext
   ├── LaravelBootstrapperContext
   ├── DirectiveBlueprintRecord
   ├── Aliases (StringTypedCollection)
   └── shouldBootLaravel (bool)

2. Instanciation de ClosureDirective
   ├── context → stocké
   ├── interaction → stocké
   ├── signature → stocké
   └── execute → stocké (closure)

3. Exécution (execute())
   └── Appel de la closure avec $this

4. Accès aux méthodes dans la closure
   ├── argument() / hasArgument()
   ├── option() / hasOption()
   ├── getVariadicArguments()
   ├── line() / info() / error() / warn()
   ├── table()
   └── ask() / confirm()
```

## Gestion des erreurs

| Situation | Code retour | Comportement |
|-----------|-------------|--------------|
| Closure retourne ExitCode::SUCCESS | `ExitCode::SUCCESS` | Exécution normale |
| Closure retourne ExitCode::FAILURE | `ExitCode::FAILURE` | Échec signalé |
| Closure retourne ExitCode::INVALID_ARGUMENT | `ExitCode::INVALID_ARGUMENT` | Argument invalide |
| Exception dans la closure | Non catchée | Remonte à l'appelant |

## Intégration

### Avec DirectiveTestingService (recommandé)

```php
use AndyDefer\Directive\Services\DirectiveTestingService;

final class MyTest extends TestCase
{
    private DirectiveTestingService $service;
    
    public function test_closure_directive(): void
    {
        $this->service->createTestDirective('ping', function ($d) {
            $d->line('Pong!');
            return ExitCode::SUCCESS;
        });
        
        $response = $this->service->runDirective('ping');
        
        $this->assertStringContainsString('Pong!', $response->output);
    }
}
```

### Avec createTestDirective() helper (déprécié)

> ⚠️ `InteractsWithDirectives` est déprécié. Utilisez `DirectiveTestingService` à la place.

```php
// ❌ Déprécié
$this->createTestDirective('test', function ($d) {
    $d->line('Hello');
    return ExitCode::SUCCESS;
});

// ✅ Recommandé
$service->createTestDirective('test', function ($d) {
    $d->line('Hello');
    return ExitCode::SUCCESS;
});
```

## Performance

- **Complexité :** O(1) - simple appel de closure
- **Mémoire :** Une instance de closure par directive
- **Tests :** Idéal pour les tests unitaires rapides

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;

final class ClosureDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;
    private DirectiveTestingContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new DirectiveTestingConfig;
        $this->context = new DirectiveTestingContext(false);
        $this->context->setConfig($config);
        $this->service = new DirectiveTestingService($this->context);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_closure_directive_calculator(): void
    {
        // Création d'une directive calculatrice avec closure
        $this->service->createTestDirective('calc {a} {b} {--operation=add}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $operation = $d->option('operation') ?? 'add';
            
            $result = match($operation) {
                'add' => $a + $b,
                'sub' => $a - $b,
                'mul' => $a * $b,
                'div' => $b !== 0 ? $a / $b : throw new \Exception('Division by zero'),
                default => throw new \Exception("Unknown operation: {$operation}"),
            };
            
            $d->line("Result: {$result}");
            return ExitCode::SUCCESS;
        });
        
        // Test addition
        $response = $this->service->runDirective('calc', ['10', '5', '--operation=add']);
        $this->assertStringContainsString('Result: 15', $response->output);
        
        // Test multiplication
        $response = $this->service->runDirective('calc', ['6', '7', '--operation=mul']);
        $this->assertStringContainsString('Result: 42', $response->output);
        
        // Test division par zéro
        $response = $this->service->runDirective('calc', ['10', '0', '--operation=div']);
        $this->assertStringContainsString('Division by zero', $response->output);
    }
    
    public function test_multiple_scenarios(): void
    {
        $scenarios = [
            'addition' => fn($d) => $d->line('1 + 1 = 2'),
            'subtraction' => fn($d) => $d->line('5 - 3 = 2'),
            'multiplication' => fn($d) => $d->line('4 * 4 = 16'),
        ];
        
        foreach ($scenarios as $name => $logic) {
            $this->service->createTestDirective($name, $logic);
            $response = $this->service->runDirective($name);
            
            $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        }
    }
    
    public function test_context_tracking(): void
    {
        $this->service->createTestDirective('track-me', function ($d) {
            $d->line('Executed');
            return ExitCode::SUCCESS;
        });
        
        $response = $this->service->runDirective('track-me');
        
        // ✅ Traçabilité : on peut inspecter le contexte
        $this->assertTrue($this->context->hasBeenExecuted('track-me'));
        $this->assertEquals(1, $this->context->getExecutedDirectivesCount());
        $this->assertGreaterThan(0, $this->context->getStepsExecutedCount());
    }
}
```
---