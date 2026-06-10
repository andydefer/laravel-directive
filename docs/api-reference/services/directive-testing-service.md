# DirectiveTestingService - Référence Technique

## Description

Service de test pour les directives CLI. Fournit un environnement isolé pour exécuter et tester des directives, avec ou sans intégration Laravel, en redirigeant automatiquement toutes les opérations de fichiers vers un répertoire temporaire.

## Hiérarchie

```
DirectiveTestingService (final)
    └── Implémente : DirectiveTestingServiceInterface
```

## Rôle principal

Faciliter les tests unitaires et d'intégration des directives en offrant une API unifiée pour l'enregistrement et l'exécution. Le service crée automatiquement un répertoire temporaire, isole les opérations de fichiers, nettoie après les tests, et supporte l'injection automatique des dépendances via le conteneur Laravel.

## Installation

```bash
composer require --dev andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(?object $application = null, ?DirectiveTestingContext $context = null, ?DirectiveTestingConfigInterface $config = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$application` | `object|null` | Application Laravel (mode intégré) ou `null` (mode isolé) |
| `$context` | `DirectiveTestingContext|null` | Contexte de test (créé automatiquement si null) |
| `$config` | `DirectiveTestingConfigInterface|null` | Configuration (créée automatiquement si null) |

**Exemple :**
```php
// Mode isolé (sans Laravel)
$service = new DirectiveTestingService();

// Mode intégré (avec Laravel)
$service = new DirectiveTestingService($this->app);
```

### `registerDirective(string $class): void`

Enregistre une directive par son nom de classe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive (ex: `UserListDirective::class`) |

**Exceptions :** `InvalidArgumentException` - La classe n'existe pas

**Exemple :**
```php
$service->registerDirective(UserListDirective::class);
```

### `registerDirectiveInstance(AbstractDirective $directive): void`

Enregistre une instance de directive directement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directive` | `AbstractDirective` | Instance de directive |

**Exemple :**
```php
$directive = new UserListDirective($context, $interaction);
$service->registerDirectiveInstance($directive);
```

### `registerAndRun(string $class, array $arguments = []): DirectiveResponseRecord`

Enregistre une directive par son nom de classe et l'exécute immédiatement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse avec code de sortie et sortie

**Exemple :**
```php
$response = $service->registerAndRun(UserListDirective::class, ['--active']);
```

### `registerAndRunInstance(AbstractDirective $directive, array $arguments = []): DirectiveResponseRecord`

Enregistre une instance de directive et l'exécute immédiatement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directive` | `AbstractDirective` | Instance de directive |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse avec code de sortie et sortie

### `run(string $class, array $arguments = []): DirectiveResponseRecord`

Exécute une directive en l'enregistrant automatiquement (alias de `registerAndRun`).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse avec code de sortie et sortie

**Exemple :**
```php
$response = $service->run(UserListDirective::class, ['--active']);
```

### `runDirective(string $signature, array $arguments = []): DirectiveResponseRecord`

Exécute une directive par sa signature (doit être déjà enregistrée).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive (ex: `user-list`) |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse avec code de sortie et sortie

### `createTestDirective(string $signature, callable $execute): ClosureDirective`

Crée une directive temporaire avec une closure comme logique d'exécution.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive |
| `$execute` | `callable(ClosureDirective): ExitCode` | Logique d'exécution |

**Retourne :** `ClosureDirective` - La directive créée

**Exemple :**
```php
$service->createTestDirective('test-calc', function ($d) {
    $result = 5 + 3;
    $d->line("Result: {$result}");
    return ExitCode::SUCCESS;
});
```

### `clearRegisteredDirectives(): void`

Supprime toutes les directives enregistrées.

### `destroy(): void`

Détruit l'environnement de test : restaure le répertoire original et supprime le répertoire temporaire.

### `getInteraction(): DirectiveInteractionService`

Retourne le service d'interaction utilisateur.

### `getContext(): DirectiveTestingContext`

