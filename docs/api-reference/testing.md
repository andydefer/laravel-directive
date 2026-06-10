# Tester vos directives - Guide complet

## Introduction

Le package `laravel-directive` fournit un environnement de test isolé et complet pour valider vos directives. Ce guide explique comment tester efficacement vos directives, avec ou sans Laravel, en utilisant `DirectiveTestingService`.

---

## Concepts fondamentaux

### Le contexte (`DirectiveContext`)

Le `DirectiveContext` est le conteneur central qui stocke toutes les informations d'une directive :

- **Blueprint** : Métadonnées (signature, description, classe)
- **Arguments** : Valeurs des paramètres positionnels
- **Options** : Valeurs des options (flags ou avec valeurs)
- **Arguments variadiques** : Collection des arguments restants
- **État Laravel** : Disponibilité du framework

```php
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

$context = new DirectiveContext(
    laravelBootstrapper: new LaravelBootstrapperContext(),
    blueprint: new DirectiveBlueprintRecord(MyDirective::class, 'my-cmd', 'Description'),
    aliases: new StringTypedCollection(),
    shouldBootLaravel: false
);
```

### Le bootstrapper Laravel (`LaravelBootstrapperContext`)

Ce composant gère le chargement optionnel de Laravel :

- Quand `shouldBootLaravel` est `true`, la directive peut accéder à Eloquent, au cache, etc.
- Le bootstrap se fait **une seule fois** par exécution, même si plusieurs directives le demandent

### Le service de test (`DirectiveTestingService`)

C'est le point d'entrée principal pour les tests. Il :

- Crée un environnement isolé (répertoire temporaire)
- Initialise tous les services nécessaires
- Gère l'enregistrement et l'exécution des directives
- Nettoie automatiquement après les tests

---

## Installation des dépendances de test

```bash
composer require --dev phpunit/phpunit orchestra/testbench
```

---

## Structure d'un test basique

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;
use App\Directives\HelloDirective;

final class HelloDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;
    private DirectiveTestingContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        // Configuration
        $config = new DirectiveTestingConfig();
        $this->context = new DirectiveTestingContext(false); // false = pas de Laravel
        $this->context->setConfig($config);
        
        // Service de test
        $this->service = new DirectiveTestingService($this->context);
    }

    protected function tearDown(): void
    {
        $this->service->destroy(); // Nettoie les fichiers temporaires
        parent::tearDown();
    }

    public function test_directive_returns_success(): void
    {
        // Créer la directive avec son contexte
        $context = new DirectiveContext(
            laravelBootstrapper: new LaravelBootstrapperContext(),
            blueprint: new DirectiveBlueprintRecord(
                HelloDirective::class,
                'hello {name}',
                'Say hello'
            ),
            aliases: new StringTypedCollection(),
            shouldBootLaravel: false
        );
        
        $directive = new HelloDirective($context, $this->service->getInteraction());
        $this->service->registerDirective($directive);
        
        $response = $this->service->runDirective('hello', ['John']);
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }
}
```

---

## Tester sans Laravel (mode isolé)

C'est le mode par défaut. Idéal pour les directives qui n'ont pas besoin de base de données ou des fonctionnalités Laravel.

```php
protected function setUp(): void
{
    parent::setUp();
    
    $config = new DirectiveTestingConfig();
    $this->context = new DirectiveTestingContext(false); // ← false = pas de Laravel
    $this->context->setConfig($config);
    $this->service = new DirectiveTestingService($this->context);
}
```

**Quand l'utiliser ?**
- Directives qui manipulent des fichiers
- Directives qui font des calculs
- Directives qui appellent des API externes
- Directives qui utilisent uniquement la logique métier

---

## Tester avec Laravel

Activez Laravel quand votre directive utilise Eloquent, le cache, les sessions, etc.

```php
protected function setUp(): void
{
    parent::setUp();
    
    $config = new DirectiveTestingConfig();
    $this->context = new DirectiveTestingContext(true); // ← true = active Laravel
    $this->context->setConfig($config);
    $this->service = new DirectiveTestingService($this->context);
}
```

### Directive avec base de données

```php
<?php
// app/Directives/UserStatsDirective.php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use App\Models\User;

