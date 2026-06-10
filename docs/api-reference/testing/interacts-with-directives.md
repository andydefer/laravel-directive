# InteractsWithDirectives - Référence Technique

> ⚠️ **DÉPRÉCIÉ depuis la version 3.8.0** - Ce trait est déprécié et sera supprimé dans la version 4.0.0.
> 
> Veuillez utiliser `DirectiveTestingService` à la place.

## 📌 Raisons de la dépréciation

| Problème | Explication |
|----------|-------------|
| **Couplage implicite** | Le trait crée un couplage caché entre la classe de test et les services internes. Les méthodes `initDirectiveTesting()`, `registerDirective()`, `runDirective()` apparaissent comme par magie sans injection explicite. |
| **État caché** | Les propriétés privées (`$directiveTempDir`, `$directiveKernel`, `$interaction`) sont stockées dans la classe de test, créant un état implicite difficile à traquer. |
| **Difficulté de test** | Impossible de tester le comportement du trait lui-même car il doit être utilisé dans une classe. |
| **Singletons implicites** | `NormalizerChain::get()`, `Hydrator::hydrate()` sont appelés en interne sans possibilité de substitution. |
| **Violation du SRP** | La classe de test se retrouve avec des responsabilités supplémentaires (gestion de l'environnement de test, nettoyage, registre) qu'elle ne devrait pas avoir. |
| **Pas de traçabilité** | Impossible de savoir ce qui s'est passé pendant l'exécution (étapes, fichiers créés, erreurs) car l'état n'est pas exposé. |
| **Composition vs Héritage** | Le trait force l'héritage au lieu de la composition. On ne peut pas réutiliser la logique sans hériter de `TestCase`. |

## 🎯 Avantages de la nouvelle approche avec `DirectiveTestingService`

| Avantage | Explication |
|----------|-------------|
| **Découplage total** | Le service est injecté, pas hérité. La classe de test ne dépend que de ce dont elle a besoin. |
| **État traçable** | Le `DirectiveTestingContext` expose tout l'état : répertoire temporaire, étapes exécutées, fichiers créés, résultats. |
| **Testabilité** | Le service peut être testé isolément, mocké, remplacé. |
| **Composition explicite** | On compose ce dont on a besoin, on n'hérite pas de comportements non désirés. |
| **Flux observable** | Chaque étape est enregistrée dans le contexte. On peut savoir exactement ce qui s'est passé. |
| **Nettoyage garanti** | `destroy()` nettoie proprement toutes les ressources. |
| **Pas de pollution** | La classe de test ne contient plus de propriétés techniques. |

## 🔄 Migration

```php
// ❌ Ancienne approche (dépréciée depuis 3.8.0)
class MyDirectiveTest extends TestCase
{
    use InteractsWithDirectives;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }
    
    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }
    
    public function test_directive(): void
    {
        $directive = new MyDirective();
        $this->registerDirective($directive);
        $response = $this->runDirective('my-cmd');
        
        // ❌ Impossible de savoir quels fichiers ont été créés
        // ❌ Impossible de savoir quelles étapes ont été exécutées
    }
}

// ✅ Nouvelle approche (recommandée)
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;

class MyDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;
    private DirectiveTestingContext $context;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Composition explicite
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
    
    public function test_directive(): void
    {
        // Création et enregistrement
        $directive = new MyDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);
        
        // Exécution
        $response = $this->service->runDirective('my-cmd');
        
        // ✅ Traçabilité : on peut inspecter le contexte
        $this->assertTrue($this->context->hasStepResult(TestingStep::CREATE_TEMP_DIRECTORY));
        $this->assertTrue($this->context->hasCreatedPaths());
        $this->assertEquals(1, $this->context->getCreatedPathsCount());
        
        // ✅ Chaque étape est enregistrée
        $stepResult = $this->context->getStepResult(TestingStep::CREATE_TEMP_DIRECTORY);
        $this->assertNotNull($stepResult);
        $this->assertEquals(StepResultStatus::SUCCESS, $stepResult->status);
    }
}
```

## 📖 Philosophie : Composition over Inheritance

| Principe | Trait (`InteractsWithDirectives`) | Service (`DirectiveTestingService`) |
|----------|-----------------------------------|-------------------------------------|
| **Composition** | ❌ Héritage implicite | ✅ Injection explicite |
| **État** | ❌ Caché dans les propriétés privées | ✅ Exposé via `DirectiveTestingContext` |
| **Testabilité** | ❌ Difficile (trait à tester via une classe) | ✅ Facile (service mockable) |
| **Couplage** | ❌ Fort (dépendances internes cachées) | ✅ Faible (dépendances injectées) |
| **Traçabilité** | ❌ Aucune (boîte noire) | ✅ Totale (contexte observable) |
| **Réutilisabilité** | ❌ Limitée (doit être utilisé dans un TestCase) | ✅ Totale (peut être utilisé partout) |
| **SRP** | ❌ Violé (test + gestion environnement) | ✅ Respecté (service spécialisé) |

## 🧠 Leçon : Pourquoi les traits sont souvent une mauvaise idée

Les traits créent une **illusion de réutilisabilité** mais cachent des dépendances et de l'état. Ils violent plusieurs principes SOLID :

1. **Single Responsibility Principle (SRP)** : Le trait ajoute des responsabilités à la classe qui l'utilise.
2. **Dependency Inversion Principle (DIP)** : On dépend d'une implémentation concrète (le trait), pas d'une abstraction.
3. **Interface Segregation Principle (ISP)** : On hérite de toutes les méthodes du trait, même celles dont on n'a pas besoin.

La **composition** (injection de service) résout tous ces problèmes :
- ✅ Le service a une seule responsabilité
- ✅ On dépend d'une abstraction (l'interface du service)
- ✅ On ne prend que ce dont on a besoin

