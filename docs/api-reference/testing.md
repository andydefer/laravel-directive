# Tester vos directives - Guide complet

## Introduction

Ce guide vous apprend à tester vos directives CLI avec `DirectiveTestingService`, que vous utilisiez Laravel ou non. Vous découvrirez comment isoler vos tests, éviter la pollution du projet réel, et couvrir tous les cas d'usage.

---

## Table des matières

1. [Prérequis](#prérequis)
2. [Comprendre le problème](#comprendre-le-problème)
3. [Installation des dépendances](#installation-des-dépendances)
4. [Les deux modes de test](#les-deux-modes-de-test)
5. [Tests en mode isolé (sans Laravel)](#tests-en-mode-isolé-sans-laravel)
6. [Tests en mode intégré (avec Laravel)](#tests-en-mode-intégré-avec-laravel)
7. [Les différentes méthodes d'enregistrement](#les-différentes-méthodes-denregistrement)
8. [Créer des directives temporaires](#créer-des-directives-temporaires)
9. [Tester les arguments et options](#tester-les-arguments-et-options)
10. [Tester les arguments variadiques](#tester-les-arguments-variadiques)
11. [Tester les interactions utilisateur](#tester-les-interactions-utilisateur)
12. [Tester les codes de sortie](#tester-les-codes-de-sortie)
13. [Tester avec la base de données](#tester-avec-la-base-de-données)
14. [Bonnes pratiques](#bonnes-pratiques)
15. [Exemple complet](#exemple-complet)

---

## Prérequis

Avant de commencer, assurez-vous d'avoir :

```bash
composer require --dev phpunit/phpunit orchestra/testbench
```

Votre `phpunit.xml` doit être configuré :

```xml
<?xml version="1.0"?>
<phpunit bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

---

## Comprendre le problème

### Pourquoi un service de test dédié ?

Les directives CLI créent des fichiers, lisent la configuration, et interagissent avec le système. Sans isolation :

```php
// ❌ Problème : les fichiers sont créés dans votre projet réel
$directive = new MakeDirective();
$directive->execute(); // Crée des fichiers dans ./app/Directives/
```

**Conséquences :**
- Pollution du projet avec des fichiers de test
- Conflits entre les tests
- Nettoyage manuel fastidieux

**Solution :** `DirectiveTestingService` crée un répertoire temporaire et y redirige TOUTES les opérations.

```php
// ✅ Solution : fichiers isolés dans /tmp/directive_test_xxx/
$service = new DirectiveTestingService();
$service->run(MakeDirective::class, ['UserList']);
// Les fichiers sont créés dans /tmp/directive_test_xxx/app/Directives/
```

---

## Installation des dépendances

Pour tester avec Laravel, vous aurez besoin d'étendre `IntegrationTestCase` :

```php
<?php
// tests/IntegrationTestCase.php

namespace Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DirectiveServiceProvider::class,
            // Ajoutez vos providers ici
        ];
    }
}
```

---

## Les deux modes de test

| Mode | Description | Quand l'utiliser |
|------|-------------|------------------|
| **Mode isolé** | Pas d'application Laravel, environnement minimal | Directives sans base de données, sans cache, sans Eloquent |
| **Mode intégré** | Avec application Laravel (via `$this->app`) | Directives qui utilisent Eloquent, le cache, ou les providers Laravel |

```php
// Mode isolé
$service = new DirectiveTestingService();

// Mode intégré (dans un test qui étend IntegrationTestCase)
$service = new DirectiveTestingService($this->app);
```

---

## Tests en mode isolé (sans Laravel)

### Structure de base d'un test unitaire

```php
<?php
// tests/Unit/Directives/HelloDirectiveTest.php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;
use App\Directives\HelloDirective;

final class HelloDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveTestingService();
    }

    protected function tearDown(): void
    {
        $this->service->destroy(); // Nettoie le répertoire temporaire
        parent::tearDown();
    }

    public function test_hello_directive_returns_success(): void
    {
        $response = $this->service->run(HelloDirective::class, ['John']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }
}
```

### Vérification de l'isolation

```php
public function test_files_are_created_in_temp_directory(): void
{
    $service = new DirectiveTestingService();
    
    // Le répertoire temporaire est automatiquement créé
    $tempDir = $service->getContext()->getTempDir();
    $this->assertNotNull($tempDir);
    $this->assertDirectoryExists($tempDir);
    $this->assertStringContainsString('/tmp/directive_test_', $tempDir);
    
    // Après destruction, le répertoire est supprimé
    $service->destroy();
    $this->assertDirectoryDoesNotExist($tempDir);
}
```

### Tester une directive simple

```php
<?php
// app/Directives/GreetDirective.php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class GreetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'greet {name}';
    }

    public function getDescription(): string
    {
        return 'Greet someone';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $this->line("Hello, {$name}!");
        return ExitCode::SUCCESS;
    }
}
```

```php
// tests/Unit/Directives/GreetDirectiveTest.php

public function test_greet_directive(): void
{
    $response = $this->service->run(GreetDirective::class, ['Jane']);

    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Hello, Jane!', $response->output);
}
```

---

## Tests en mode intégré (avec Laravel)

## ⚠️ Prérequis important : Enregistrement des directives dans le conteneur

Pour que `DirectiveTestingService` puisse instancier automatiquement vos directives via `registerDirective(string $class)` ou `run(string $class)`, vos directives doivent être **enregistrées dans le conteneur de services**. En mode intégré (avec Laravel), le service utilise `$application->make($class)` qui nécessite que la directive soit bindée dans le conteneur. La meilleure pratique est de créer un **Service Provider** dédié où vous enregistrez toutes vos directives en tant que singletons ou liaisons. Sans cet enregistrement, l'instanciation échouera car le conteneur ne saura pas comment résoudre les dépendances du constructeur de votre directive. Si vous utilisez le mode isolé (sans Laravel), cette contrainte ne s'applique pas car le service utilise la réflexion pour créer les instances.

**Exemple d'enregistrement dans un Service Provider :**

```php
// App\Providers\DirectivesServiceProvider.php
use App\Directives\UserListDirective;
use App\Directives\CacheClearDirective;

public function register(): void
{
    $this->app->singleton(UserListDirective::class);
    $this->app->singleton(CacheClearDirective::class);
}
```

### Structure de base d'un test d'intégration

```php
<?php
// tests/Integration/Directives/UserStatsDirectiveTest.php

namespace Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use Tests\IntegrationTestCase;
use App\Models\User;
use App\Directives\UserStatsDirective;

final class UserStatsDirectiveTest extends IntegrationTestCase
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

    public function test_user_stats_displays_count(): void
    {
        // Créer des données de test
        User::create(['name' => 'John', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $response = $this->service->run(UserStatsDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Total users: 2', $response->output);
    }
}
```

### Directive qui utilise Eloquent

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
        return 'user-stats {--active}';
    }

    public function getDescription(): string
    {
        return 'Display user statistics';
    }

    public function shouldBootLaravel(): bool
    {
        return true; // Important : active Laravel
    }

    public function execute(): ExitCode
    {
        $query = User::query();
        
        if ($this->option('active')) {
            $query->where('is_active', true);
        }
        
        $count = $query->count();
        $this->info("Total users: {$count}");
        
        return ExitCode::SUCCESS;
    }
}
```

### Nettoyage de la base de données

```php
protected function tearDown(): void
{
    // Nettoyer les données de test
    User::truncate();
    
    $this->service->destroy();
    parent::tearDown();
}
```

---

## Les différentes méthodes d'enregistrement

### Méthode 1 : `run()` - La plus simple

```php
// Enregistre et exécute en une ligne
$response = $this->service->run(MyDirective::class, ['arg1', 'arg2']);
```

### Méthode 2 : `registerAndRun()`

```php
// Explicitement enregistrer puis exécuter
$response = $this->service->registerAndRun(MyDirective::class, ['arg1', 'arg2']);
```

### Méthode 3 : Enregistrement séparé

```php
// Utile quand vous avez besoin de la directive pour autre chose
$this->service->registerDirective(MyDirective::class);
$response = $this->service->runDirective('my-signature', ['arg1']);
```

### Méthode 4 : Avec instance manuelle

```php
// Pour un contrôle total sur l'instance
$context = new DirectiveContext(
    laravelBootstrapper: new LaravelBootstrapperContext(),
    blueprint: new DirectiveBlueprintRecord(MyDirective::class, 'my-cmd', 'Description'),
    aliases: new StringTypedCollection,
    shouldBootLaravel: false,
);

$directive = new MyDirective($context, $this->service->getInteraction());
$this->service->registerDirectiveInstance($directive);
$response = $this->service->runDirective('my-cmd', ['arg1']);
```

---

## Créer des directives temporaires

### Pourquoi des directives temporaires ?

- Tester rapidement une logique sans créer de classe dédiée
- Isoler un comportement spécifique
- Mock une partie de la logique

### Syntaxe de base

```php
public function test_temporary_directive(): void
{
    $executed = false;
    
    $this->service->createTestDirective('test-calc', function ($d) use (&$executed) {
        $executed = true;
        $result = 5 + 3;
        $d->line("Result: {$result}");
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('test-calc');
    
    $this->assertTrue($executed);
    $this->assertStringContainsString('Result: 8', $response->output);
}
```

### Avec arguments

```php
public function test_temporary_directive_with_args(): void
{
    $this->service->createTestDirective('calc {a} {b}', function ($d) {
        $a = (int) $d->argument('a');
        $b = (int) $d->argument('b');
        $result = $a + $b;
        $d->line((string) $result);
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('calc', ['10', '20']);
    
    $this->assertStringContainsString('30', $response->output);
}
```

### Avec options

```php
public function test_temporary_directive_with_options(): void
{
    $this->service->createTestDirective('process {--verbose}', function ($d) {
        if ($d->option('verbose')) {
            $d->line('Verbose mode enabled');
        }
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('process', ['--verbose']);
    
    $this->assertStringContainsString('Verbose mode enabled', $response->output);
}
```

---

## Tester les arguments et options

### Directive avec arguments requis

```php
// Directive
public function getSignature(): string
{
    return 'user-create {name} {email}';
}

// Test
public function test_required_arguments(): void
{
    $response = $this->service->run(UserCreateDirective::class, ['John', 'john@example.com']);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Directive avec arguments optionnels

```php
// Directive
public function getSignature(): string
{
    return 'user-list {limit?}';
}

// Test - sans argument
public function test_optional_argument_omitted(): void
{
    $response = $this->service->run(UserListDirective::class, []);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}

// Test - avec argument
public function test_optional_argument_provided(): void
{
    $response = $this->service->run(UserListDirective::class, ['10']);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Directive avec valeurs par défaut

```php
// Directive
public function getSignature(): string
{
    return 'user-list {limit=10}';
}

// Test - valeur par défaut
public function test_default_value(): void
{
    $response = $this->service->run(UserListDirective::class, []);
    // $limit = 10 par défaut
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}

// Test - valeur surchargée
public function test_default_value_overridden(): void
{
    $response = $this->service->run(UserListDirective::class, ['25']);
    // $limit = 25
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Directive avec options

```php
// Directive
public function getSignature(): string
{
    return 'cache-clear {--force}';
}

// Test - option absente
public function test_option_absent(): void
{
    $response = $this->service->run(CacheClearDirective::class, []);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}

// Test - option présente
public function test_option_present(): void
{
    $response = $this->service->run(CacheClearDirective::class, ['--force']);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Directive avec options à valeur

```php
// Directive
public function getSignature(): string
{
    return 'user-create {name} {--role=}';
}

// Test
public function test_option_with_value(): void
{
    $response = $this->service->run(UserCreateDirective::class, ['John', '--role=admin']);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

---

## Tester les arguments variadiques

### Directive avec arguments variadiques

```php
// Directive
public function getSignature(): string
{
    return 'process {name} {files*}';
}

public function execute(): ExitCode
{
    $name = $this->argument('name');
    $files = $this->getVariadicArguments();
    
    $this->info("Processing {$files->count()} files for {$name}");
    
    foreach ($files as $file) {
        $this->line("  - {$file}");
    }
    
    return ExitCode::SUCCESS;
}

// Test
public function test_variadic_arguments(): void
{
    $this->service->createTestDirective('process {name} {files*}', function ($d) {
        $files = $d->getVariadicArguments();
        $d->line("Count: " . $files->count());
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('process', ['John', 'file1.txt', 'file2.txt']);
    
    $this->assertStringContainsString('Count: 2', $response->output);
}
```

### Syntaxe avec crochets (recommandée)

```php
public function test_variadic_with_brackets(): void
{
    $this->service->createTestDirective('process {files*}', function ($d) {
        $files = $d->getVariadicArguments();
        $d->line("Files: " . implode(', ', $files->toArray()));
        return ExitCode::SUCCESS;
    });
    
    // Syntaxe avec crochets pour plus de lisibilité
    $response = $this->service->runDirective('process', ['[', 'file1.txt,', 'file2.txt,', 'file3.txt', ']']);
    
    $this->assertStringContainsString('file1.txt', $response->output);
}
```

---

## Tester les interactions utilisateur

### Problème : les méthodes `ask()` et `confirm()` bloquent les tests

```php
// ❌ Ce test va bloquer car il attend une saisie utilisateur
public function test_interactive_directive(): void
{
    $response = $this->service->run(SetupDirective::class, []);
    // Bloque !!!
}
```

### Solution : Mocker l'interaction

```php
public function test_interactive_directive(): void
{
    // Créer un mock de l'interaction
    $interaction = $this->createMock(DirectiveInteractionService::class);
    
    // Simuler les réponses utilisateur
    $interaction->expects($this->once())
        ->method('ask')
        ->with('Application name')
        ->willReturn('MyApp');
    
    $interaction->expects($this->once())
        ->method('confirm')
        ->with('Continue?')
        ->willReturn(true);
    
    // Créer la directive avec le mock
    $context = new DirectiveContext(
        laravelBootstrapper: new LaravelBootstrapperContext(),
        blueprint: new DirectiveBlueprintRecord(SetupDirective::class, 'setup', 'Setup wizard'),
        aliases: new StringTypedCollection,
        shouldBootLaravel: false,
    );
    
    $directive = new SetupDirective($context, $interaction);
    $this->service->registerDirectiveInstance($directive);
    
    $response = $this->service->runDirective('setup');
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Alternative : Rendre la directive non-interactive pour les tests

```php
// Dans votre directive, ajoutez un mode non-interactif
public function execute(): ExitCode
{
    $isInteractive = !$this->option('no-interaction');
    
    if ($isInteractive) {
        $name = $this->ask('What is your name?');
    } else {
        $name = $this->argument('name') ?? 'default';
    }
    
    // ...
}

// Test non-interactif
public function test_non_interactive_mode(): void
{
    $response = $this->service->run(SetupDirective::class, ['--no-interaction', '--name=John']);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

---

## Tester les codes de sortie

### Les différents codes de sortie

| Code | Constante | Description |
|------|-----------|-------------|
| 0 | `ExitCode::SUCCESS` | Exécution réussie |
| 1 | `ExitCode::FAILURE` | Erreur générale |
| 3 | `ExitCode::NOT_FOUND` | Directive non trouvée |
| 4 | `ExitCode::INVALID_ARGUMENT` | Argument invalide |

### Tester le succès

```php
public function test_directive_success(): void
{
    $response = $this->service->run(ValidDirective::class, []);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Tester une erreur

```php
public function test_directive_failure(): void
{
    $response = $this->service->run(FailingDirective::class, []);
    $this->assertSame(ExitCode::FAILURE, $response->exitCode);
    $this->assertStringContainsString('Something went wrong', $response->output);
}
```

### Tester un argument invalide

```php
public function test_invalid_argument(): void
{
    $response = $this->service->run(CalculatorDirective::class, []);
    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    $this->assertStringContainsString('Not enough arguments', $response->output);
}
```

### Tester une directive non trouvée

```php
public function test_directive_not_found(): void
{
    $response = $this->service->runDirective('non-existent-directive');
    $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
}
```

---

## Tester avec la base de données

### Configuration du test

```php
<?php
// tests/Integration/Directives/DatabaseDirectiveTest.php

namespace Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use Tests\IntegrationTestCase;
use App\Models\User;
use App\Directives\UserStatsDirective;

final class DatabaseDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mode intégré avec Laravel et base de données
        $this->service = new DirectiveTestingService($this->app);
        
        // Configurer la base de données de test
        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Exécuter les migrations
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }
}
```

### Tester une directive qui utilise Eloquent

```php
public function test_user_creation_directive(): void
{
    $response = $this->service->run(UserCreateDirective::class, ['John', 'john@example.com']);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
}
```

### Nettoyer la base de données entre les tests

```php
protected function tearDown(): void
{
    // Nettoyer les tables
    User::truncate();
    
    $this->service->destroy();
    parent::tearDown();
}
```

---

## Bonnes pratiques

### 1. Toujours appeler `destroy()` dans `tearDown()`

```php
protected function tearDown(): void
{
    $this->service->destroy();
    parent::tearDown();
}
```

### 2. Un test par cas d'usage

```php
// ❌ À éviter
public function test_all_calculations(): void
{
    // Test addition, soustraction, multiplication...
}

// ✅ Recommandé
public function test_addition(): void { ... }
public function test_subtraction(): void { ... }
public function test_multiplication(): void { ... }
```

### 3. Utiliser des noms de test explicites

```php
public function test_directive_returns_success_when_valid_name_provided(): void { ... }
public function test_directive_returns_failure_when_name_is_missing(): void { ... }
```

### 4. Vérifier à la fois le code et la sortie

```php
public function test_directive_behavior(): void
{
    $response = $this->service->run(MyDirective::class, ['test']);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Expected output', $response->output);
}
```

### 5. Isoler les tests de base de données

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Nettoyer avant chaque test
    User::truncate();
}
```

### 6. Utiliser les fixtures pour les données complexes

```php
private function createTestUser(): User
{
    return User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
}

public function test_directive_with_user(): void
{
    $user = $this->createTestUser();
    $response = $this->service->run(UserShowDirective::class, [(string) $user->id]);
    
    $this->assertStringContainsString('Test User', $response->output);
}
```

---

## Exemple complet

### La directive à tester

```php
<?php
// app/Directives/UserManagerDirective.php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use App\Models\User;

final class UserManagerDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-manager {action} {name?} {email?} {--role=user}';
    }

    public function getDescription(): string
    {
        return 'Manage users (create, list, delete)';
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $action = $this->argument('action');
        
        return match($action) {
            'create' => $this->createUser(),
            'list' => $this->listUsers(),
            'delete' => $this->deleteUser(),
            default => $this->invalidAction()
        };
    }

    private function createUser(): ExitCode
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $role = $this->option('role');
        
        if ($name === null || $email === null) {
            $this->error('Name and email are required for create action');
            return ExitCode::INVALID_ARGUMENT;
        }
        
        User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);
        
        $this->info("User {$name} created successfully");
        return ExitCode::SUCCESS;
    }

    private function listUsers(): ExitCode
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->warn('No users found');
            return ExitCode::SUCCESS;
        }
        
        $headers = new StringTypedCollection();
        $headers->add('ID', 'Name', 'Email', 'Role');
        
        $rows = new RowCollection();
        foreach ($users as $user) {
            $row = new RowCollection();
            $row->add($user->id, $user->name, $user->email, $user->role);
            $rows->add($row);
        }
        
        $this->table($headers, $rows);
        return ExitCode::SUCCESS;
    }

    private function deleteUser(): ExitCode
    {
        $name = $this->argument('name');
        
        if ($name === null) {
            $this->error('Name is required for delete action');
            return ExitCode::INVALID_ARGUMENT;
        }
        
        $deleted = User::where('name', $name)->delete();
        
        if ($deleted === 0) {
            $this->error("User {$name} not found");
            return ExitCode::FAILURE;
        }
        
        $this->info("User {$name} deleted successfully");
        return ExitCode::SUCCESS;
    }

    private function invalidAction(): ExitCode
    {
        $this->error('Invalid action. Use: create, list, or delete');
        return ExitCode::INVALID_ARGUMENT;
    }
}
```

### Tests complets

```php
<?php
// tests/Integration/Directives/UserManagerDirectiveTest.php

namespace Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use Tests\IntegrationTestCase;
use App\Models\User;

final class UserManagerDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveTestingService($this->app);
        User::truncate();
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== Create Action Tests ====================

    public function test_create_user_success(): void
    {
        $response = $this->service->run(
            UserManagerDirective::class,
            ['create', 'John Doe', 'john@example.com', '--role=admin']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('created successfully', $response->output);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_create_user_missing_name_returns_error(): void
    {
        $response = $this->service->run(
            UserManagerDirective::class,
            ['create', '--role=admin']
        );

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Name and email are required', $response->output);
    }

    public function test_create_user_with_default_role(): void
    {
        $response = $this->service->run(
            UserManagerDirective::class,
            ['create', 'Jane Doe', 'jane@example.com']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        
        $user = User::where('email', 'jane@example.com')->first();
        $this->assertSame('user', $user->role);
    }

    // ==================== List Action Tests ====================

    public function test_list_users_shows_all_users(): void
    {
        User::create(['name' => 'John', 'email' => 'john@test.com', 'role' => 'admin']);
        User::create(['name' => 'Jane', 'email' => 'jane@test.com', 'role' => 'user']);

        $response = $this->service->run(UserManagerDirective::class, ['list']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('John', $response->output);
        $this->assertStringContainsString('Jane', $response->output);
    }

    public function test_list_users_when_empty_shows_warning(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['list']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('No users found', $response->output);
    }

    // ==================== Delete Action Tests ====================

    public function test_delete_user_success(): void
    {
        User::create(['name' => 'John Doe', 'email' => 'john@test.com', 'role' => 'admin']);

        $response = $this->service->run(UserManagerDirective::class, ['delete', 'John Doe']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('deleted successfully', $response->output);
        $this->assertDatabaseMissing('users', ['name' => 'John Doe']);
    }

    public function test_delete_nonexistent_user_returns_error(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['delete', 'Nonexistent']);

        $this->assertSame(ExitCode::FAILURE, $response->exitCode);
        $this->assertStringContainsString('not found', $response->output);
    }

    public function test_delete_missing_name_returns_error(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['delete']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Name is required', $response->output);
    }

    // ==================== Invalid Action Tests ====================

    public function test_invalid_action_returns_error(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['invalid']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Invalid action', $response->output);
    }
}
```

---

## Conclusion

Avec `DirectiveTestingService`, vous pouvez :

- ✅ Tester vos directives dans un environnement totalement isolé
- ✅ Éviter la pollution du projet réel
- ✅ Tester avec ou sans Laravel
- ✅ Simuler les interactions utilisateur
- ✅ Vérifier les codes de sortie et les sorties
- ✅ Tester les cas d'erreur
- ✅ Utiliser des directives temporaires pour des tests rapides

**Rappel :** Toujours appeler `destroy()` dans `tearDown()` pour nettoyer le répertoire temporaire.
---