final class UserStatsDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-stats';
    }

    public function shouldBootLaravel(): bool
    {
        return true; // Nécessite Eloquent
    }

    public function execute(): ExitCode
    {
        if (!$this->hasLaravel()) {
            return ExitCode::FAILURE;
        }
        
        $count = User::count();
        $this->info("Total users: {$count}");
        
        return ExitCode::SUCCESS;
    }
}
```

### Test correspondant

```php
<?php
namespace Tests\Unit\Directives;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use PHPUnit\Framework\TestCase;
use App\Directives\UserStatsDirective;
use App\Models\User;

final class UserStatsDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Activer Laravel
        $config = new DirectiveTestingConfig();
        $context = new DirectiveTestingContext(true);
        $context->setConfig($config);
        $this->service = new DirectiveTestingService($context);
        
        // Créer des données de test
        User::create(['name' => 'John', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        User::truncate();
        parent::tearDown();
    }

    public function test_user_stats_display_count(): void
    {
        $context = new DirectiveContext(
            laravelBootstrapper: new LaravelBootstrapperContext(),
            blueprint: new DirectiveBlueprintRecord(UserStatsDirective::class, 'user-stats', 'User stats'),
            aliases: new StringTypedCollection(),
            shouldBootLaravel: true
        );
        
        $directive = new UserStatsDirective($context, $this->service->getInteraction());
        $this->service->registerDirective($directive);
        
        $response = $this->service->runDirective('user-stats');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Total users: 2', $response->output);
    }
}
```

---

## Créer des directives temporaires avec `createTestDirective()`

Pour les tests rapides ou pour mocker une dépendance, utilisez `createTestDirective()`.

```php
public function test_temporary_directive(): void
{
    $executed = false;
    
    $this->service->createTestDirective('test-calc {a} {b}', function ($d) use (&$executed) {
        $a = (int) $d->argument('a');
        $b = (int) $d->argument('b');
        $result = $a + $b;
        $d->line("Result: {$result}");
        $executed = true;
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('test-calc', ['10', '20']);
    
    $this->assertTrue($executed);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Result: 30', $response->output);
}
```

**Avantages :**
- Pas besoin de créer une classe dédiée
- La closure reçoit la directive (`$d`) avec accès à toutes ses méthodes
- Idéal pour tester des cas spécifiques ou des intégrations

---

## Tester avec des arguments variadiques

```php
public function test_directive_with_variadic_arguments(): void
{
    $this->service->createTestDirective('process {files*}', function ($d) {
        $files = $d->getVariadicArguments();
        $count = $files->count();
        $d->line("Processing {$count} files");
        
        foreach ($files as $file) {
            $d->line("  - {$file}");
        }
        
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('process', [
        '[', 'file1.txt,', 'file2.txt,', 'file3.txt', ']'
    ]);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Processing 3 files', $response->output);
    $this->assertStringContainsString('file1.txt', $response->output);
}
```

---

## Tester les erreurs et les codes de sortie

```php
public function test_directive_returns_invalid_argument_when_name_missing(): void
{
    $this->service->createTestDirective('greet {name}', function ($d) {
        $name = $d->argument('name');
        if ($name === null) {
            $d->error('Name is required');
            return ExitCode::INVALID_ARGUMENT;
        }
        $d->line("Hello, {$name}!");
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('greet', []);
    
    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    $this->assertStringContainsString('Name is required', $response->output);
}

public function test_directive_handles_exception(): void
{
    $this->service->createTestDirective('risky', function ($d) {
        throw new \RuntimeException('Something went wrong');
    });
    
    $response = $this->service->runDirective('risky');
    
    $this->assertSame(ExitCode::FAILURE, $response->exitCode);
    $this->assertStringContainsString('went wrong', $response->output);
}
```

---

## Tester l'interaction utilisateur (mock)

Les méthodes comme `ask()`, `confirm()`, `line()`, `info()` peuvent être mockées.

```php
public function test_directive_asks_for_user_input(): void
{
    // Créer un mock de l'interaction
    $interaction = $this->createMock(DirectiveInteractionService::class);
    $interaction->expects($this->once())
        ->method('ask')
        ->with('What is your name?')
        ->willReturn('John');
    
    $interaction->expects($this->once())
        ->method('line')
        ->with('Hello, John!');
    
    $context = new DirectiveContext(
        laravelBootstrapper: new LaravelBootstrapperContext(),
        blueprint: new DirectiveBlueprintRecord(HelloDirective::class, 'hello', 'Say hello'),
        aliases: new StringTypedCollection(),
        shouldBootLaravel: false
    );
    
    $directive = new HelloDirective($context, $interaction);
    
    // Exécuter...
}
```

---

## Accéder au contexte de test

Le `DirectiveTestingContext` expose l'état complet de l'exécution :

```php
public function test_context_tracks_execution(): void
{
    $this->service->createTestDirective('track-me', function ($d) {
        $d->line('Executed');
        return ExitCode::SUCCESS;
    });
    
    $this->service->runDirective('track-me');
    
    // Vérifier que la directive a été exécutée
    $this->assertTrue($this->context->hasBeenExecuted('track-me'));
    $this->assertEquals(1, $this->context->getExecutedDirectivesCount());
    
    // Vérifier les étapes d'initialisation
    $this->assertTrue($this->context->hasStepResult(TestingStep::BUILD_CONTAINER));
    $this->assertTrue($this->context->hasStepResult(TestingStep::CREATE_TEMP_DIRECTORY));
}
```

---

## Nettoyage après les tests

Toujours appeler `destroy()` dans `tearDown()` :

```php
protected function tearDown(): void
{
    $this->service->destroy(); // Nettoie le répertoire temporaire
    parent::tearDown();
}
```

---

## Tableau récapitulatif

| Situation | `DirectiveTestingContext` | `shouldBootLaravel` |
|-----------|--------------------------|---------------------|
| Directive sans Laravel | `false` | `false` |
| Directive avec Eloquent | `true` | `true` |
| Directive avec Cache | `true` | `true` |
| Directive avec fichiers | `false` | `false` |

---

## Bonnes pratiques

1. **Un test par cas** : Testez une seule chose à la fois
2. **Nettoyez toujours** : Appelez `destroy()` dans `tearDown()`
3. **Utilisez `createTestDirective()`** pour les tests rapides et temporaires
4. **Mockez l'interaction** quand vous n'avez pas besoin de la sortie réelle
5. **Vérifiez les codes de sortie** : `SUCCESS`, `FAILURE`, `INVALID_ARGUMENT`, `NOT_FOUND`

---

## Exemple complet

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\TestingStep;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;
use App\Directives\CalculatorDirective;

final class CalculatorDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;
    private DirectiveTestingContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        $config = new DirectiveTestingConfig();
        $this->context = new DirectiveTestingContext(false);
        $this->context->setConfig($config);
        $this->service = new DirectiveTestingService($this->context);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_addition(): void
    {
        $this->service->createTestDirective('calc {a} {b}', function ($d) {
            $a = (int) $d->argument('a');
            $b = (int) $d->argument('b');
            $result = $a + $b;
            $d->line((string) $result);
            return ExitCode::SUCCESS;
        });
        
        $response = $this->service->runDirective('calc', ['10', '20']);
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('30', $response->output);
    }

    public function test_division_by_zero(): void
    {
        $this->service->createTestDirective('calc {a} {b}', function ($d) {
            $b = (int) $d->argument('b');
            if ($b === 0) {
                $d->error('Division by zero');
                return ExitCode::INVALID_ARGUMENT;
            }
            $a = (int) $d->argument('a');
            $result = $a / $b;
            $d->line((string) $result);
            return ExitCode::SUCCESS;
        });
        
        $response = $this->service->runDirective('calc', ['10', '0']);
        
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Division by zero', $response->output);
    }
}
---