## Description

Trait PHPUnit fournissant des utilitaires de test pour les directives, permettant un environnement isolé sans dépendance au système de fichiers.

> ⚠️ **Ce trait est déprécié depuis la version 3.8.0.** Utilisez `DirectiveTestingService` pour les nouveaux développements.

## Hiérarchie

```
Trait PHPUnit
    └── InteractsWithDirectives
```

## Rôle principal

Ce trait permet de tester des directives dans un environnement totalement isolé. Il crée un répertoire temporaire, initialise l'ensemble des services nécessaires (kernel, discovery, execution) et offre des méthodes simples pour enregistrer et exécuter des directives pendant les tests.

## Installation

```bash
composer require --dev andydefer/php-records
```

Le trait s'utilise dans une classe PHPUnit :

```php
use AndyDefer\Directive\Testing\InteractsWithDirectives;

final class MyDirectiveTest extends TestCase
{
    use InteractsWithDirectives;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }
    
    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }
}
```

## API / Méthodes publiques

### `initDirectiveTesting(bool $bootLaravel = false): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$bootLaravel` | `bool` | Si `true`, bootstrap une application Laravel minimale |

Initialise l'environnement de test. Crée un répertoire temporaire, configure le conteneur de services et optionnellement Laravel.

**Exemple :**
```php
protected function setUp(): void
{
    parent::setUp();
    $this->initDirectiveTesting();
}
```

### `destroyDirectiveTesting(): void`

Détruit l'environnement de test et nettoie les fichiers temporaires.

**Exemple :**
```php
protected function tearDown(): void
{
    $this->destroyDirectiveTesting();
    parent::tearDown();
}
```

### `registerDirective(AbstractDirective $directive): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directive` | `AbstractDirective` | Instance de directive à enregistrer |

Enregistre une directive pour les tests.

**Exemple :**
```php
$directive = new MyDirective();
$this->registerDirective($directive);
```

### `registerDirectives(array $directives): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directives` | `array<AbstractDirective>` | Tableau de directives |

Enregistre plusieurs directives.

**Exemple :**
```php
$this->registerDirectives([$directive1, $directive2]);
```

### `clearRegisteredDirectives(): void`

Supprime toutes les directives enregistrées.

**Exemple :**
```php
$this->clearRegisteredDirectives();
```

### `createTestDirective(string $signature, callable $execute): ClosureDirective`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive |
| `$execute` | `callable` | Logique d'exécution |

**Retourne :** `ClosureDirective` - La directive créée

Crée une directive temporaire avec une closure comme logique d'exécution.

**Exemple :**
```php
$this->createTestDirective('test:cmd', function ($d) {
    $d->line('Hello World');
    return ExitCode::SUCCESS;
});
```

### `runDirective(string $className, array $arguments = []): DirectiveResponseRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$className` | `string` | Nom de la directive (signature ou FQCN) |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse contenant le code de sortie et la sortie

Exécute une directive et retourne sa réponse.

**Exemple :**
```php
$response = $this->runDirective('calculator', ['add', '5', '3']);
$this->assertSame(ExitCode::SUCCESS, $response->exitCode);
```

### `getBufferLevel(): int`

**Retourne :** `int` - Niveau actuel du buffer de sortie

Utile pour déboguer les problèmes liés aux buffers.

## Cas d'utilisation

### Cas 1 : Test d'une directive simple

```php
public function test_calculator_adds_numbers(): void
{
    // Arrange
    $directive = new CalculatorDirective();
    $this->registerDirective($directive);
    
    // Act
    $response = $this->runDirective('calculator', ['add', '5', '3']);
    
    // Assert
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('8', $response->output);
}
```

### Cas 2 : Test avec directive temporaire