Retourne le contexte de test.

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

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }
}
```

### Cas 2 : Test d'intégration avec Laravel

```php
<?php

namespace Tests\Integration\Directives;

use AndyDefer\Directive\Services\DirectiveTestingService;
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

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Total users: 1', $response->output);
    }
}
```

### Cas 3 : Directive temporaire pour test rapide

```php
public function test_temporary_directive(): void
{
    $executed = false;

    $this->service->createTestDirective('test-calc', function ($d) use (&$executed) {
        $executed = true;
        $d->line('42');
        return ExitCode::SUCCESS;
    });

    $response = $this->service->runDirective('test-calc');

    $this->assertTrue($executed);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('42', $response->output);
}
```

### Cas 4 : Enregistrement manuel d'une instance

```php
public function test_manual_registration(): void
{
    $context = new DirectiveContext(
        laravelBootstrapper: new LaravelBootstrapperContext,
        blueprint: new DirectiveBlueprintRecord(HelloDirective::class, 'hello', 'Say hello'),
        aliases: new StringTypedCollection,
        shouldBootLaravel: false,
    );

    $directive = new HelloDirective($context, $this->service->getInteraction());
    $this->service->registerDirectiveInstance($directive);

    $response = $this->service->runDirective('hello', ['John']);

    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

## Flux d'exécution

```
__construct()
    │
    ├── initializeInteraction()
    │
    ├── setupTempDirectory()
    │   ├── Sauvegarde originalCwd
    │   ├── Création tempDir
    │   ├── chdir() vers tempDir
    │   ├── putenv() variables d'environnement
    │   └── createTempDirectories()
    │
    ├── Si application !== null (mode intégré)
    │   ├── setIntegratedMode(true)
    │   ├── updateApplicationPaths()
    │   ├── Récupération du kernel
    │   └── Récupération du LaravelBootstrapperContext
    │
    └── Sinon (mode isolé)
        └── initializeMinimalEnvironment()

run() / registerAndRun()
    │
    ├── registerDirective($class)
    │   ├── Instanciation via conteneur ou réflexion
    │   └── Enregistrement dans le Registry
    │
    ├── extractSignatureFromClass($class)
    │
    └── runDirective($signature, $arguments)
        ├── Recherche dans ClosureRegistry
        ├── Recherche dans Registry
        └── Fallback via Kernel

destroy()
    │
    ├── clearRegisteredDirectives()
    ├── Restauration originalCwd (si répertoire existe)
    └── Suppression tempDir
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Classe de directive inexistante | `InvalidArgumentException` | `Directive class {$class} does not exist` |
| Classe sans méthode `getSignature` | `RuntimeException` | `Class {$class} does not have a getSignature method` |
| Directive non trouvée | `ExitCode::NOT_FOUND` (pas d'exception) | `Kernel not available...` ou message du renderer |
| Constructeur manquant | `ExitCode::FAILURE` | `Directive has no constructor` |

## Intégration

`DirectiveTestingService` s'intègre avec :

- **`DirectiveTestingContext`** : Stockage de l'état (temp dir, cwd original, registres)
- **`DirectiveInteractionService`** : Gestion des affichages et entrées utilisateur
- **`LaravelBootstrapperContext`** : Bootstrap optionnel de Laravel
- **`DirectiveKernel`** : Noyau d'exécution (fallback)
- **`FileCreatorConfig`** : Via variable d'environnement `FILE_CREATOR_WORKING_DIR`
- **`EnvDirectiveConfig`** : Via variable d'environnement `DIRECTIVE_PATH`

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Répertoire temporaire | Créé une fois par instance |
| Changement de répertoire | `chdir()` - O(1) |
| Variables d'environnement | `putenv()` - O(1) |
| Nettoyage | Suppression récursive du temp dir |
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

    public function test_temp_directory_isolation(): void
    {
        $tempDir = $this->service->getContext()->getTempDir();
        $this->assertNotNull($tempDir);
        $this->assertDirectoryExists($tempDir);
        $this->assertStringContainsString('directive_test', getcwd());
    }
}
```
---