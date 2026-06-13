# DirectiveTestingService - Référence Technique

## Description

Service de test pour les directives CLI. Fournit un environnement isolé pour exécuter et tester des directives, avec ou sans intégration Laravel, en redirigeant automatiquement toutes les opérations de fichiers vers un répertoire temporaire.

## Hiérarchie

```
DirectiveTestingService (final)
```

## Rôle principal

Faciliter les tests unitaires et d'intégration des directives en offrant une API simple pour l'exécution. Le service crée automatiquement un répertoire temporaire, isole les opérations de fichiers, nettoie après les tests, et supporte l'injection automatique des dépendances via le conteneur Laravel.

## Installation

```bash
composer require --dev andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(?Application $application = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$application` | `Application|null` | Application Laravel (mode intégré) ou `null` (mode isolé) |

**Exemple :**
```php
// Mode isolé (sans Laravel)
$service = new DirectiveTestingService();

// Mode intégré (avec Laravel)
$service = new DirectiveTestingService($this->app);
```

### `run(string $class, array $arguments = []): DirectiveResponseRecord`

Exécute une directive par sa classe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive (ex: `UserListDirective::class`) |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse avec code de sortie et sortie

**Exceptions :** `InvalidArgumentException` - La classe n'existe pas

**Exemple :**
```php
$response = $service->run(UserListDirective::class, ['--active']);
```

### `destroy(): void`

Détruit l'environnement de test : restaure le répertoire original et supprime le répertoire temporaire.

### `getInteraction(): DirectiveInteractionService`

Retourne le service d'interaction utilisateur.

## Cas d'utilisation

### Cas 1 : Test unitaire sans Laravel

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
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
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_hello_directive(): void
    {
        $response = $this->service->run(HelloDirective::class, ['John']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }
}
```

### Cas 2 : Test d'intégration avec Laravel

```php
<?php

namespace Tests\Integration\Directives;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
use Orchestra\Testbench\TestCase;

final class UserStatsDirectiveTest extends TestCase
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

    public function test_user_stats(): void
    {
        // Créer des données en base
        User::create(['name' => 'John', 'email' => 'john@example.com']);

        $response = $this->service->run(UserStatsDirective::class);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Total users: 1', $response->output);
    }
}
```

### Cas 3 : Test avec variadic arguments

```php
public function test_variadic_directive(): void
{
    $response = $this->service->run(FileProcessDirective::class, [
        'John', 
        'file1.txt', 
        'file2.txt', 
        '--verbose'
    ]);

    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    $this->assertStringContainsString('file1.txt', $response->output);
}
```

### Cas 4 : Test avec gestion d'erreur

```php
public function test_division_by_zero(): void
{
    $response = $this->service->run(CalculatorDirective::class, ['div', '10', '0']);

    $this->assertSame(ExitCode::FAILURE, $response->exit_code);
    $this->assertStringContainsString('Division by zero', $response->output);
}
```

## Flux d'exécution

```
__construct(?Application $application)
    │
    ├── Initialisation typeConverter et interaction
    │
    ├── Sauvegarde originalCwd
    │
    └── setupTempDirectory()
        ├── Création tempDir (sys_get_temp_dir()/directive_test_*)
        ├── mkdir() récursif
        └── chdir() vers tempDir

run(string $class, array $arguments)
    │
    ├── createDirective($class)
    │   ├── Vérification existence classe
    │   ├── Analyse constructeur via réflexion
    │   ├── Résolution automatique des dépendances :
    │   │   - DirectiveContext → createDirectiveContext()
    │   │   - DirectiveInteractionService → $this->interaction
    │   │   - Autres classes → $application->make() (si mode intégré)
    │   └── newInstanceArgs()
    │
    └── executeDirective($directive, $arguments)
        ├── createDirectiveContext()
        ├── parseArguments() via DirectiveParserService
        ├── hydrateDirective() (re-crée l'instance avec le vrai contexte)
        ├── ob_start() pour capturer la sortie
        └── Retourne DirectiveResponseRecord

destroy()
    │
    ├── Restauration originalCwd (si répertoire existe)
    └── Suppression récursive tempDir
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Classe de directive inexistante | `InvalidArgumentException` avec message `Directive class {$class} does not exist` |
| Exception pendant l'exécution | `DirectiveResponseRecord` avec `ExitCode::FAILURE` et le message d'erreur comme output |
| Succès de l'exécution | `DirectiveResponseRecord` avec `ExitCode::SUCCESS` et la sortie capturée |

## Intégration

`DirectiveTestingService` s'intègre avec :

- **`DirectiveInteractionService`** : Gestion des affichages (`line()`, `info()`, `error()`, `table()`, etc.)
- **`DirectiveParserService`** : Parsing des signatures et arguments
- **`PrimitiveTypeConverterService`** : Conversion automatique des types (bool, int, float, null)
- **`Application` (Laravel)** : Injection automatique des dépendances en mode intégré

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Répertoire temporaire | Créé une fois par instance |
| Changement de répertoire | `chdir()` - O(1) |
| Nettoyage | Suppression récursive du temp dir (`removeDirectory`) |
| Mode intégré vs isolé | Même isolation, seule la résolution des dépendances diffère |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| Laravel 10+ | ✅ Mode intégré |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use App\Directives\CalculatorDirective;
use PHPUnit\Framework\TestCase;

final class CalculatorDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveTestingService();
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_addition(): void
    {
        $response = $this->service->run(CalculatorDirective::class, ['add', '10', '20']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('30', $response->output);
    }

    public function test_division_by_zero(): void
    {
        $response = $this->service->run(CalculatorDirective::class, ['div', '10', '0']);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Division by zero', $response->output);
    }

    public function test_temp_directory_isolation(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $tempDirProperty = $reflection->getProperty('tempDir');
        $tempDir = $tempDirProperty->getValue($this->service);

        $this->assertNotNull($tempDir);
        $this->assertDirectoryExists($tempDir);
        $this->assertStringContainsString('directive_test', getcwd());
    }
}
```

## Notes importantes

1. **Pas de méthodes `registerDirective()`** : L'enregistrement explicite n'est plus nécessaire. La méthode `run()` crée et exécute la directive directement.

2. **Pas de `createTestDirective()`** : Les directives temporaires par closure ne sont plus supportées. Utilisez des classes de fixture.

3. **Pas de `runDirective()` par signature** : L'exécution se fait uniquement par FQCN.

4. **Pas de `getContext()`** : Le contexte de test n'est plus exposé publiquement.

5. **Nettoyage automatique** : Appelez toujours `destroy()` dans `tearDown()` pour nettoyer le répertoire temporaire.
---