```php
public function test_custom_directive_logic(): void
{
    // Arrange
    $executed = false;
    
    $this->createTestDirective('test:ping', function ($d) use (&$executed) {
        $executed = true;
        $d->line('Pong!');
        return ExitCode::SUCCESS;
    });
    
    // Act
    $response = $this->runDirective('test:ping');
    
    // Assert
    $this->assertTrue($executed);
    $this->assertStringContainsString('Pong!', $response->output);
}
```

### Cas 3 : Test avec options

```php
public function test_directive_with_verbose_option(): void
{
    // Arrange
    $directive = new VerboseDirective();
    $this->registerDirective($directive);
    
    // Act
    $response = $this->runDirective('verbose:cmd', ['--verbose']);
    
    // Assert
    $this->assertStringContainsString('[DEBUG]', $response->output);
}
```

### Cas 4 : Test d'erreur

```php
public function test_division_by_zero_returns_error(): void
{
    // Arrange
    $directive = new CalculatorDirective();
    $this->registerDirective($directive);
    
    // Act
    $response = $this->runDirective('calculator', ['div', '10', '0']);
    
    // Assert
    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    $this->assertStringContainsString('Division by zero', $response->output);
}
```

### Cas 5 : Test avec environnement Laravel

```php
public function test_directive_using_laravel_cache(): void
{
    // Arrange
    $this->initDirectiveTesting(bootLaravel: true);
    $directive = new CacheDirective();
    $this->registerDirective($directive);
    
    // Act
    $response = $this->runDirective('cache:get', ['user:123']);
    
    // Assert
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

## Flux d'exécution

```
1. setUp() / initDirectiveTesting()
   ├── Création répertoire temporaire
   ├── Changement de répertoire courant
   ├── (Optionnel) Création structure Laravel
   ├── Initialisation Container
   ├── Enregistrement des services
   └── Initialisation du Kernel

2. test_*()
   ├── registerDirective() / createTestDirective()
   ├── runDirective()
   └── Assertions

3. tearDown() / destroyDirectiveTesting()
   ├── clearRegisteredDirectives()
   ├── Suppression répertoire temporaire
   └── Restauration répertoire courant
```

## Gestion des erreurs

| Situation | Code retour | Message |
|-----------|-------------|---------|
| Directive non trouvée | `ExitCode::NOT_FOUND` | `Directive not found: {name}` |
| Arguments invalides | `ExitCode::INVALID_ARGUMENT` | Message de l'exception |
| Exception pendant l'exécution | `ExitCode::FAILURE` | Message de l'exception |

## Intégration

### Avec TestCase PHPUnit

```php
use PHPUnit\Framework\TestCase;
use AndyDefer\Directive\Testing\InteractsWithDirectives;

final class MyTest extends TestCase
{
    use InteractsWithDirectives;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }
    
    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }
}
```

### Avec des mocks

```php
public function test_with_mocked_dependency(): void
{
    $mock = $this->createMock(UserRepository::class);
    $mock->method('find')->willReturn($user);
    
    $directive = new UserDirective($mock);
    $this->registerDirective($directive);
    
    $response = $this->runDirective('user:find', ['123']);
    
    $this->assertStringContainsString('John Doe', $response->output);
}
```

## Performance

- **Premier appel :** Création du répertoire temporaire et de tous les services (~10-20ms)
- **Appels suivants :** Service déjà initialisé, retour immédiat
- **Mémoire :** Stockage des directives enregistrées pendant la durée du test
- **Nettoyage :** `destroyDirectiveTesting()` supprime tout le répertoire temporaire

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

| Version Laravel | Support (bootLaravel) |
|----------------|----------------------|
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use PHPUnit\Framework\TestCase;

final class CalculatorTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_addition(): void
    {
        $this->createTestDirective('calc', function ($d) {
            $d->line('Result: 42');
            return ExitCode::SUCCESS;
        });

        $response = $this->runDirective('calc');

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('42', $response->output);
    }

    public function test_with_multiple_registered_directives(): void
    {
        $this->createTestDirective('cmd:a', fn($d) => $d->line('A') | ExitCode::SUCCESS);
        $this->createTestDirective('cmd:b', fn($d) => $d->line('B') | ExitCode::SUCCESS);

        $responseA = $this->runDirective('cmd:a');
        $responseB = $this->runDirective('cmd:b');

        $this->assertStringContainsString('A', $responseA->output);
        $this->assertStringContainsString('B', $responseB->output);
    }

    public function test_clear_directives(): void
    {
        $this->createTestDirective('temp:cmd', fn($d) => ExitCode::SUCCESS);
        
        $response = $this->runDirective('temp:cmd');
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        
        $this->clearRegisteredDirectives();
        
        $response = $this->runDirective('temp:cmd');
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
    }
}
```
---