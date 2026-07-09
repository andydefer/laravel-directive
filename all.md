
<!-- ==== ./docs/api-reference/testing/test-directive-discovery-service.md ==== -->

# TestDirectiveDiscoveryService - Référence Technique

## Description

Version spécialisée pour les tests du service de découverte de directives, permettant l'enregistrement programmatique sans exploration du système de fichiers.

## Hiérarchie

```
DirectiveDiscoveryService
    └── TestDirectiveDiscoveryService
```

## Rôle principal

Cette classe étend `DirectiveDiscoveryService` pour fournir un environnement de test contrôlé. Elle permet d'enregistrer des directives manuellement (via des instances ou des noms de classe) et peut désactiver la découverte automatique du système de fichiers, évitant ainsi les dépendances externes pendant les tests unitaires.

## Installation

```bash
composer require --dev andydefer/php-records
```

Cette classe est destinée uniquement à l'environnement de test.

```php
use AndyDefer\Directive\Testing\TestDirectiveDiscoveryService;

$service = new TestDirectiveDiscoveryService($config, $hydrator, true);
```

## API / Méthodes publiques

### `__construct(DirectiveConfig $config, DirectiveHydratorService $hydrator, bool $disableFilesystemDiscovery = true)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `DirectiveConfig` | Configuration de la découverte |
| `$hydrator` | `DirectiveHydratorService` | Service d'hydratation |
| `$disableFilesystemDiscovery` | `bool` | Si `true`, désactive la découverte sur disque |

**Exemple :**
```php
$config = DirectiveConfig::default();
$hydrator = new DirectiveHydratorService();
$service = new TestDirectiveDiscoveryService($config, $hydrator, true);
```

### `registerDirective(AbstractDirective $directive): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directive` | `AbstractDirective` | Instance de directive à enregistrer |

Enregistre une instance de directive dans le service de test.

**Exemple :**
```php
$directive = new UserCreateDirective();
$service->registerDirective($directive);
```

### `registerDirectives(array $directives): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directives` | `array<AbstractDirective>` | Tableau de directives à enregistrer |

Enregistre plusieurs instances de directives.

**Exemple :**
```php
$directives = [new UserCreateDirective(), new CacheClearDirective()];
$service->registerDirectives($directives);
```

### `registerDirectiveClass(string $className, array $constructorArgs = []): AbstractDirective`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$className` | `class-string<AbstractDirective>` | Nom de la classe de directive |
| `$constructorArgs` | `array<mixed>` | Arguments du constructeur |

**Retourne :** `AbstractDirective` - Instance créée et enregistrée

Instancie une directive par son nom de classe et l'enregistre.

**Exemple :**
```php
$directive = $service->registerDirectiveClass(
    UserCreateDirective::class,
    [$dependency1, $dependency2]
);
```

### `clearRegisteredDirectives(): void`

Supprime toutes les directives précédemment enregistrées.

**Exemple :**
```php
$service->clearRegisteredDirectives();
$this->assertCount(0, $service->getRegisteredDirectives());
```

### `getRegisteredDirectives(): array`

**Retourne :** `array<AbstractDirective>` - Toutes les directives enregistrées

Récupère la liste des directives actuellement enregistrées.

**Exemple :**
```php
$directives = $service->getRegisteredDirectives();
foreach ($directives as $directive) {
    echo $directive->getSignature();
}
```

### `discover(): DirectiveMetadataCollection`

**Retourne :** `DirectiveMetadataCollection` - Collection des métadonnées des directives

Découvre les directives selon la configuration. Retourne soit uniquement les directives enregistrées, soit également celles du système de fichiers si activé.

**Exemple :**
```php
$metadata = $service->discover();
foreach ($metadata as $directive) {
    echo $directive->signature . ': ' . $directive->description;
}
```

## Cas d'utilisation

### Cas 1 : Test unitaire avec directives mockées

**Problème :** Tester un service qui dépend de la découverte de directives sans utiliser le vrai système de fichiers.

```php
class DirectiveExecutorTest extends TestCase
{
    private TestDirectiveDiscoveryService $discovery;
    
    protected function setUp(): void
    {
        $config = DirectiveConfig::default();
        $hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->discovery = new TestDirectiveDiscoveryService($config, $hydrator, true);
    }
    
    public function test_execute_registered_directive(): void
    {
        // Arrange: Register mock directive
        $directive = $this->createMock(AbstractDirective::class);
        $directive->method('getSignature')->willReturn('test:cmd');
        $this->discovery->registerDirective($directive);
        
        // Act: Discover and execute
        $metadata = $this->discovery->discover();
        
        // Assert: Directive is found
        $this->assertCount(1, $metadata);
        $this->assertSame('test:cmd', $metadata->firstItem()->signature);
    }
}
```

### Cas 2 : Test de directives avec dépendances

**Problème :** Tester une directive qui a des dépendances à injecter.

```php
class UserDirectiveTest extends TestCase
{
    public function test_directive_with_dependencies(): void
    {
        // Arrange: Create dependencies
        $userRepository = $this->createMock(UserRepository::class);
        $mailer = $this->createMock(MailerService::class);
        
        // Register directive with constructor args
        $discovery = new TestDirectiveDiscoveryService($config, $hydrator, true);
        $directive = $discovery->registerDirectiveClass(
            CreateUserDirective::class,
            [$userRepository, $mailer]
        );
        
        // Act: Execute directive
        $result = $directive->execute(['name' => 'John']);
        
        // Assert: Verify behavior
        $this->assertTrue($result);
    }
}
```

### Cas 3 : Reset entre les tests

**Problème :** Éviter la pollution entre les tests en réinitialisant l'état.

```php
class DirectiveBatchTest extends TestCase
{
    private TestDirectiveDiscoveryService $discovery;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = new TestDirectiveDiscoveryService($config, $hydrator, true);
    }
    
    protected function tearDown(): void
    {
        $this->discovery->clearRegisteredDirectives();
        parent::tearDown();
    }
    
    public function test_first_scenario(): void
    {
        $this->discovery->registerDirective(new FirstDirective());
        $this->assertCount(1, $this->discovery->getRegisteredDirectives());
    }
    
    public function test_second_scenario(): void
    {
        // Collection is empty after tearDown
        $this->assertCount(0, $this->discovery->getRegisteredDirectives());
    }
}
```

### Cas 4 : Test de découverte avec filesystem activé

**Problème :** Tester l'interaction avec la découverte sur disque.

```php
class FilesystemDiscoveryTest extends TestCase
{
    public function test_discovery_with_filesystem_enabled(): void
    {
        // Arrange: Create service with filesystem discovery enabled
        $discovery = new TestDirectiveDiscoveryService($config, $hydrator, false);
        
        // Register a test directive
        $discovery->registerDirective(new TestDirective());
        
        // Act: Discover (should also scan filesystem)
        $metadata = $discovery->discover();
        
        // Assert: Contains both registered and filesystem directives
        $this->assertGreaterThanOrEqual(1, $metadata->count());
    }
}
```

### Cas 5 : Enregistrement batch depuis une configuration

**Problème :** Enregistrer plusieurs directives à partir d'une liste de classes.

```php
class DirectiveBatchRegistrationTest extends TestCase
{
    private array $directiveClasses = [
        UserCreateDirective::class,
        CacheClearDirective::class,
        ReportGenerateDirective::class,
    ];
    
    public function test_register_all_directives(): void
    {
        $discovery = new TestDirectiveDiscoveryService($config, $hydrator, true);
        
        foreach ($this->directiveClasses as $class) {
            $discovery->registerDirectiveClass($class, [$this->getCommonDependencies()]);
        }
        
        $directives = $discovery->getRegisteredDirectives();
        $this->assertCount(3, $directives);
        
        $signatures = array_map(
            fn($d) => $d->getSignature(), 
            $directives
        );
        
        $this->assertContains('user:create', $signatures);
        $this->assertContains('cache:clear', $signatures);
    }
}
```

## Flux d'exécution

### Découverte sans filesystem (mode test)
<img src="../graphics/test_directive_discovery_service_1.png" width="800" alt="Test Directive Discovery Service Flow" />

### Découverte avec filesystem activé

<img src="../graphics/discover-filesystem-mode.png" width="800" alt="Test  Discovery File System Mode" />

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Classe invalide dans `registerDirectiveClass()` | Exception `ReflectionException` |
| Arguments constructeur insuffisants | Exception `ReflectionException` |
| Directives en double | Accepté - plusieurs directives identiques possibles |
| Filesystem discovery activé sans droits lecture | Les erreurs de lecture sont ignorées silencieusement |

## Intégration

### Avec PHPUnit

```php
use AndyDefer\Directive\Testing\TestDirectiveDiscoveryService;

class CustomTestCase extends UnitTestCase
{
    protected TestDirectiveDiscoveryService $discovery;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = new TestDirectiveDiscoveryService(
            $this->config,
            $this->hydrator,
            true
        );
    }
    
    protected function registerTestDirective(string $class, array $args = []): AbstractDirective
    {
        return $this->discovery->registerDirectiveClass($class, $args);
    }
}
```

### Avec Mockery ou Prophecy

```php
use Mockery as m;

$directive = m::mock(AbstractDirective::class);
$directive->shouldReceive('getSignature')->andReturn('test:cmd');
$directive->shouldReceive('getDescription')->andReturn('Test command');

$discovery->registerDirective($directive);
```

### Pattern Builder pour les tests

```php
class DirectiveTestBuilder
{
    private TestDirectiveDiscoveryService $discovery;
    private array $directives = [];
    
    public function __construct(TestDirectiveDiscoveryService $discovery)
    {
        $this->discovery = $discovery;
    }
    
    public function withDirective(AbstractDirective $directive): self
    {
        $this->directives[] = $directive;
        return $this;
    }
    
    public function withClass(string $className, array $args = []): self
    {
        $this->discovery->registerDirectiveClass($className, $args);
        return $this;
    }
    
    public function build(): TestDirectiveDiscoveryService
    {
        $this->discovery->registerDirectives($this->directives);
        return $this->discovery;
    }
}

// Utilisation
$builder = new DirectiveTestBuilder($discovery);
$service = $builder
    ->withDirective(new UserDirective())
    ->withClass(CacheDirective::class, [$cache])
    ->build();
```

## Performance

- **Complexité temporelle :** O(n) où n est le nombre de directives enregistrées
- **Mémoire :** Stocke toutes les directives en mémoire pendant la durée du test
- **Mode filesystem :** Plus lent mais désactivé par défaut en test

### Benchmarks indicatifs

| Opération | 10 directives | 100 directives |
|-----------|---------------|----------------|
| `registerDirective()` | ~0.5 µs | ~5 µs |
| `registerDirectiveClass()` | ~5 µs | ~50 µs |
| `discover()` | ~1 µs | ~10 µs |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Config\DirectiveConfig;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Testing\TestDirectiveDiscoveryService;
use AndyDefer\Directive\Tests\UnitTestCase;

// ==================== Définition de directives de test ====================

class UserCreateDirective extends AbstractDirective
{
    public function __construct(private UserRepository $users) {}
    
    public function getSignature(): string { return 'user:create'; }
    public function getDescription(): string { return 'Create a new user'; }
    public function getAliases(): array { return ['user:add']; }
    public function execute(array $params): void { /* ... */ }
}

class CacheClearDirective extends AbstractDirective
{
    public function getSignature(): string { return 'cache:clear'; }
    public function getDescription(): string { return 'Clear application cache'; }
    public function getAliases(): array { return ['cache:flush']; }
    public function execute(array $params): void { /* ... */ }
}

// ==================== Configuration ====================

$config = DirectiveConfig::default();
$hydrator = new DirectiveHydratorService();
$discovery = new TestDirectiveDiscoveryService($config, $hydrator, true);

// ==================== Enregistrement de directives ====================

echo "=== Registering Directives ===\n\n";

// Enregistrement par instance
$userDirective = new UserCreateDirective(new UserRepository());
$discovery->registerDirective($userDirective);
echo "✓ Registered UserCreateDirective instance\n";

// Enregistrement par classe
$cacheDirective = $discovery->registerDirectiveClass(CacheClearDirective::class);
echo "✓ Registered CacheClearDirective via class name\n";

// Enregistrement multiple
$discovery->registerDirectives([
    new TestDirective1(),
    new TestDirective2(),
]);
echo "✓ Registered multiple directives\n";

// ==================== Vérification ====================

echo "\n=== Registered Directives ===\n\n";

$directives = $discovery->getRegisteredDirectives();
echo sprintf("Total directives: %d\n\n", count($directives));

foreach ($directives as $directive) {
    echo sprintf(
        "  - %s (%s)\n",
        $directive->getSignature(),
        $directive::class
    );
}

// ==================== Découverte ====================

echo "\n=== Discovery Results ===\n\n";

$metadata = $discovery->discover();

foreach ($metadata as $item) {
    echo sprintf(
        "Signature: %s\nClass: %s\nDescription: %s\nAliases: %s\n\n",
        $item->signature,
        $item->class,
        $item->description ?? '(none)',
        implode(', ', $item->aliases)
    );
}

// ==================== Réinitialisation ====================

echo "=== Reset Demonstration ===\n\n";

echo "Before reset: " . count($discovery->getRegisteredDirectives()) . " directives\n";
$discovery->clearRegisteredDirectives();
echo "After reset: " . count($discovery->getRegisteredDirectives()) . " directives\n";

// ==================== Test avec filesystem activé ====================

echo "\n=== Filesystem Discovery Test ===\n\n";

$discoveryWithFs = new TestDirectiveDiscoveryService($config, $hydrator, false);
$discoveryWithFs->registerDirective($userDirective);

$allDirectives = $discoveryWithFs->discover();
echo sprintf(
    "Total directives (registered + filesystem): %d\n",
    $allDirectives->count()
);

// ==================== Intégration avec PHPUnit ====================

class ExampleTest extends UnitTestCase
{
    private TestDirectiveDiscoveryService $discovery;
    
    protected function setUp(): void
    {
        parent::setUp();
        $config = DirectiveConfig::default();
        $hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->discovery = new TestDirectiveDiscoveryService($config, $hydrator, true);
    }
    
    protected function tearDown(): void
    {
        $this->discovery->clearRegisteredDirectives();
        parent::tearDown();
    }
    
    public function test_directive_registration(): void
    {
        // Arrange
        $directive = $this->createMock(AbstractDirective::class);
        $directive->method('getSignature')->willReturn('test:cmd');
        
        // Act
        $this->discovery->registerDirective($directive);
        
        // Assert
        $this->assertCount(1, $this->discovery->getRegisteredDirectives());
        
        $metadata = $this->discovery->discover();
        $this->assertSame('test:cmd', $metadata->firstItem()->signature);
    }
}

echo "\n✅ All examples completed successfully\n";
```
---
<!-- ==== ./docs/api-reference/testing/interacts-with-directives.md ==== -->

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
<!-- ==== ./docs/api-reference/testing/closure-directive.md ==== -->

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
<!-- ==== ./docs/api-reference/services/directive-testing-service.md ==== -->

# DirectiveTestingService - Référence Technique

## Description

Service de test pour exécuter des directives dans un environnement isolé. Il crée un répertoire temporaire avec un fichier `composer.json` minimal, change le répertoire de travail courant, et exécute les directives dans un environnement sandbox.

## Hiérarchie / Implémentations

```
DirectiveTestingService (final)
```

## Rôle principal

Fournir un environnement de test isolé pour l'exécution des directives. Ce service est conçu pour :
1. Créer un environnement propre et reproductible
2. Éviter les effets de bord sur le projet principal
3. Permettre l'exécution de directives en toute sécurité
4. Nettoyer automatiquement les ressources après les tests

## Installation

### Utilisation en tests

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;

class MyDirectiveTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testingService = new DirectiveTestingService(
            $this->app,
            ['app/Directives'] // Sources supplémentaires
        );
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
}
```

## API / Méthodes publiques

### `run(string $query): DirectiveResponseRecord`

Exécute une directive dans l'environnement de test.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête de la directive à exécuter |

**Retourne :** `DirectiveResponseRecord` - Le résultat de l'exécution (code de sortie + sortie)

**Exceptions :** Aucune (les erreurs sont capturées et retournées dans le record)

**Exemple :**
```php
<?php

$result = $this->testingService->run('list');
echo $result->output; // Affiche la liste des directives
echo $result->exit_code->value; // 0 si succès
```

---

### `getTempDir(): string`

Récupère le chemin du répertoire temporaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Le chemin absolu du répertoire temporaire

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$tempDir = $this->testingService->getTempDir();
echo "Répertoire de test: " . $tempDir . PHP_EOL;
```

---

### `destroy(): void`

Nettoie l'environnement de test.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
<?php

// À la fin du test
$this->testingService->destroy();
```

## Cas d'utilisation

### Cas 1 : Test d'une directive simple

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;

class ListDirectiveTest extends TestCase
{
    private DirectiveTestingService $testing;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testing = new DirectiveTestingService($this->app);
    }
    
    protected function tearDown(): void
    {
        $this->testing->destroy();
        parent::tearDown();
    }
    
    public function test_list_directive()
    {
        $result = $this->testing->run('list');
        
        $this->assertTrue($result->exit_code->isSuccess());
        $this->assertStringContainsString('Available Directives', $result->output);
        $this->assertStringContainsString('list', $result->output);
        $this->assertStringContainsString('help', $result->output);
        $this->assertStringContainsString('version', $result->output);
    }
}
```

### Cas 2 : Test avec sources personnalisées

```php
<?php

class CustomDirectiveTest extends TestCase
{
    private DirectiveTestingService $testing;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ajouter des sources personnalisées
        $this->testing = new DirectiveTestingService(
            $this->app,
            [
                base_path('tests/Fixtures/Directives'),
                base_path('app/Modules/Admin/Directives'),
            ]
        );
    }
    
    public function test_custom_directive()
    {
        $result = $this->testing->run('custom:command --force');
        
        $this->assertTrue($result->exit_code->isSuccess());
        $this->assertStringContainsString('Custom command executed', $result->output);
    }
}
```

### Cas 3 : Test avec assertions sur la sortie

```php
<?php

class OutputFormatTest extends TestCase
{
    private DirectiveTestingService $testing;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testing = new DirectiveTestingService($this->app);
    }
    
    protected function tearDown(): void
    {
        $this->testing->destroy();
        parent::tearDown();
    }
    
    public function test_output_format()
    {
        $result = $this->testing->run('version');
        
        $this->assertTrue($result->exit_code->isSuccess());
        $this->assertStringContainsString('Laravel Directive', $result->output);
        $this->assertStringContainsString('Version:', $result->output);
        $this->assertStringContainsString('PHP:', $result->output);
        $this->assertStringContainsString('Author:', $result->output);
    }
}
```

### Cas 4 : Test des erreurs

```php
<?php

class ErrorHandlingTest extends TestCase
{
    private DirectiveTestingService $testing;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testing = new DirectiveTestingService($this->app);
    }
    
    protected function tearDown(): void
    {
        $this->testing->destroy();
        parent::tearDown();
    }
    
    public function test_directive_not_found()
    {
        $result = $this->testing->run('nonexistent');
        
        $this->assertTrue($result->exit_code->isNotFound());
        $this->assertStringContainsString('Directive not found', $result->output);
    }
    
    public function test_invalid_arguments()
    {
        $result = $this->testing->run('help --invalid-flag');
        // Le comportement dépend de l'implémentation
        // Certaines directives ignorent les flags inconnus
    }
}
```

## Flux d'exécution

```
DirectiveTestingService::__construct()
    │
    ├── getcwd() → $this->originalCwd
    │
    ├── setupTempDirectory()
    │   ├── createTempDirectory()
    │   │   └── sys_get_temp_dir()/directive_test_{uniqid}
    │   ├── createMinimalComposerJson()
    │   │   └── Crée composer.json minimal
    │   └── changeToTempDirectory()
    │       └── chdir($tempDir)
    │
    ├── DiscoveryService → addSource($sourcePaths)
    │
    └── DirectiveKernel → new DirectiveKernel($app, $discovery)

DirectiveTestingService::run($query)
    │
    ├── ob_start()
    │
    ├── $argv = ['directive', ...explode(' ', $query)]
    │
    ├── $exitCode = $this->kernel->run($argv)
    │
    ├── $output = ob_get_clean()
    │
    └── return new DirectiveResponseRecord($exitCode, $output)

DirectiveTestingService::destroy()
    │
    ├── restoreOriginalDirectory()
    │   └── chdir($originalCwd)
    │
    └── removeTempDirectory()
        └── removeDirectory($tempDir) → suppression récursive
```

## Structure du répertoire temporaire

```
/tmp/directive_test_{uniqid}/
├── composer.json
└── (fichiers créés par les tests)
```

### composer.json minimal

```json
{
    "name": "directive-test/app",
    "type": "project",
    "require": {
        "php": "^8.1"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Erreur d'exécution de la directive | Capturée, retournée dans `DirectiveResponseRecord` | Message de l'exception |
| Erreur de création du répertoire | Exception levée | `mkdir(): Permission denied` |
| Erreur de suppression du répertoire | Ignorée silencieusement | - |
| Répertoire original inexistant | Ignoré (ne change pas de répertoire) | - |

### Cas particuliers

```php
// Exécution avec exception
$result = $this->testing->run('failing:command');
// $result->exit_code = ExitCode::RUNTIME_ERROR
// $result->output = "Error message from exception"
```

## Intégration

Le `DirectiveTestingService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveKernel` | Exécution des directives |
| `DirectiveDiscoveryService` | Découverte des directives |
| `DirectiveConfigInterface` | Configuration des chemins |
| `DirectiveResponseRecord` | Résultat de l'exécution |
| `ExitCode` | Codes de retour |

### Utilisation avec PHPUnit

```php
<?php

namespace Tests\Unit;

use AndyDefer\Directive\Services\DirectiveTestingService;
use Tests\TestCase;

abstract class DirectiveTestCase extends TestCase
{
    protected DirectiveTestingService $testing;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testing = new DirectiveTestingService(
            $this->app,
            $this->getExtraSources()
        );
    }
    
    protected function tearDown(): void
    {
        $this->testing->destroy();
        parent::tearDown();
    }
    
    protected function getExtraSources(): array
    {
        return [];
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Temps de setup | 50-100ms | Création du répertoire et composer.json |
| Temps par exécution | 100-500ms | Dépend de la directive exécutée |
| Temps de cleanup | 10-50ms | Suppression du répertoire |
| Mémoire | 1-5 MB | Environnement de test |

### Optimisations

```php
class OptimizedTestingService
{
    private ?string $tempDir = null;
    private bool $initialized = false;
    
    public function __construct(
        private readonly Application $app,
        private readonly array $sourcePaths = [],
    ) {
        // Lazy initialization
    }
    
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        
        $this->setupTempDirectory();
        $this->initialized = true;
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| PHPUnit 9.x | ✅ Complet | - |
| PHPUnit 10.x | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveTestingService;

class DirectiveTestingExample extends TestCase
{
    private DirectiveTestingService $testing;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer le service de test avec des sources personnalisées
        $this->testing = new DirectiveTestingService(
            $this->app,
            [
                base_path('app/Directives'),
                base_path('tests/Fixtures/Directives'),
            ]
        );
    }
    
    protected function tearDown(): void
    {
        // Nettoyer l'environnement de test
        $this->testing->destroy();
        parent::tearDown();
    }
    
    public function test_help_directive()
    {
        $result = $this->testing->run('help');
        
        $this->assertTrue($result->exit_code->isSuccess());
        $this->assertStringContainsString('Global options:', $result->output);
        $this->assertStringContainsString('--help', $result->output);
        $this->assertStringContainsString('--list', $result->output);
        $this->assertStringContainsString('--version', $result->output);
    }
    
    public function test_version_directive()
    {
        $result = $this->testing->run('version');
        
        $this->assertTrue($result->exit_code->isSuccess());
        $this->assertStringContainsString('Package: laravel-directive', $result->output);
        $this->assertStringContainsString('Laravel:', $result->output);
        $this->assertStringContainsString('PHP:', $result->output);
        $this->assertStringContainsString('Author:', $result->output);
    }
    
    public function test_custom_directive_with_arguments()
    {
        // Créer une directive de test dans le répertoire temporaire
        $tempDir = $this->testing->getTempDir();
        $directivePath = $tempDir . '/app/Directives/TestDirective.php';
        
        // Vous pouvez créer des fichiers de test dynamiquement
        // ou utiliser des fixtures pré-existantes
        
        $result = $this->testing->run('test:command --force');
        
        $this->assertTrue($result->exit_code->isSuccess());
    }
    
    public function test_output_capturing()
    {
        $result = $this->testing->run('list');
        
        // Vérifier que la sortie est capturée
        $this->assertNotEmpty($result->output);
        
        // Vérifier les formats de sortie
        $lines = explode("\n", $result->output);
        $this->assertGreaterThan(3, count($lines));
    }
    
    public function test_multiple_executions()
    {
        // Exécuter plusieurs directives dans le même environnement
        $results = [];
        $results[] = $this->testing->run('list');
        $results[] = $this->testing->run('help');
        $results[] = $this->testing->run('version');
        
        foreach ($results as $result) {
            $this->assertTrue($result->exit_code->isSuccess());
        }
    }
}
```

## Notes techniques

### Isolation des tests

Le service garantit l'isolation en :
1. **Créant un répertoire temporaire** : Tous les fichiers sont créés dans `/tmp`
2. **Changeant le répertoire courant** : Les chemins relatifs sont résolus dans le sandbox
3. **Créant un composer.json minimal** : Évite les erreurs de résolution de dépendances
4. **Nettoyant automatiquement** : Supprime tout après les tests

### Gestion des sources

Les sources personnalisées sont ajoutées au `DirectiveDiscoveryService` :

```php
$discovery = $this->app->make(DirectiveDiscoveryService::class);

foreach ($this->sourcePaths as $path) {
    $discovery->addSource($path);
}
```

### Limitations

1. **Pas de support des arguments entre guillemets** : `$query = explode(' ', $query)` ne gère pas les guillemets
2. **Environnement isolé** : Les modifications de fichiers ne persistent pas
3. **Pas de tests d'intégration** : Ne teste pas l'interaction avec le système réel

### Bonnes pratiques

1. **Toujours appeler `destroy()`** : Nettoyer après les tests
2. **Utiliser des assertions de sortie** : Vérifier le contenu de `$result->output`
3. **Tester les cas d'erreur** : Tester les erreurs attendues
4. **Sources personnalisées** : Ajouter des fixtures pour les tests complexes

```php
// ✅ Bonne pratique
public function test_with_fixtures()
{
    $testing = new DirectiveTestingService(
        $this->app,
        [__DIR__ . '/Fixtures/Directives']
    );
    
    $result = $testing->run('fixture:command');
    
    $this->assertTrue($result->exit_code->isSuccess());
    $testing->destroy();
}
```
---
<!-- ==== ./docs/api-reference/services/directive-renderer-service.md ==== -->

# DirectiveRendererService - Référence Technique

## Description

Service façade pour le rendu des différentes sorties de directives (aide, listes, messages, tableaux).

## Hiérarchie

```
Aucune hiérarchie - Classe finale sans extension ni implémentation
```

## Rôle principal

Agit comme une façade (facade pattern) devant `RenderDispatcher`. Fournit des méthodes dédiées et nommées pour chaque type de rendu, gère le rendu conditionnel (ex: messages de debug uniquement en mode développement) et délègue le travail d'affichage réel à `RenderDispatcher`.

## Installation

```bash
composer require andydefer/php-records
```

Le service nécessite une instance de `RenderDispatcher` injectée dans le constructeur.

```php
$renderDispatcher = new RenderDispatcher($rendererStrategy);
$service = new DirectiveRendererService($renderDispatcher);
```

## API / Méthodes publiques

### `renderHelp(): void`

Affiche l'écran d'aide avec les instructions d'utilisation.

**Exemple :**
```php
$service->renderHelp();
// Affiche la page d'aide complète
```

### `renderList(DirectiveMetadataCollection $directives): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directives` | `DirectiveMetadataCollection` | Collection des métadonnées des directives à afficher |

Affiche une liste des directives disponibles.

**Exemple :**
```php
$directives = new DirectiveMetadataCollection();
$directives->add(new DirectiveMetadataRecord(name: 'user:create', description: 'Create a user'));
$directives->add(new DirectiveMetadataRecord(name: 'cache:clear', description: 'Clear cache'));

$service->renderList($directives);
```

### `renderNotFound(string $signature): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive non trouvée |

Affiche un message d'erreur indiquant qu'une directive n'existe pas.

**Exemple :**
```php
$service->renderNotFound('unknown:command');
// Affiche: "Directive 'unknown:command' not found"
```

### `renderSuccess(string $message): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message de succès à afficher |

Affiche un message de succès (généralement en vert).

**Exemple :**
```php
$service->renderSuccess('User created successfully');
```

### `renderError(string $message): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message d'erreur à afficher |

Affiche un message d'erreur (généralement en rouge).

**Exemple :**
```php
$service->renderError('Failed to connect to database');
```

### `renderWarning(string $message): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message d'avertissement à afficher |

Affiche un message d'avertissement (généralement en jaune).

**Exemple :**
```php
$service->renderWarning('Deprecated feature used');
```

### `renderDebug(string $message): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Message de debug à afficher |

Affiche un message de debug **uniquement si** `DIRECTIVE_DEBUG=true` ou `APP_DEBUG=true` dans l'environnement.

⚠️ **Important :** Seule la valeur exacte `'true'` active le debug. Les valeurs `'1'`, `'yes'`, `'on'` ne sont pas reconnues.

**Exemple :**
```php
// En mode debug activé
putenv('DIRECTIVE_DEBUG=true');
$service->renderDebug('User ID: 42'); // Affiche le message

// En mode debug désactivé
putenv('DIRECTIVE_DEBUG=false');
$service->renderDebug('User ID: 42'); // N'affiche rien
```

### `renderVersion(): void`

Affiche la version du package.

**Exemple :**
```php
$service->renderVersion();
// Affiche: "php-records v1.0.0"
```

### `renderConflict(ConflictDisplayRecord $record): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `ConflictDisplayRecord` | Enregistrement contenant les données du conflit |

Affiche les conflits de nommage entre directives.

**Exemple :**
```php
$conflict = new ConflictDisplayRecord(
    name: 'create',
    classNames: new StringTypedCollection('UserCreator', 'PostCreator'),
    signatures: new StringTypedCollection('user:create', 'post:create'),
    descriptions: new StringTypedCollection('Create user', 'Create post'),
);

$service->renderConflict($conflict);
```

### `renderTable(DisplayTableRecord $record): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `DisplayTableRecord` | Enregistrement contenant les données du tableau |

Affiche un tableau formaté dans la console.

**Exemple :**
```php
$headers = new StringTypedCollection('Name', 'Email', 'Role');
$rows = new RowCollection();

$row1 = new RowCollection();
$row1->add('John Doe', 'john@example.com', 'Admin');
$rows->add($row1);

$row2 = new RowCollection();
$row2->add('Jane Smith', 'jane@example.com', 'User');
$rows->add($row2);

$table = new DisplayTableRecord(headers: $headers, rows: $rows);
$service->renderTable($table);
```

### `renderValidationError(ValidationResultRecord $record): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `ValidationResultRecord` | Enregistrement contenant l'erreur de validation |

Affiche une erreur de validation de signature.

**Exemple :**
```php
$validationError = new ValidationResultRecord(
    isValid: false,
    error: 'Invalid signature format: Required arguments must come before optional arguments',
);

$service->renderValidationError($validationError);
```

## Cas d'utilisation

### Cas 1 : Application console basique

**Problème :** Afficher un message de succès après exécution d'une commande.

```php
class CreateUserCommand
{
    public function __construct(
        private DirectiveRendererService $renderer,
        private UserRepository $repository,
    ) {}
    
    public function execute(string $name, string $email): void
    {
        try {
            $this->repository->create($name, $email);
            $this->renderer->renderSuccess("User {$name} created");
        } catch (Exception $e) {
            $this->renderer->renderError($e->getMessage());
        }
    }
}
```

### Cas 2 : Application avec affichage de liste

**Problème :** Lister toutes les commandes disponibles avec leurs descriptions.

```php
class ListDirectivesCommand
{
    public function __construct(
        private DirectiveRendererService $renderer,
        private DirectiveRegistry $registry,
    ) {}
    
    public function execute(): void
    {
        $directives = $this->registry->getAll();
        
        if ($directives->isEmpty()) {
            $this->renderer->renderWarning('No directives registered');
            return;
        }
        
        $this->renderer->renderList($directives);
    }
}
```

### Cas 3 : Débogage conditionnel

**Problème :** Ajouter des logs de debug qui ne s'affichent qu'en environnement de développement.

```php
class CacheService
{
    public function __construct(
        private DirectiveRendererService $renderer,
    ) {}
    
    public function clear(): void
    {
        $this->renderer->renderDebug('Starting cache clear operation');
        
        // Opération longue
        sleep(2);
        
        $this->renderer->renderDebug('Cache cleared successfully');
        $this->renderer->renderSuccess('Cache cleared');
    }
}
```

### Cas 4 : Génération de rapport tabulaire

**Problème :** Afficher une liste d'utilisateurs formatée en tableau.

```php
class ListUsersCommand
{
    public function __construct(
        private DirectiveRendererService $renderer,
        private UserRepository $repository,
    ) {}
    
    public function execute(): void
    {
        $users = $this->repository->findAll();
        
        $headers = new StringTypedCollection('ID', 'Name', 'Email', 'Status');
        $rows = new RowCollection();
        
        foreach ($users as $user) {
            $row = new RowCollection();
            $row->add($user->id, $user->name, $user->email, $user->isActive() ? 'Active' : 'Inactive');
            $rows->add($row);
        }
        
        $table = new DisplayTableRecord(headers: $headers, rows: $rows);
        $this->renderer->renderTable($table);
    }
}
```

### Cas 5 : Validation et messages d'erreur contextuels

**Problème :** Valider une signature et afficher les erreurs spécifiques.

```php
class SignatureValidator
{
    public function __construct(
        private DirectiveRendererService $renderer,
    ) {}
    
    public function validate(string $signature): bool
    {
        $result = $this->performValidation($signature);
        
        if (!$result->isValid) {
            $this->renderer->renderValidationError($result);
            return false;
        }
        
        $this->renderer->renderSuccess('Signature is valid');
        return true;
    }
    
    private function performValidation(string $signature): ValidationResultRecord
    {
        // Logique de validation...
        if (str_contains($signature, '{')) {
            return new ValidationResultRecord(false, 'Invalid braces in signature');
        }
        
        return new ValidationResultRecord(true, '');
    }
}
```

## Flux d'exécution
<img src="../graphics/directive_renderer_service.png" width="800" alt="Directive Renderer Service Flow">

## Gestion des erreurs

Aucune exception n'est levée directement par ce service. Les erreurs sont gérées en interne par `RenderDispatcher` et ses stratégies.

| Situation | Comportement |
|-----------|--------------|
| `RenderDispatcher::execute()` échoue | L'exception remonte jusqu'à l'appelant |
| Debug désactivé | `renderDebug()` ne fait rien (retour silencieux) |
| Collection vide pour `renderList()` | Affiche "No directives available" ou message similaire |
| Tableau sans données | Affiche "No data to display" |

## Intégration

### Avec RenderDispatcher

```php
// RenderDispatcher attend une stratégie de rendu
$renderStrategy = new ConsoleRenderStrategy(); // Implémente RenderStrategyInterface
$renderDispatcher = new RenderDispatcher($renderStrategy);

$renderer = new DirectiveRendererService($renderDispatcher);
```

### Avec d'autres services

```php
class DirectiveApplication
{
    public function __construct(
        private DirectiveParserService $parser,
        private DirectiveRendererService $renderer,
        private DirectiveExecutor $executor,
    ) {}
    
    public function run(string $signature, array $argv): void
    {
        try {
            $parsed = $this->parser->parse($signature, $argv);
            $result = $this->executor->execute($parsed);
            
            $this->renderer->renderSuccess($result->message);
        } catch (InvalidArgumentException $e) {
            $this->renderer->renderError($e->getMessage());
            $this->renderer->renderHelp();
        }
    }
}
```

### Configuration des variables d'environnement

```bash
# Activer le debug (une des deux suffit)
export DIRECTIVE_DEBUG=true
export APP_DEBUG=true

# Désactiver le debug
export DIRECTIVE_DEBUG=false
# ou simplement ne pas définir la variable
```

## Performance

- **Complexité :** O(1) par appel - pas de boucle ni de traitement lourd
- **Allocation mémoire :** Crée un `RenderRecord` par appel (léger)
- **Condition debug :** `getenv()` appelé à chaque `renderDebug()` (pénalité minime)
- **Recommandation :** Pour des appels très fréquents (>1000/s), mettre en cache l'état du debug

```php
class DirectiveRendererService
{
    private bool $debugEnabled;
    
    public function __construct(RenderDispatcher $renderDispatcher)
    {
        $this->renderDispatcher = $renderDispatcher;
        $this->debugEnabled = getenv('DIRECTIVE_DEBUG') === 'true' 
                           || getenv('APP_DEBUG') === 'true';
    }
}
```

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ⚠️ Limité (retourne `void`, propriétés `readonly`) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Collections\DirectiveMetadataCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Records\ConflictDisplayRecord;
use AndyDefer\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\Directive\Records\DisplayTableRecord;
use AndyDefer\Directive\Records\ValidationResultRecord;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// Configuration
$renderDispatcher = new RenderDispatcher($consoleStrategy);
$renderer = new DirectiveRendererService($renderDispatcher);

// Activer le debug pour cet exemple
putenv('DIRECTIVE_DEBUG=true');

// 1. Message de bienvenue
$renderer->renderSuccess('Welcome to MyApp v1.0');
$renderer->renderVersion();

// 2. Lister les commandes disponibles
$commands = new DirectiveMetadataCollection();
$commands->add(new DirectiveMetadataRecord('user:create', 'Create a new user', 'user:create {name} {email}'));
$commands->add(new DirectiveMetadataRecord('cache:clear', 'Clear application cache', 'cache:clear {--force}'));
$commands->add(new DirectiveMetadataRecord('report:generate', 'Generate report', 'report:generate {type=summary}'));

$renderer->renderList($commands);

// 3. Message de debug (ne s'affiche que si DEBUG=true)
$renderer->renderDebug('Loading configuration...');
$renderer->renderDebug('Connecting to database...');

// 4. Simuler une création d'utilisateur
$renderer->renderSuccess('User "john_doe" created successfully');
$renderer->renderWarning('Password policy will be enforced in next version');

// 5. Afficher une erreur de validation
$validationError = new ValidationResultRecord(
    isValid: false,
    error: 'Missing required argument: "name"',
);
$renderer->renderValidationError($validationError);

// 6. Afficher un conflit de nommage
$conflict = new ConflictDisplayRecord(
    name: 'create',
    classNames: new StringTypedCollection('UserCommand', 'PostCommand'),
    signatures: new StringTypedCollection('user:create', 'post:create'),
    descriptions: new StringTypedCollection('Create user', 'Create post'),
);
$renderer->renderConflict($conflict);

// 7. Afficher un tableau d'utilisateurs
$headers = new StringTypedCollection('ID', 'Username', 'Email', 'Role');
$rows = new RowCollection();

$row1 = new RowCollection();
$row1->add('1', 'john_doe', 'john@example.com', 'Admin');
$rows->add($row1);

$row2 = new RowCollection();
$row2->add('2', 'jane_smith', 'jane@example.com', 'User');
$rows->add($row2);

$table = new DisplayTableRecord(headers: $headers, rows: $rows);
$renderer->renderTable($table);

// 8. Gérer une commande non trouvée
$renderer->renderNotFound('unknown:command');

// 9. Aide
$renderer->renderHelp();
```
---
<!-- ==== ./docs/api-reference/services/signature-validation-service.md ==== -->

# SignatureValidationService - Référence Technique

## Description

Valide les signatures de directives pour garantir leur conformité avec le format attendu.

## Hiérarchie

```
Aucune hiérarchie - Classe finale sans extension ni implémentation
```

## Rôle principal

Assure que les noms de directives respectent les règles de formatage :
- Commencent par une lettre
- Contiennent uniquement des lettres, chiffres et tirets
- Pas de tirets consécutifs
- Ne se terminent pas par un tiret

Accepte également les cas spéciaux comme les options longues (`--help`) et les options courtes (`-v`).

## Installation

```bash
composer require andydefer/php-records
```

Aucune configuration supplémentaire requise.

```php
$validator = new SignatureValidationService();
$result = $validator->validate('user-create');
```

## API / Méthodes publiques

### `validate(string $signature): ValidationResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive à valider |

**Retourne :** `ValidationResultRecord` - Enregistrement contenant le statut de validation et un message d'erreur si invalide

**Exceptions :** Aucune

**Exemple :**
```php
$validator = new SignatureValidationService();

$result = $validator->validate('user-create');
if ($result->isValid) {
    echo "Signature valide";
} else {
    echo "Erreur : " . $result->error;
}
```

## Cas d'utilisation

### Cas 1 : Validation d'une commande avant enregistrement

**Problème :** Vérifier qu'une nouvelle commande respecte les conventions de nommage avant de l'ajouter au registre.

```php
class DirectiveRegistry
{
    private SignatureValidationService $validator;
    private array $directives = [];
    
    public function register(string $name, callable $handler): void
    {
        $result = $this->validator->validate($name);
        
        if (!$result->isValid) {
            throw new InvalidArgumentException($result->error);
        }
        
        $this->directives[$name] = $handler;
    }
}
```

### Cas 2 : Validation interactive dans un assistant de création

**Problème :** Guider l'utilisateur dans la création d'une nouvelle commande avec validation en temps réel.

```php
class DirectiveCreator
{
    private SignatureValidationService $validator;
    
    public function createInteractive(): void
    {
        do {
            $name = readline("Enter directive name: ");
            $result = $this->validator->validate($name);
            
            if (!$result->isValid) {
                echo "Error: " . $result->error . "\n";
                echo "Examples: user-create, cache-clear, db-migrate\n";
            }
        } while (!$result->isValid);
        
        // Créer la directive avec le nom valide
        $this->createDirective($name);
    }
}
```

### Cas 3 : Filtrage des options spéciales

**Problème :** Distinguer les noms de commandes standards des options système comme `--help` ou `-v`.

```php
class CommandRouter
{
    private SignatureValidationService $validator;
    
    public function route(string $input): void
    {
        $result = $this->validator->validate($input);
        
        if (!$result->isValid) {
            $this->showError($result->error);
            return;
        }
        
        // Les options spéciales sont valides mais ne sont pas des commandes
        if ($input === '--help' || $input === '-h') {
            $this->showHelp();
            return;
        }
        
        $this->executeCommand($input);
    }
}
```

### Cas 4 : Validation batch de plusieurs signatures

**Problème :** Valider un lot de signatures et collecter toutes les erreurs.

```php
class BatchValidator
{
    private SignatureValidationService $validator;
    
    public function validateAll(array $signatures): array
    {
        $errors = [];
        
        foreach ($signatures as $signature) {
            $result = $this->validator->validate($signature);
            
            if (!$result->isValid) {
                $errors[$signature] = $result->error;
            }
        }
        
        return $errors;
    }
}
```

### Cas 5 : Intégration dans un système de plugins

**Problème :** Vérifier que les plugins tiers respectent les conventions de nommage.

```php
class PluginLoader
{
    private SignatureValidationService $validator;
    
    public function loadPlugin(string $pluginClass): void
    {
        $plugin = new $pluginClass();
        $commandName = $plugin->getCommandName();
        
        $result = $this->validator->validate($commandName);
        
        if (!$result->isValid) {
            throw new PluginException(
                "Plugin '{$pluginClass}' has invalid command name '{$commandName}': {$result->error}"
            );
        }
        
        $this->registerPlugin($commandName, $plugin);
    }
}
```

## Flux d'exécution

<img src="../graphics/signature_validation_service.png" width="800" alt="Signature Validation Service" />

## Règles de validation

### Format standard

| Règle | Description | Exemple valide | Exemple invalide |
|-------|-------------|----------------|------------------|
| Commence par une lettre | `[a-zA-Z]` | `user-create` | `1user` |
| Caractères autorisés | lettres, chiffres, tirets | `api-v2` | `user_create` |
| Pas de tirets consécutifs | `--` interdit | `user-create` | `user--create` |
| Pas de tiret final | ne peut pas se terminer par `-` | `user-create` | `user-create-` |

### Options spéciales

| Type | Format | Exemples |
|------|--------|----------|
| Option longue | `--` + lettre(s) | `--help`, `--force`, `--verbose` |
| Option courte | `-` + lettre | `-h`, `-v`, `-f` |
| Options courtes groupées | `-` + lettres multiples | `-vfh`, `-la` |

## Gestion des erreurs

| Situation | Message d'erreur |
|-----------|------------------|
| Signature vide | `Directive name cannot be empty` |
| Format invalide | `Invalid directive name: "{name}". Use only letters, numbers, and hyphens. Must start with a letter. No spaces. Examples: user-create, clean-log, db-migrate-fresh` |
| Tirets consécutifs | `Invalid directive name: "{name}". Cannot have consecutive hyphens` |
| Tiret final | `Invalid directive name: "{name}". Cannot end with a hyphen` |

## Intégration

### Avec ShortOption enum

```php
// Délégation des short options à l'enum
if (ShortOption::isValid($signature)) {
    return $this->createValidResult();
}
```

### Avec ValidationResultRecord

```php
$result = $validator->validate('user-create');

// Vérification simple
if ($result->isValid) {
    // Procéder
}

// Récupération de l'erreur
if (!$result->isValid) {
    $this->logger->error($result->error);
}
```

### Dans une application console

```php
class ConsoleApplication
{
    private SignatureValidationService $validator;
    private CommandRegistry $registry;
    
    public function run(string $input): void
    {
        // Extraire le nom de la commande
        $parts = explode(' ', trim($input));
        $commandName = $parts[0];
        
        // Valider
        $result = $this->validator->validate($commandName);
        
        if (!$result->isValid) {
            echo "Error: " . $result->error . "\n";
            echo "Type 'help' for available commands.\n";
            return;
        }
        
        // Exécuter
        $this->registry->execute($commandName, array_slice($parts, 1));
    }
}
```

## Performance

- **Complexité temporelle :** O(n) où n est la longueur de la signature
- **Opérations :**
  - 1 expression régulière (validité du format)
  - 3 recherches de chaîne (`str_starts_with`, `str_contains`, `str_ends_with`)
  - Délégation à `ShortOption::isValid()` pour les options courtes
- **Optimisation :** Les validations sont chaînées par ordre de complexité croissante (les cas les plus simples sont traités d'abord)

### Benchmark indicatif

| Longueur signature | Temps moyen |
|-------------------|-------------|
| 10 caractères | ~0.5 µs |
| 50 caractères | ~1.2 µs |
| 100 caractères | ~2.0 µs |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.3+ | ✅ Complet (constantes typées) |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ⚠️ Limité (retourne `static` au lieu de `self`) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\SignatureValidationService;

$validator = new SignatureValidationService();

// ==================== Tests de validation ====================

$testCases = [
    // Valides
    'user-create' => true,
    'list' => true,
    'db-migrate-fresh' => true,
    'api-v2' => true,
    'user-create2' => true,
    '--help' => true,
    '-h' => true,
    '-vf' => true,
    'a' => true,
    'UserCreate' => true,
    
    // Invalides
    '' => false,
    'user create' => false,
    'user@create' => false,
    'user:create' => false,
    'user_create' => false,
    '123-user' => false,
    '-user' => false,
    'user--create' => false,
    'user-create-' => false,
    '123' => false,
    'user$create' => false,
];

echo "Signature Validation Tests\n";
echo "==========================\n\n";

foreach ($testCases as $signature => $shouldBeValid) {
    $result = $validator->validate($signature);
    $status = $result->isValid ? '✓ VALID' : '✗ INVALID';
    $expected = $shouldBeValid ? 'VALID' : 'INVALID';
    $match = ($result->isValid === $shouldBeValid) ? '✅' : '❌';
    
    echo sprintf(
        "%s %-20s : %s (expected: %s)\n",
        $match,
        "'{$signature}'",
        $status,
        $expected
    );
    
    if (!$result->isValid) {
        echo "    Error: {$result->error}\n";
    }
}

// ==================== Utilisation pratique ====================

echo "\n\nPractical Usage Example\n";
echo "=======================\n\n";

function registerCommand(SignatureValidationService $validator, string $name, callable $handler): void
{
    $result = $validator->validate($name);
    
    if (!$result->isValid) {
        throw new InvalidArgumentException(
            "Cannot register command '{$name}': {$result->error}"
        );
    }
    
    echo "✓ Command '{$name}' registered successfully\n";
    // Stocker le handler...
}

// Enregistrement de commandes
try {
    registerCommand($validator, 'cache-clear', fn() => 'Clearing cache...');
    registerCommand($validator, 'user:create', fn() => 'Creating user...'); // Invalide
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ==================== Filtrage des options ====================

echo "\nSpecial Options Detection\n";
echo "========================\n";

$inputs = ['--help', '-v', '-vf', 'user-create', '--'];

foreach ($inputs as $input) {
    $result = $validator->validate($input);
    
    if ($result->isValid && (str_starts_with($input, '--') || str_starts_with($input, '-'))) {
        echo "✓ '{$input}' is a valid special option\n";
    } elseif ($result->isValid) {
        echo "✓ '{$input}' is a valid command name\n";
    } else {
        echo "✗ '{$input}' is invalid: {$result->error}\n";
    }
}
```
---
<!-- ==== ./docs/api-reference/services/dependancy-resolver-service.md ==== -->

# DependencyResolverService - Référence Technique

## Description

Service de résolution récursive des dépendances Composer. Analyse l'arborescence complète des dépendances d'un projet à partir du fichier `composer.json`.

## Hiérarchie / Implémentations

```
DependencyResolverInterface
    └── DependencyResolverService
```

## Rôle principal

`DependencyResolverService` parcourt récursivement toutes les dépendances d'un projet en lisant les fichiers `composer.json` de chaque package installé dans le dossier `vendor`. Il permet de :

- Résoudre l'intégralité de l'arbre des dépendances
- Détecter les dépendances circulaires
- Obtenir une vue plate ou arborescente des dépendances

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `ComposerReaderInterface` - Lecture du `composer.json` racine
- `FileSystemInterface` - Opérations sur le système de fichiers
- PHP 8.1+

## API / Méthodes publiques

### `__construct(ComposerReaderInterface $composerReader, FileSystemInterface $fileSystem)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$composerReader` | `ComposerReaderInterface` | Service de lecture du composer.json racine |
| `$fileSystem` | `FileSystemInterface` | Service de système de fichiers |

**Retourne :** `void`

**Exemple :**
```php
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);
```

---

### `resolveAll(): array`

Résout toutes les dépendances du projet de manière récursive.

**Retourne :** `array<string, array>` - Tableau associatif [nom du package => données du composer.json]

**Exceptions :** Aucune (les erreurs sont ignorées silencieusement)

**Exemple :**
```php
$dependencies = $resolver->resolveAll();
// [
//     'andydefer/domain-structures' => [...],
//     'andydefer/php-vo' => [...],
//     'laravel/framework' => [...],
// ]
```

---

### `resolvePackageDependencies(string $package): array`

Résout les dépendances d'un package spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$package` | `string` | Nom complet du package (ex: `andydefer/domain-structures`) |

**Retourne :** `array<string, array>` - Dépendances du package

**Exceptions :** Aucune

**Exemple :**
```php
$deps = $resolver->resolvePackageDependencies('andydefer/domain-structures');
// ['andydefer/php-vo' => [...], 'andydefer/php-utils' => [...]]
```

---

### `getDependencyTree(): array`

Retourne l'arbre complet des dépendances sous forme hiérarchique.

**Retourne :** `array<string, array>` - Arbre des dépendances

**Exceptions :** Aucune

**Exemple :**
```php
$tree = $resolver->getDependencyTree();
// [
//     'andydefer/domain-structures' => [
//         'andydefer/php-vo' => [
//             'andydefer/php-utils' => []
//         ]
//     ]
// ]
```

---

### `getFlatDependencies(): StringTypedCollection`

Retourne une collection plate de tous les noms de packages.

**Retourne :** `StringTypedCollection` - Collection des noms de packages

**Exceptions :** Aucune

**Exemple :**
```php
$packages = $resolver->getFlatDependencies();
// StringTypedCollection ['andydefer/domain-structures', 'andydefer/php-vo', ...]
```

---

### `hasCircularDependency(): bool`

Vérifie la présence de dépendances circulaires dans l'arbre.

**Retourne :** `bool` - `true` si une dépendance circulaire est détectée

**Exceptions :** Aucune

**Exemple :**
```php
if ($resolver->hasCircularDependency()) {
    echo "Circular dependency detected!";
}
```

---

## Cas d'utilisation

### Cas 1 : Analyse complète des dépendances

```php
<?php

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\PhpServices\Services\FileSystemService;

$fileSystem = new FileSystemService();
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);

$allDependencies = $resolver->resolveAll();

foreach ($allDependencies as $package => $data) {
    echo "Package: $package\n";
    echo "Version: " . ($data['version'] ?? 'unknown') . "\n";
    echo "Requires: " . implode(', ', array_keys($data['require'] ?? [])) . "\n";
    echo "---\n";
}
```

### Cas 2 : Détection des dépendances circulaires

```php
<?php

if ($resolver->hasCircularDependency()) {
    // Récupérer l'arbre pour identifier le cycle
    $tree = $resolver->getDependencyTree();
    
    // Analyser l'arbre pour trouver le cycle
    function findCycle(array $tree, array $path = []): ?array
    {
        foreach ($tree as $package => $children) {
            if (in_array($package, $path, true)) {
                return array_merge($path, [$package]);
            }
            
            $cycle = findCycle($children, array_merge($path, [$package]));
            if ($cycle !== null) {
                return $cycle;
            }
        }
        
        return null;
    }
    
    $cycle = findCycle($tree);
    if ($cycle) {
        echo "Cycle detected: " . implode(' → ', $cycle) . "\n";
    }
}
```

### Cas 3 : Génération d'un rapport de dépendances

```php
<?php

$packages = $resolver->getFlatDependencies();

echo "Total packages: " . $packages->count() . "\n";
echo "Packages:\n";

foreach ($packages as $package) {
    echo "- $package\n";
}

// Vérifier un package spécifique
if ($packages->contains('andydefer/domain-structures')) {
    echo "\ndomain-structures is present in the project";
}
```

---

## Flux d'exécution

```
resolveAll()
    ↓
getRequire() (via ComposerReader)
    ↓
Pour chaque package racine
    ↓
resolvePackage($package)
    ├── Vérifier si déjà visité
    ├── Marquer comme visité
    ├── Lire composer.json du package
    ├── Stocker les données
    └── Résoudre récursivement les dépendances
    ↓
Retourner tous les packages résolus
```

### Détection des cycles

```
detectCycle($package, $path)
    ├── Si $package dans $path → cycle détecté
    ├── Ajouter $package au $path
    ├── Lire composer.json du package
    ├── Pour chaque dépendance
    │   └── detectCycle($dependency, $path)
    └── Retourner false (aucun cycle)
```

---

## Gestion des erreurs

Aucune exception n'est levée. Les erreurs sont gérées silencieusement :

| Situation | Comportement |
|-----------|--------------|
| Package non trouvé | Ignoré (continuation) |
| composer.json manquant | Ignoré (continuation) |
| Fichier non lisible | Ignoré (continuation) |
| JSON invalide | Ignoré (continuation) |
| Dépendance circulaire | Détectée via `hasCircularDependency()` |

---

## Intégration

### Avec ComposerReaderService

```php
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);
```

### Dans un framework Laravel

```php
// ServiceProvider
$this->app->singleton(DependencyResolverInterface::class, function ($app) {
    return new DependencyResolverService(
        $app->make(ComposerReaderInterface::class),
        $app->make(FileSystemInterface::class)
    );
});
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `resolveAll()` | O(n²) | n = nombre de packages |
| `resolvePackageDependencies()` | O(n) | Résolution d'un package |
| `getDependencyTree()` | O(n²) | Construction récursive de l'arbre |
| `getFlatDependencies()` | O(n) | Aplatissement des résultats |
| `hasCircularDependency()` | O(n²) | Détection de cycles |

**Optimisations :**
- Visite unique des packages (évite les boucles infinies)
- Pas de relecture des fichiers déjà visités
- Les dépendances sont mises en cache dans `$resolved`

---

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\PhpServices\Services\FileSystemService;

$fileSystem = new FileSystemService();
$reader = new ComposerReaderService(getcwd(), $fileSystem);
$resolver = new DependencyResolverService($reader, $fileSystem);

// 1. Résoudre toutes les dépendances
$allDependencies = $resolver->resolveAll();
echo "=== All Dependencies ===\n";
echo "Total: " . count($allDependencies) . " packages\n\n";

// 2. Afficher les dépendances directes
$directDependencies = $reader->getRequire();
echo "=== Direct Dependencies ===\n";
foreach ($directDependencies as $package => $version) {
    $deps = $resolver->resolvePackageDependencies($package);
    echo "$package ($version) depends on " . count($deps) . " package(s)\n";
}

// 3. Obtenir l'arbre
$tree = $resolver->getDependencyTree();
echo "\n=== Dependency Tree ===\n";
print_r($tree);

// 4. Collection plate
$flatPackages = $resolver->getFlatDependencies();
echo "\n=== Flat List ===\n";
echo "Packages: " . implode(', ', $flatPackages->toArray()) . "\n";

// 5. Vérifier les cycles
if ($resolver->hasCircularDependency()) {
    echo "\n⚠️ Circular dependency detected!\n";
} else {
    echo "\n✅ No circular dependencies found\n";
}

// 6. Vérifier un package spécifique
$packages = $resolver->getFlatDependencies();
if ($packages->contains('andydefer/domain-structures')) {
    echo "\ndomain-structures is installed\n";
}
```

## Voir aussi

- `ComposerReaderService` - Lecture du composer.json
- `ComposerReaderInterface` - Interface du lecteur
- `FileSystemService` - Service de système de fichiers
- `StringTypedCollection` - Collection typée de chaînes
---
<!-- ==== ./docs/api-reference/services/directive-discovery-service.md ==== -->

# DirectiveDiscoveryService - Référence Technique

## Description

Service d'orchestration qui découvre et gère les classes de directives provenant de multiples sources. Il coordonne la découverte depuis les sources intégrées, le workspace, les packages vendors et les sources personnalisées.

## Hiérarchie / Implémentations

```
DirectiveDiscoveryService (final)
```

## Rôle principal

Agir comme un orchestrateur central qui :
1. Agrège les directives de toutes les sources
2. Filtre les signatures réservées
3. Déduplique les directives
4. Fournit une collection unifiée de toutes les directives disponibles

## Installation

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
    return new DirectiveDiscoveryService(
        $app->make(BuiltInDirectiveDiscovery::class),
        $app->make(WorkspaceDirectiveDiscovery::class),
        $app->make(VendorDirectiveDiscovery::class),
        $app->make(DirectiveParserInterface::class),
        $app->make(DirectiveScannerInterface::class),
        $app->make(FileSystemInterface::class),
        $app->make(DirectiveConfigInterface::class),
        3 // maxDepth
    );
});
```

## API / Méthodes publiques

### `addSource(string $directory): self`

Ajoute un répertoire source personnalisé à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Chemin du répertoire à scanner |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addSource('app/CustomDirectives');
```

---

### `addSources(array $directories): self`

Ajoute plusieurs répertoires sources personnalisés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directories` | `array<int, string>` | Liste des chemins à scanner |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addSources([
    'app/CustomDirectives',
    'modules/Admin/Directives',
    'packages/Acme/Directives',
]);
```

---

### `discover(): DirectiveMetadataCollection`

Découvre toutes les directives disponibles depuis toutes les sources.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `DirectiveMetadataCollection` - Collection des directives découvertes

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$directives = $discovery->discover();

foreach ($directives as $directive) {
    echo $directive->signature . ': ' . $directive->description . PHP_EOL;
}
```

---

### `addReservedSignature(string $signature): self`

Ajoute une signature à la liste des réservées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La signature à réserver |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addReservedSignature('my-command');
```

---

### `removeReservedSignature(string $signature): self`

Retire une signature de la liste des réservées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La signature à retirer |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->removeReservedSignature('help');
```

---

### `getReservedSignatures(): array`

Récupère la liste des signatures réservées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des signatures réservées

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$reserved = $discovery->getReservedSignatures();
// ['-h', '--help', '-v', '--version', ...]
```

## Cas d'utilisation

### Cas 1 : Découverte des directives dans une application

```php
<?php

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Dans un contrôleur ou un service
$discovery = app(DirectiveDiscoveryService::class);
$directives = $discovery->discover();

foreach ($directives as $directive) {
    echo "Signature: " . $directive->signature . PHP_EOL;
    echo "Classe: " . $directive->class . PHP_EOL;
    echo "Description: " . $directive->description . PHP_EOL;
    
    if ($directive->aliases->isNotEmpty()) {
        echo "Alias: " . $directive->aliases->join(', ') . PHP_EOL;
    }
    echo PHP_EOL;
}
```

### Cas 2 : Ajout de sources dynamiques

```php
<?php

class ModuleServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $discovery = $this->app->make(DirectiveDiscoveryService::class);
        
        // Ajouter les directives des modules actifs
        foreach ($this->getActiveModules() as $module) {
            $path = base_path("modules/{$module}/Directives");
            
            if (is_dir($path)) {
                $discovery->addSource($path);
            }
        }
    }
    
    private function getActiveModules(): array
    {
        return ['Admin', 'Api', 'Blog'];
    }
}
```

### Cas 3 : Gestion des signatures réservées

```php
<?php

$discovery = app(DirectiveDiscoveryService::class);

// Ajouter une signature réservée
$discovery->addReservedSignature('import');

// Retirer une signature réservée
$discovery->removeReservedSignature('version');

// Voir les signatures réservées
$reserved = $discovery->getReservedSignatures();
```

### Cas 4 : Tests de découverte

```php
<?php

class DirectiveDiscoveryTest extends TestCase
{
    public function test_discover_directives()
    {
        $discovery = $this->app->make(DirectiveDiscoveryService::class);
        
        $directives = $discovery->discover();
        
        $this->assertGreaterThan(0, $directives->count());
        $this->assertInstanceOf(DirectiveMetadataCollection::class, $directives);
        
        // Vérifier que les directives intégrées sont présentes
        $signatures = $directives->pluck('signature')->toArray();
        $this->assertContains('list', $signatures);
        $this->assertContains('help', $signatures);
        $this->assertContains('version', $signatures);
    }
}
```

## Flux d'exécution

```
DirectiveDiscoveryService::discover()
    │
    ├── discoverBuiltInDirectives()
    │   └── $builtInSource->discover()
    │       └── addDirective($fqcn, true)  ← force = true (prioritaire)
    │
    ├── discoverWorkspaceDirectives()
    │   └── $workspaceSource->discover()
    │       └── addDirective($fqcn, false)
    │
    ├── discoverVendorDirectives()
    │   └── $vendorSource->discover()
    │       └── addDirective($fqcn, false)
    │
    ├── discoverCustomDirectives()
    │   ├── foreach($customSources)
    │   │   ├── $scanner->scan($directory)
    │   │   └── addDirective($fqcn, false)
    │   └──
    │
    └── return $this->collection->uniqueByClass()
        └── Déduplication par nom de classe
```

## Ordre de découverte

| Ordre | Source | Force | Description |
|-------|--------|-------|-------------|
| 1 | `BuiltInDirectiveDiscovery` | ✅ Force | Directives intégrées (prioritaires) |
| 2 | `WorkspaceDirectiveDiscovery` | ❌ | Directives du projet |
| 3 | `VendorDirectiveDiscovery` | ❌ | Directives des packages vendors |
| 4 | `CustomSources` | ❌ | Sources personnalisées |

### Règle de force

- **Force = true** : La directive est ajoutée même si sa signature est réservée
- **Force = false** : La directive est ignorée si sa signature est réservée

## Filtrage des directives

### 1. Validation de la classe

```php
private function isValidDirectiveClass(ReflectionClass $reflection): bool
{
    if ($reflection->isAbstract()) {
        return false; // ❌ Les classes abstraites sont ignorées
    }

    return $reflection->isSubclassOf(AbstractDirective::class); // ✅ Doit étendre AbstractDirective
}
```

### 2. Vérification des signatures réservées

```php
private function isReservedSignature(string $signature): bool
{
    $parsed = $this->parser->parse($signature, '');
    $commandName = $parsed->source;
    
    return in_array($commandName, $this->config->getReservedSignatures(), true);
}
```

### 3. Déduplication

Les directives sont dédupliquées par nom de classe pour éviter les doublons :

```php
return $this->collection->uniqueByClass();
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Répertoire personnalisé inexistant | Ignoré silencieusement | - |
| Classe abstraite | Ignorée (non ajoutée) | - |
| Classe ne respectant pas `AbstractDirective` | Ignorée (non ajoutée) | - |
| Signature réservée | Ignorée (non ajoutée) | - |

### Signatures réservées par défaut

```php
const DEFAULT_RESERVED_SIGNATURES = [
    '-h',
    '--help',
    '-v',
    '--version',
    '-l',
    '--list',
    'help',
    'list',
    'version',
];
```

## Intégration

Le `DirectiveDiscoveryService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DiscoverySourceInterface` | Sources de découverte (BuiltIn, Workspace, Vendor) |
| `DirectiveParserInterface` | Parsing des signatures pour validation |
| `DirectiveScannerInterface` | Scan des répertoires personnalisés |
| `FileSystemInterface` | Vérification des répertoires |
| `DirectiveConfigInterface` | Configuration et signatures réservées |
| `DirectiveMetadataCollection` | Collection des directives découvertes |

### Utilisation par d'autres composants

```php
// Dans DirectiveKernel
class DirectiveKernel
{
    public function run(array $argv): ExitCode
    {
        $directives = $this->discovery->discover();
        // Utiliser la collection pour trouver la directive appropriée
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n × m) | n = sources, m = directives par source |
| Temps typique | 200-800ms | Première découverte |
| Mémoire | 2-5 MB | Dépend du nombre de directives |
| Cache | ❌ Non | Recalcul à chaque appel |

### Facteurs de performance

1. **Nombre de sources** : Plus il y a de sources, plus la découverte est lente
2. **Nombre de directives** : Plus il y a de directives, plus la collection est grande
3. **Profondeur de scan** : Scan plus profond → plus de fichiers → plus lent
4. **Parsing** : Chaque directive est parsée pour validation

### Optimisations

```php
class DirectiveDiscoveryService
{
    private ?DirectiveMetadataCollection $cachedDirectives = null;
    
    public function discover(): DirectiveMetadataCollection
    {
        if ($this->cachedDirectives !== null) {
            return $this->cachedDirectives;
        }
        
        // ... découverte ...
        
        $this->cachedDirectives = $this->collection->uniqueByClass();
        return $this->cachedDirectives;
    }
    
    public function clearCache(): void
    {
        $this->cachedDirectives = null;
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Dans un service provider
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DirectiveDiscoveryService::class, function ($app) {
            return new DirectiveDiscoveryService(
                $app->make(BuiltInDirectiveDiscovery::class),
                $app->make(WorkspaceDirectiveDiscovery::class),
                $app->make(VendorDirectiveDiscovery::class),
                $app->make(DirectiveParserInterface::class),
                $app->make(DirectiveScannerInterface::class),
                $app->make(FileSystemInterface::class),
                $app->make(DirectiveConfigInterface::class),
                3
            );
        });
    }
}

// Utilisation dans un contrôleur
class DirectiveController extends Controller
{
    public function index(DirectiveDiscoveryService $discovery)
    {
        // Ajouter des sources personnalisées
        $discovery->addSources([
            base_path('app/CustomDirectives'),
            base_path('modules/Admin/Directives'),
        ]);
        
        // Ajouter une signature réservée
        $discovery->addReservedSignature('import');
        
        // Découvrir les directives
        $directives = $discovery->discover();
        
        return response()->json([
            'total' => $directives->count(),
            'directives' => $directives->map(function ($directive) {
                return [
                    'signature' => $directive->signature,
                    'description' => $directive->description,
                    'class' => $directive->class,
                    'aliases' => $directive->aliases->toArray(),
                ];
            })->toArray(),
        ]);
    }
}

// Vérification des signatures réservées
$discovery = app(DirectiveDiscoveryService::class);
$reserved = $discovery->getReservedSignatures();

echo "Signatures réservées:\n";
foreach ($reserved as $signature) {
    echo "- {$signature}\n";
}

// Retirer une signature réservée
if (in_array('version', $reserved, true)) {
    $discovery->removeReservedSignature('version');
    echo "Signature 'version' retirée des réservées\n";
}
```

## Notes techniques

### Stratégie de force

Les directives intégrées sont marquées avec `force = true` pour garantir leur présence :

```php
private function discoverBuiltInDirectives(): void
{
    $fqcns = $this->builtInSource->discover();
    
    foreach ($fqcns as $fqcn) {
        $this->addDirective($fqcn, true); // ✅ Force = true
    }
}
```

### Déduplication intelligente

La collection utilise `uniqueByClass()` pour éviter les doublons par nom de classe, même si la même directive est découverte depuis plusieurs sources.

### Validation des signatures

Les signatures sont parsées et validées avant d'être ajoutées à la collection :

```php
private function isReservedSignature(string $signature): bool
{
    $parsed = $this->parser->parse($signature, '');
    $commandName = $parsed->source;
    
    return in_array($commandName, $this->config->getReservedSignatures(), true);
}
```

### Points d'extension

Le service peut être étendu via :
1. **Nouvelles sources** : Ajout via `addSource()` et `addSources()`
2. **Signatures réservées** : Gestion via `addReservedSignature()` et `removeReservedSignature()`
3. **Sources personnalisées** : Implémentation de `DiscoverySourceInterface`
---
<!-- ==== ./docs/api-reference/services/directive-execution-service.md ==== -->

# DirectiveExecutionService - Référence Technique

## Description

Service central responsable de l'exécution complète des directives CLI. Orchestre la découverte, la recherche, le parsing, l'hydratation et l'exécution des directives. Gère également le **système de composition (Call System)** en exécutant récursivement les appels enregistrés par les directives.

## Hiérarchie

```
DirectiveExecutionService (final)
    ├── Dépend de : DirectiveDiscoveryService
    ├── Dépend de : DirectiveParserService
    ├── Dépend de : DirectiveHydratorService
    └── Dépend de : DirectiveRendererService
```

## Rôle principal

Exécuter une directive à partir d'un enregistrement d'exécution. Gère les commandes globales (`--help`, `--list`, `--version`), trouve la directive cible par signature, alias ou nom de base, parse les arguments, hydrate l'instance, exécute la directive et traite récursivement les appels enregistrés.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `execute(DirectiveExecutionRecord $record): ExitCode`

Exécute une directive à partir de l'enregistrement d'exécution. Cette méthode est récursive : elle exécute également tous les appels enregistrés par la directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `DirectiveExecutionRecord` | Enregistrement contenant la signature et les arguments |

**Retourne :** `ExitCode` - Code de sortie indiquant le succès ou l'échec

**Exceptions :** Aucune (toutes les exceptions sont capturées et traduites en codes de sortie)

**Exemple :**
```php
$arguments = new StringTypedCollection();
$arguments->add('John', '--role=admin');

$record = new DirectiveExecutionRecord(
    signature: 'user-create',
    arguments: $arguments
);

$exitCode = $service->execute($record);
```

## Cas d'utilisation

### Cas 1 : Exécution d'une directive simple

```php
$record = new DirectiveExecutionRecord(
    signature: 'user-list',
    arguments: new StringTypedCollection()
);

$exitCode = $service->execute($record);
// Retourne ExitCode::SUCCESS (0)
```

### Cas 2 : Exécution avec arguments et options

```php
$arguments = new StringTypedCollection();
$arguments->add('John Doe', 'john@example.com', '--role=admin');

$record = new DirectiveExecutionRecord(
    signature: 'user-create',
    arguments: $arguments
);

$exitCode = $service->execute($record);
```

### Cas 3 : Exécution via alias

```php
$record = new DirectiveExecutionRecord(
    signature: 'users',  // Alias de 'user-list'
    arguments: new StringTypedCollection()
);

$exitCode = $service->execute($record);
```

### Cas 4 : Exécution d'une directive orchestratrice (Call System)

```php
$arguments = new StringTypedCollection();
$arguments->add('123');

$record = new DirectiveExecutionRecord(
    signature: 'user-orchestrate',
    arguments: $arguments
);

// La directive parente s'exécute, puis les appels sont exécutés récursivement
$exitCode = $service->execute($record);
```

## Flux d'exécution avec Call System

```
1. Appel de execute($record)
   ↓
2. Vérification des commandes globales (--help, --list, --version)
   ↓
3. Découverte et recherche de la directive
   ↓
4. Parsing des arguments
   ↓
5. Hydratation de la directive
   ↓
6. Exécution de la directive parente
   ├── execute() → exécute la logique
   ├── Enregistrement des appels via call()
   └── Retour du résultat
   ↓
7. Récupération des appels via getCalls()
   ↓
8. Exécution récursive de chaque appel
   ├── Pour chaque call : execute($call)
   │   ├── Recherche de la directive enfant
   │   ├── Parsing des arguments
   │   ├── Hydratation
   │   ├── Exécution de l'enfant
   │   └── Traitement des appels de l'enfant
   └── Fin de la boucle
   ↓
9. Rendu du résultat (succès/échec)
   ↓
10. Retour du code de sortie final
```

## Gestion des erreurs

| Situation | Exception | Code de sortie |
|-----------|-----------|----------------|
| Directive non trouvée | - | `ExitCode::NOT_FOUND` (3) |
| Arguments invalides | `InvalidArgumentException` | `ExitCode::INVALID_ARGUMENT` (4) |
| Signature invalide | `InvalidArgumentException` | `ExitCode::INVALID_ARGUMENT` (4) |
| Erreur générale | `\Throwable` | `ExitCode::FAILURE` (1) |
| Échec de la directive | - | `ExitCode::FAILURE` (1) |
| Appel vers directive inexistante | Ignoré (pas de rupture) | Continue l'exécution |

## Intégration

`DirectiveExecutionService` s'intègre avec :

- **`DirectiveDiscoveryService`** : Découverte des directives disponibles
- **`DirectiveParserService`** : Parsing des arguments
- **`DirectiveHydratorService`** : Hydratation des instances
- **`DirectiveRendererService`** : Rendu des messages
- **`DirectiveExecutionRecord`** : Enregistrement d'entrée
- **`AbstractDirective`** : Récupération des appels via `getCalls()`

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Découverte | Une fois par exécution (cachée) |
| Recherche de directive | O(n) avec n = nombre de directives |
| Parsing | O(m) avec m = nombre d'arguments |
| Hydratation | O(k) avec k = arguments + options |
| Exécution des appels | Récursive, dépend du nombre de calls |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| Laravel 10+ | ✅ Optionnel |

## Exemple complet avec Call System

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\Directive\Services\DirectiveExecutionService;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\Directive\Services\DirectiveRendererService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// 1. Créer les dépendances
$discovery = new DirectiveDiscoveryService($config, $hydrator);
$parser = new DirectiveParserService();
$hydrator = new DirectiveHydratorService($factory);
$renderer = new DirectiveRendererService($renderDispatcher);

// 2. Créer le service
$service = new DirectiveExecutionService(
    discovery: $discovery,
    parser: $parser,
    hydrator: $hydrator,
    renderer: $renderer,
);

// 3. Exécuter une directive orchestratrice
$arguments = new StringTypedCollection();
$arguments->add('123');

$record = new DirectiveExecutionRecord(
    signature: 'user-orchestrate',
    arguments: $arguments,
);

// 4. Exécution
// La directive parente s'exécute, puis tous ses appels sont exécutés récursivement
$exitCode = $service->execute($record);

// 5. Vérifier le résultat
if ($exitCode === ExitCode::SUCCESS) {
    echo "Orchestration completed successfully\n";
} else {
    echo "Orchestration failed with code: " . $exitCode->value . "\n";
}
```

## Récursivité des appels

`DirectiveExecutionService` gère la récursivité des appels de manière automatique :

```php
// Dans executeDirective()
private function executeDirective(DirectiveMetadataRecord $metadata, DirectiveExecutionRecord $record): ExitCode
{
    // 1. Parser et hydrater la directive
    $parsed = $this->parser->parse($metadata->signature, $record->arguments);
    $directive = $this->hydrator->hydrate($metadata->class, $parsed);

    // 2. Exécuter la directive parente
    $result = $directive->run();

    // 3. Récupérer et exécuter les appels enregistrés
    $calls = $directive->getCalls();
    foreach ($calls as $call) {
        $this->execute($call); // ← Appel récursif
    }

    // 4. Rendre le résultat
    if ($result === ExitCode::SUCCESS) {
        $this->renderer->renderSuccess('Directive executed successfully');
    } else {
        $this->renderer->renderError('Directive execution failed');
    }

    return $result;
}
```
---
<!-- ==== ./docs/api-reference/services/directive-interaction-service.md ==== -->

# DirectiveInteractionService - Référence Technique

## Description

Service central pour toutes les interactions utilisateur dans les directives CLI. Gère l'affichage des messages, la capture des entrées utilisateur et le rendu des tableaux. Délègue le rendu à `RenderDispatcher` et l'entrée utilisateur à `InputDispatcher`.

## Hiérarchie

```
DirectiveInteractionService (final)
    ├── Dépend de : RenderDispatcher
    └── Dépend de : InputDispatcher
```

## Rôle principal

Faire le pont entre les directives et les tâches de rendu/entrée. Fournit une API simple et cohérente pour afficher des messages (info, erreur, avertissement), poser des questions, demander des confirmations et afficher des tableaux formatés.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(RenderDispatcher $renderDispatcher, InputDispatcher $inputDispatcher): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$renderDispatcher` | `RenderDispatcher` | Tâche de rendu pour l'affichage |
| `$inputDispatcher` | `InputDispatcher` | Tâche d'entrée pour la capture utilisateur |

Constructeur du service. Reçoit les deux dispatchers nécessaires pour le rendu et l'entrée.

**Exemple :**
```php
$renderDispatcher = new RenderDispatcher();
$inputDispatcher = new InputDispatcher();
$interaction = new DirectiveInteractionService($renderDispatcher, $inputDispatcher);
```

### `line(string $message): void`

Affiche un message texte brut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
$this->interaction->line('Processing users...');
```

### `info(string $message): void`

Affiche un message d'information (généralement en vert).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message d'information |

**Exemple :**
```php
$this->interaction->info('Operation completed successfully!');
```

### `error(string $message): void`

Affiche un message d'erreur (généralement en rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message d'erreur |

**Exemple :**
```php
$this->interaction->error('Failed to connect to database.');
```

### `warn(string $message): void`

Affiche un message d'avertissement (généralement en jaune).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message d'avertissement |

**Exemple :**
```php
$this->interaction->warn('This operation may take a while.');
```

### `newLine(): void`

Affiche une ligne vide.

**Exemple :**
```php
$this->interaction->newLine();
```

### `separator(string $character = '-', int $length = 80): void`

Affiche une ligne de séparation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$character` | `string` | Caractère de séparation (défaut: '-') |
| `$length` | `int` | Longueur de la ligne (défaut: 80) |

**Exemple :**
```php
$this->interaction->separator('=', 100);
$this->interaction->line('Section Title');
$this->interaction->separator();
```

### `ask(string $question): string`

Pose une question et retourne la réponse de l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | La question à poser |

**Retourne :** `string` - La réponse saisie (trimée)

**Exemple :**
```php
$name = $this->interaction->ask('What is your name?');
```

### `confirm(string $question): bool`

Demande une confirmation et retourne le choix de l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | La question de confirmation |

**Retourne :** `bool` - `true` pour `y`/`yes`, `false` pour `n`/`no`

**Exemple :**
```php
if ($this->interaction->confirm('Do you want to continue?')) {
    // Continuer
}
```

### `askUserChoice(string $name, int $max): int`

Demande à l'utilisateur de choisir dans une liste numérotée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom du choix (pour l'affichage) |
| `$max` | `int` | Le nombre maximum de choix (1 à max) |

**Retourne :** `int` - Le numéro choisi (1 à max), ou `0` si invalide

**Exemple :**
```php
$this->interaction->line('1. List users');
$this->interaction->line('2. Create user');
$this->interaction->line('3. Exit');

$choice = $this->interaction->askUserChoice('Select an action', 3);
```

### `table(StringTypedCollection $headers, RowCollection $rows): void`

Affiche un tableau formaté avec en-têtes et lignes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `StringTypedCollection` | Les en-têtes du tableau |
| `$rows` | `RowCollection` | Les lignes du tableau |

**Exemple :**
```php
$headers = new StringTypedCollection();
$headers->add('ID', 'Name', 'Email');

$rows = new RowCollection();
$row = new RowCollection();
$row->add(1, 'John Doe', 'john@example.com');
$rows->add($row);

$this->interaction->table($headers, $rows);
```

## Cas d'utilisation

### Cas 1 : Directive interactive complète

```php
final class SetupDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        $this->interaction->info('Welcome to the setup wizard!');
        
        $appName = $this->interaction->ask('Application name');
        $environment = $this->interaction->ask('Environment (local/production)');
        
        if (!$this->interaction->confirm("Create configuration for {$appName}?")) {
            $this->interaction->warn('Setup cancelled');
            return ExitCode::SUCCESS;
        }
        
        $headers = new StringTypedCollection();
        $headers->add('Setting', 'Value');
        
        $rows = new RowCollection();
        $row = new RowCollection();
        $row->add('App Name', $appName);
        $row->add('Environment', $environment);
        $rows->add($row);
        
        $this->interaction->table($headers, $rows);
        $this->interaction->info('Setup completed!');
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Menu avec choix utilisateur

```php
public function execute(): ExitCode
{
    $this->interaction->info('Available actions:');
    $this->interaction->line('1. List users');
    $this->interaction->line('2. Create user');
    $this->interaction->line('3. Delete user');
    $this->interaction->line('4. Exit');
    
    $choice = $this->interaction->askUserChoice('Select an action', 4);
    
    return match($choice) {
        1 => $this->listUsers(),
        2 => $this->createUser(),
        3 => $this->deleteUser(),
        4 => ExitCode::SUCCESS,
        default => ExitCode::INVALID_ARGUMENT,
    };
}
```

### Cas 3 : Résultats en tableau

```php
public function execute(): ExitCode
{
    $users = $this->userRepository->all();
    
    $headers = new StringTypedCollection();
    $headers->add('ID', 'Name', 'Email', 'Status');
    
    $rows = new RowCollection();
    foreach ($users as $user) {
        $row = new RowCollection();
        $row->add($user->id, $user->name, $user->email, $user->active ? 'Active' : 'Inactive');
        $rows->add($row);
    }
    
    $this->interaction->table($headers, $rows);
    $this->interaction->info('Total: ' . count($users) . ' users');
    
    return ExitCode::SUCCESS;
}
```

### Cas 4 : Directive de progression

```php
public function execute(): ExitCode
{
    $items = ['task1', 'task2', 'task3'];
    $total = count($items);
    
    $this->interaction->info("Processing {$total} items...");
    
    foreach ($items as $index => $item) {
        $this->interaction->line("  [{".($index+1)."/{$total}] Processing {$item}...");
        // Traitement...
        $this->interaction->line("  ✓ Completed");
    }
    
    $this->interaction->newLine();
    $this->interaction->info('All tasks completed!');
    
    return ExitCode::SUCCESS;
}
```

### Cas 5 : Directive d'information système

```php
public function execute(): ExitCode
{
    $info = [
        ['PHP Version', phpversion()],
        ['Memory Limit', ini_get('memory_limit')],
        ['Max Execution Time', ini_get('max_execution_time')],
    ];
    
    $headers = new StringTypedCollection();
    $headers->add('Setting', 'Value');
    
    $rows = new RowCollection();
    foreach ($info as $rowData) {
        $row = new RowCollection();
        $row->add($rowData[0], $rowData[1]);
        $rows->add($row);
    }
    
    $this->interaction->info('System Information:');
    $this->interaction->table($headers, $rows);
    
    return ExitCode::SUCCESS;
}
```

## Flux d'exécution

```
1. Appel d'une méthode d'interaction
   │
   ├── line() / info() / error() / warn()
   │   ├── Crée DisplayMessageRecord avec MessageType
   │   ├── Crée RenderRecord avec RenderType::DISPLAY_MESSAGE
   │   └── RenderDispatcher->execute() → affichage
   │
   ├── ask()
   │   ├── Crée QuestionRecord
   │   └── InputDispatcher->execute(InputType::SIMPLE_QUESTION) → retourne string
   │
   ├── confirm()
   │   ├── Crée QuestionRecord
   │   └── InputDispatcher->execute(InputType::CONFIRMATION) → retourne bool
   │
   ├── askUserChoice()
   │   ├── Crée UserChoiceRecord
   │   └── InputDispatcher->execute(InputType::USER_CHOICE) → retourne int
   │
   └── table()
       ├── Crée DisplayTableRecord
       ├── Crée RenderRecord avec RenderType::TABLE
       └── RenderDispatcher->execute() → affichage du tableau
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Entrée utilisateur vide pour `ask()` | Retourne une chaîne vide (`''`) |
| Confirmation avec réponse invalide | `confirm()` retourne `false` |
| Choix utilisateur non numérique | `askUserChoice()` retourne `0` |
| Choix utilisateur hors plage (1-max) | `askUserChoice()` retourne `0` |

## Intégration

`DirectiveInteractionService` s'intègre avec :

- **`RenderDispatcher`** : Tâche de rendu pour l'affichage des messages et tableaux
- **`InputDispatcher`** : Tâche d'entrée pour la capture utilisateur
- **`MessageType`** : Enum des types de messages (`LINE`, `INFO`, `ERROR`, `WARNING`)
- **`InputType`** : Enum des types d'entrée (`SIMPLE_QUESTION`, `CONFIRMATION`, `USER_CHOICE`)
- **`RenderType`** : Enum des types de rendu (`DISPLAY_MESSAGE`, `TABLE`)
- **`DisplayMessageRecord`** : Record pour les messages
- **`DisplayTableRecord`** : Record pour les tableaux
- **`QuestionRecord`** : Record pour les questions
- **`UserChoiceRecord`** : Record pour les choix utilisateur

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Affichage | Délégation à `RenderDispatcher` (pas de surcharge) |
| Entrée | Délégation à `InputDispatcher` (temps réel utilisateur) |
| Tableau | O(n × m) avec n = lignes, m = colonnes |
| Messages | O(1) - simple création de records |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis (types union, readonly) |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Directive\Collections\RowCollection;

// 1. Créer les dépendances
$renderDispatcher = new RenderDispatcher();
$inputDispatcher = new InputDispatcher();

// 2. Créer le service d'interaction
$interaction = new DirectiveInteractionService($renderDispatcher, $inputDispatcher);

// 3. Afficher un message de bienvenue
$interaction->info('Welcome to the application!');
$interaction->separator();

// 4. Poser une question
$name = $interaction->ask('What is your name?');
$interaction->line("Hello, {$name}!");

// 5. Demander une confirmation
if ($interaction->confirm('Do you want to see the information?')) {
    // 6. Afficher un tableau
    $headers = new StringTypedCollection();
    $headers->add('Item', 'Value');
    
    $rows = new RowCollection();
    $row = new RowCollection();
    $row->add('Name', $name);
    $row->add('Time', date('Y-m-d H:i:s'));
    $row->add('PHP Version', phpversion());
    $rows->add($row);
    
    $interaction->table($headers, $rows);
}

// 7. Menu de choix
$interaction->newLine();
$interaction->line('1. Option One');
$interaction->line('2. Option Two');
$interaction->line('3. Exit');

$choice = $interaction->askUserChoice('Select an option', 3);

switch ($choice) {
    case 1:
        $interaction->info('You selected Option One');
        break;
    case 2:
        $interaction->info('You selected Option Two');
        break;
    case 3:
        $interaction->warn('Exiting...');
        break;
    default:
        $interaction->error('Invalid selection');
}

// 8. Message de fin
$interaction->separator();
$interaction->info('Goodbye!');
```

## Voir aussi

- [`RenderDispatcher`](../tasks/render-task.md) - Tâche de rendu
- [`InputDispatcher`](../tasks/input-task.md) - Tâche d'entrée utilisateur
- [`MessageType`](../enums/message-type.md) - Types de messages
- [`InputType`](../enums/input-type.md) - Types d'entrée
- [`RenderType`](../enums/render-type.md) - Types de rendu
- [`RowCollection`](../collections/row-collection.md) - Collection pour lignes de tableau
---
<!-- ==== ./docs/api-reference/services/file-creator-service.md ==== -->

# FileCreatorService - Référence Technique

## Description

Service de création de fichiers à partir de templates (stubs) avec remplacement de variables. Il gère la création de répertoires, la lecture des stubs, le remplacement des placeholders et l'écriture des fichiers.

## Hiérarchie

```
FileCreatorService
    └── Aucune classe parente (classe finale)
    ├── Dépend de FileCreatorConfigInterface
    └── Dépend de FileSystemInterface
```

## Rôle principal

Ce service centralise la logique de création de fichiers dans un composant **stateless**, testable et découplé du framework. Contrairement au trait `FileCreator` déprécié, il injecte ses dépendances et utilise des objets typés (`ReplacementCollection`, `FileCreationContext`, `PermissionMode`).

## Prérequis

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(FileCreatorConfigInterface $config, ?FileSystemInterface $filesystem = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `FileCreatorConfigInterface` | Configuration des permissions et chemins |
| `$filesystem` | `FileSystemInterface|null` | Instance optionnelle (créée automatiquement) |

**Exemple :**
```php
$config = new FileCreatorConfig();
$service = new FileCreatorService($config);
```

---

### `createFile(string $stubPath, string $destinationPath, ReplacementCollection $replacements, FileCreationContext $context): FileCreationResultRecord`

Crée un fichier à partir d'un stub avec remplacement de variables.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$stubPath` | `string` | Chemin vers le fichier stub |
| `$destinationPath` | `string` | Chemin de destination absolu |
| `$replacements` | `ReplacementCollection` | Collection des paires placeholder → valeur |
| `$context` | `FileCreationContext` | Contexte pour l'état du traitement |

**Retourne :** `FileCreationResultRecord` - Résultat avec succès, chemin et message

**Exceptions :** Aucune (les erreurs sont capturées dans le contexte)

**Exemple :**
```php
$context = new FileCreationContext();
$replacements = new ReplacementCollection();
$replacements->addReplacement('{{name}}', 'UserTask');

$result = $service->createFile(
    '/stubs/task.stub',
    '/app/Tasks/UserTask.php',
    $replacements,
    $context
);

if ($result->success) {
    echo $result->message; // "File created successfully: /app/Tasks/UserTask.php"
}
```

---

### `createFileFromName(string $stubPath, string $name, string $baseDirectory, ReplacementCollection $replacements, FileCreationContext $context): FileCreationResultRecord`

Crée un fichier en construisant automatiquement le chemin de destination à partir d'un nom.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$stubPath` | `string` | Chemin vers le fichier stub |
| `$name` | `string` | Nom avec sous-répertoires (ex: "Admin/UserTask") |
| `$baseDirectory` | `string` | Répertoire de base |
| `$replacements` | `ReplacementCollection` | Collection des remplacements |
| `$context` | `FileCreationContext` | Contexte pour l'état |

**Retourne :** `FileCreationResultRecord` - Résultat de la création

**Exemple :**
```php
$result = $service->createFileFromName(
    '/stubs/class.stub',
    'Admin/UserTask',
    '/app/Directives',
    $replacements,
    $context
);
// Crée : /app/Directives/Admin/UserTask.php
```

---

### `toPascalCase(string $string, FileCreationContext $context): string`

Convertit une chaîne de kebab-case ou snake_case en PascalCase.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$string` | `string` | Chaîne en kebab-case ou snake_case |
| `$context` | `FileCreationContext` | Contexte pour logger la transformation |

**Retourne :** `string` - Chaîne en PascalCase

**Exemple :**
```php
$result = $service->toPascalCase('user-profile', $context); // 'UserProfile'
$result = $service->toPascalCase('user_profile', $context); // 'UserProfile'
```

---

### `toKebabCase(string $string, FileCreationContext $context): string`

Convertit une chaîne de PascalCase en kebab-case.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$string` | `string` | Chaîne en PascalCase |
| `$context` | `FileCreationContext` | Contexte pour logger la transformation |

**Retourne :** `string` - Chaîne en kebab-case

**Exemple :**
```php
$result = $service->toKebabCase('UserProfile', $context); // 'user-profile'
```

---

### `extractPathSegments(string $name, FileCreationContext $context): PathSegmentsRecord`

Extrait les segments d'un chemin et les convertit en PascalCase pour les sous-répertoires.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Chemin avec segments séparés par `/` |
| `$context` | `FileCreationContext` | Contexte pour stocker les segments |

**Retourne :** `PathSegmentsRecord` - Record contenant les segments, nom de classe, sous-chemin

**Exemple :**
```php
$segments = $service->extractPathSegments('admin/user/UserRepository', $context);

echo $segments->className;   // 'UserRepository'
echo $segments->subPath;     // 'Admin/User'
echo $segments->fullPath;    // 'Admin/User/UserRepository'
```

---

### `buildNamespace(string $baseNamespace, PathSegmentsRecord $segments, FileCreationContext $context): string`

Construit un namespace PHP à partir d'un namespace de base et des segments de chemin.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$baseNamespace` | `string` | Namespace de base (ex: "App\\Tasks") |
| `$segments` | `PathSegmentsRecord` | Segments extraits |
| `$context` | `FileCreationContext` | Contexte pour stocker le namespace |

**Retourne :** `string` - Namespace complet

**Exemple :**
```php
$namespace = $service->buildNamespace('App\\Tasks', $segments, $context);
// 'App\\Tasks\\Admin\\User'
```

---

### `getAppPath(string $baseDirectory, PathSegmentsRecord $segments, FileCreationContext $context): string`

Construit un chemin absolu pour un fichier.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$baseDirectory` | `string` | Répertoire de base |
| `$segments` | `PathSegmentsRecord` | Segments extraits |
| `$context` | `FileCreationContext` | Contexte pour stocker le chemin |

**Retourne :** `string` - Chemin absolu du fichier

**Exemple :**
```php
$path = $service->getAppPath('/app/Tasks/', $segments, $context);
// '/app/Tasks/Admin/UserTask.php'
```

---

## Cas d'utilisation

### Cas 1 : Création d'une directive depuis un stub

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;

final class CreateDirectiveDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator
    ) {
        parent::__construct();
    }

    public function getSignature(): string
    {
        return 'make:directive {name}';
    }

    public function getDescription(): string
    {
        return 'Create a new directive class';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{class}}', $this->toPascalCase($name));
        $replacements->addReplacement('{{signature}}', $this->toKebabCase($name));

        $context = new FileCreationContext();
        $result = $this->fileCreator->createFileFromName(
            stubPath: __DIR__ . '/stubs/directive.stub',
            name: $name,
            baseDirectory: '/app/Directives',
            replacements: $replacements,
            context: $context
        );

        if (!$result->success) {
            $this->error($result->message);
            return ExitCode::FAILURE;
        }

        $this->info($result->message);
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Migration de l'ancien trait vers le service

```php
// ❌ Ancienne approche (trait déprécié)
use AndyDefer\Directive\Traits\FileCreator;

class OldDirective extends AbstractDirective
{
    use FileCreator;

    public function execute(): ExitCode
    {
        $this->initFileCreator();
        $this->createFile('/stub.stub', '/dest.php', ['{{name}}' => 'Value']);
        return ExitCode::SUCCESS;
    }
}

// ✅ Nouvelle approche (service injecté)
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;

class NewDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator
    ) {
        parent::__construct();
    }

    public function execute(): ExitCode
    {
        $replacements = new ReplacementCollection();
        $replacements->addReplacement('{{name}}', 'Value');

        $context = new FileCreationContext();
        $result = $this->fileCreator->createFile(
            '/stub.stub',
            '/dest.php',
            $replacements,
            $context
        );

        if (!$result->success) {
            $this->error($result->message);
            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }
}
```

---

## Flux d'exécution

```
createFile()
    │
    ├── 1. START → setCurrentStep()
    │
    ├── 2. Vérification existence fichier
    │       ├── Existe ET !force → FAILED
    │       └── Sinon → suite
    │
    ├── 3. CREATING_DIRECTORY → ensureDirectoryExists()
    │       └── makeDirectory() si inexistant
    │
    ├── 4. READING_STUB → getStubContent()
    │       ├── Succès → stubContent
    │       └── Échec → FAILED
    │
    ├── 5. REPLACING_VARIABLES → replaceVariables()
    │       └── str_replace() sur tous les placeholders
    │
    ├── 6. WRITING_FILE → writeFile()
    │       ├── Succès → suite
    │       └── Échec → FAILED
    │
    └── 7. COMPLETED → addCreatedFile()
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fichier existe déjà (force=false) | (aucune, erreur dans contexte) | `File already exists: {$path}` |
| Stub introuvable | `RuntimeException` capturée | `Stub template not found at: {$stubPath}` |
| Écriture échoue | (aucune, erreur dans contexte) | `Cannot create file: {$destinationPath}` |

---

## Intégration

### Déclaration dans un Service Provider

```php
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Services\FileSystemService;

public function register(): void
{
    $this->app->singleton(FileCreatorService::class, function ($app) {
        $config = new FileCreatorConfig();
        $filesystem = new FileSystemService();
        return new FileCreatorService($config, $filesystem);
    });
}
```

### Injection dans une Directive

```php
final class MyDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator
    ) {
        parent::__construct();
    }
}
```

---

## Performance

| Opération | Complexité |
|-----------|------------|
| `createFile()` | O(n) avec n = nombre de placeholders |
| `extractPathSegments()` | O(n) avec n = nombre de segments |
| `toPascalCase()` / `toKebabCase()` | O(m) avec m = longueur de la chaîne |
| `createStringCollection()` | O(n) avec n = nombre d'éléments |

- **Point important :** Le service est stateless, donc thread-safe et réutilisable
- La lecture du stub utilise `file_get_contents()` - pas de cache intégré

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |

| Dépendance | Version |
|------------|---------|
| `andydefer/domain-structures` | ^2.0 |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Configs\FileCreatorConfig;
use AndyDefer\Directive\Collections\ReplacementCollection;
use AndyDefer\Directive\Contexts\FileCreationContext;
use AndyDefer\Directive\Enums\PermissionMode;
use AndyDefer\Directive\Services\FileSystemService;

// 1. Configuration
$config = new FileCreatorConfig();
$filesystem = new FileSystemService();
$service = new FileCreatorService($config, $filesystem);

// 2. Préparer les remplacements
$replacements = new ReplacementCollection();
$replacements->addReplacement('{{class}}', 'UserTask');
$replacements->addReplacement('{{namespace}}', 'App\\Tasks');
$replacements->addReplacement('{{description}}', 'Handle user tasks');

// 3. Créer le contexte
$context = new FileCreationContext(force: false);

// 4. Créer le fichier
$result = $service->createFile(
    stubPath: __DIR__ . '/stubs/task.stub',
    destinationPath: __DIR__ . '/output/UserTask.php',
    replacements: $replacements,
    context: $context
);

// 5. Vérifier le résultat
if ($result->success) {
    echo "✅ " . $result->message . PHP_EOL;

    // Inspecter le contexte
    echo "Steps executed: " . $context->getStepsExecuted() . PHP_EOL;
    echo "Files created: " . $context->getCreatedFilesCount() . PHP_EOL;

    $logs = $context->getTransformationLogs();
    foreach ($logs as $log) {
        echo "Log: {$log}" . PHP_EOL;
    }
} else {
    echo "❌ " . $result->message . PHP_EOL;
    echo "Error: " . $context->getErrorMessage() . PHP_EOL;
}
```
---
<!-- ==== ./docs/api-reference/services/directive-hydrator-service.md ==== -->

# DirectiveHydratorService - Référence Technique

## Description

Service responsable de l'hydratation des instances de directives avec les données parsées (arguments, options) et l'injection des dépendances (bootstrapper Laravel, interaction service).

## Hiérarchie

```
DirectiveHydratorService
    ├── Dépend de : LaravelBootstrapperContext
    └── Dépend de : DirectiveInteractionService (optionnel)
```

## Rôle principal

Transformer un enregistrement parsé (`ParsedDirectiveRecord`) en une instance de directive entièrement configurée. Gère l'injection du bootstrapper Laravel, la conversion des options (boolean normalisation), et la création d'instances pour l'extraction des métadonnées.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(LaravelBootstrapperContext $laravelBootstrapperContext, ?DirectiveInteractionService $interaction = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$laravelBootstrapperContext` | `LaravelBootstrapperContext` | Contexte du bootstrapper Laravel |
| `$interaction` | `DirectiveInteractionService|null` | Service d'interaction (créé automatiquement si null) |

Constructeur du service d'hydratation.

### `hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface`

Hydrate complètement une directive avec les arguments et options parsés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive (ex: `UserListDirective::class`) |
| `$parsed` | `ParsedDirectiveRecord` | Enregistrement contenant les arguments et options parsés |

**Retourne :** `DirectiveInterface` - Instance de directive hydratée

**Exemple :**
```php
$parsed = new ParsedDirectiveRecord($arguments, $options, $variadic);
$directive = $hydrator->hydrate(UserListDirective::class, $parsed);
$directive->execute();
```

### `hydrateBlueprint(string $class): DirectiveBlueprintRecord`

Extrait le blueprint d'une directive sans exécuter son constructeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive |

**Retourne :** `DirectiveBlueprintRecord` - Blueprint contenant classe, signature et description

**Exemple :**
```php
$blueprint = $hydrator->hydrateBlueprint(UserListDirective::class);
echo $blueprint->signature; // 'user-list'
echo $blueprint->description; // 'List all users'
```

### `hydrateForAliases(string $class): DirectiveInterface`

Extrait une instance de directive pour la résolution d'alias (constructeur exécuté avec contexte minimal).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$class` | `string` | FQCN de la directive |

**Retourne :** `DirectiveInterface` - Instance de directive avec contexte minimal

**Exemple :**
```php
$directive = $hydrator->hydrateForAliases(UserListDirective::class);
$aliases = $directive->getAliases(); // ['users', 'list-users']
```

### `normalizeOptions(ParsedOptionCollection $options): ParameterCollection` (privée)

Normalise les options parsées en convertissant les chaînes `'true'` et `'false'` en booléens.

## Cas d'utilisation

### Cas 1 : Hydratation complète d'une directive

```php
// Parser les arguments de la ligne de commande
$parser = new DirectiveParserService();
$argv = new StringTypedCollection();
$argv->add('John', '--role=admin');

$parsed = $parser->parse('user:create {name} {--role=}', $argv);

// Hydrater la directive
$hydrator = new DirectiveHydratorService($laravelBootstrapperContext);
$directive = $hydrator->hydrate(UserCreateDirective::class, $parsed);

// Exécuter
$exitCode = $directive->execute();
```

### Cas 2 : Extraction du blueprint pour l'affichage de l'aide

```php
$blueprint = $hydrator->hydrateBlueprint(UserListDirective::class);
echo "Signature: " . $blueprint->signature . "\n";
echo "Description: " . $blueprint->description . "\n";
```

### Cas 3 : Extraction des alias pour la résolution

```php
$directive = $hydrator->hydrateForAliases(UserListDirective::class);
$aliases = $directive->getAliases();

foreach ($aliases as $alias) {
    $this->directiveRegistry->registerAlias($alias, $directive);
}
```

### Cas 4 : Normalisation des options booléennes

```php
// Les options avec valeur 'true' ou 'false' sont automatiquement converties
$options = new ParsedOptionCollection;
$options->addOption('active', 'true', true);  // 'true' → true
$options->addOption('debug', 'false', true);  // 'false' → false

$parsed = new ParsedDirectiveRecord($arguments, $options, $variadic);
$directive = $hydrator->hydrate(TestDirective::class, $parsed);

$directive->option('active'); // true (bool)
$directive->option('debug');  // false (bool)
```

## Flux d'exécution

```
hydrate(class, parsed)
    │
    ├── 1. Création d'une instance temporaire
    │       └── ReflectionClass::newInstance(tempContext, interaction)
    │
    ├── 2. Extraction des métadonnées
    │       ├── getBlueprint()
    │       ├── getAliases()
    │       └── shouldBootLaravel()
    │
    ├── 3. Création du vrai contexte
    │       └── new DirectiveContext(blueprint, aliases, shouldBootLaravel)
    │
    ├── 4. Injection des données parsées
    │       ├── setArguments()
    │       ├── setOptions() (avec normalisation booléenne)
    │       └── setVariadicArguments()
    │
    └── 5. Création de l'instance finale
            └── ReflectionClass::newInstance(context, interaction)
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Classe non trouvée | Exception `ReflectionException` propagée |
| Constructeur sans paramètres attendus | Exception `ReflectionException` propagée |
| Options avec `'true'` | Converties en `true` (bool) |
| Options avec `'false'` | Converties en `false` (bool) |
| Autres valeurs d'options | Conservées comme `string` |

## Intégration

`DirectiveHydratorService` s'intègre avec :

- **`LaravelBootstrapperContext`** : Contexte du bootstrapper Laravel injecté dans toutes les directives
- **`DirectiveInteractionService`** : Service d'interaction pour les sorties utilisateur
- **`ParsedDirectiveRecord`** : Données parsées (arguments, options, variadic)
- **`ParameterCollection`** : Conversion des arguments en collection typée
- **`ParsedOptionCollection`** : Conversion des options avec normalisation booléenne
- **`StringTypedCollection`** : Arguments variadiques

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Hydratation complète | O(n) avec n = nombre d'arguments + options |
| Extraction blueprint | O(1) + réflexion (création d'instance temporaire) |
| Extraction alias | O(1) + réflexion (création d'instance temporaire) |
| Normalisation options | O(m) avec m = nombre d'options |
| Réflexion | Utilisée pour la création d'instances temporaires et finales |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis (réflexion, types union) |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Collections\ParsedArgumentCollection;
use AndyDefer\Directive\Collections\ParsedOptionCollection;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\Directive\Services\DirectiveHydratorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// 1. Créer les dépendances
$laravelBootstrapperContext = new LaravelBootstrapperContext();
$interaction = new DirectiveInteractionService(
    new RenderDispatcher(),
    new InputDispatcher()
);

// 2. Créer l'hydrateur
$hydrator = new DirectiveHydratorService(
    laravelBootstrapperContext: $laravelBootstrapperContext,
    interaction: $interaction
);

// 3. Créer un record parsé avec arguments
$arguments = new ParsedArgumentCollection();
$arguments->addArgument('John Doe', 'name');
$arguments->addArgument('john@example.com', 'email');

// 4. Ajouter des options
$options = new ParsedOptionCollection();
$options->addOption('role', 'admin', false);
$options->addOption('active', 'true', true);
$options->addOption('verbose', 'false', true);

// 5. Ajouter des arguments variadiques
$variadic = new StringTypedCollection();
$variadic->add('file1.txt');
$variadic->add('file2.txt');
$variadic->add('file3.txt');

$parsed = new ParsedDirectiveRecord($arguments, $options, $variadic);

// 6. Hydrater la directive
$directive = $hydrator->hydrate(UserCreateDirective::class, $parsed);

// 7. Accéder aux valeurs
echo $directive->argument('name');   // 'John Doe'
echo $directive->argument('email');  // 'john@example.com'
echo $directive->option('role');     // 'admin'
echo $directive->option('active');   // true (bool)
echo $directive->option('verbose');  // false (bool)

foreach ($directive->getVariadicArguments() as $file) {
    echo "Processing: {$file}\n";
}

// 8. Extraire le blueprint
$blueprint = $hydrator->hydrateBlueprint(UserListDirective::class);
echo "Signature: " . $blueprint->signature . "\n";
echo "Description: " . $blueprint->description . "\n";

// 9. Extraire les alias
$aliasDirective = $hydrator->hydrateForAliases(UserListDirective::class);
foreach ($aliasDirective->getAliases() as $alias) {
    echo "Alias: {$alias}\n";
}
```

## Voir aussi

- [`DirectiveParserService`](directive-parser-service.md) - Service de parsing des signatures
- [`DirectiveContext`](../contexts/directive-context.md) - Contexte de la directive
- [`LaravelBootstrapperContext`](../contexts/laravel-bootstrapper-context.md) - Contexte du bootstrapper Laravel
- [`DirectiveInteractionService`](directive-interaction-service.md) - Service d'interaction utilisateur
- [`ParsedDirectiveRecord`](../records/parsed-directive-record.md) - Record des données parsées
---
<!-- ==== ./docs/api-reference/services/directive-naming-service.md ==== -->

# DirectiveNamingService - Référence Technique

## Description

Service de génération de noms de classes et de signatures pour les directives, avec conversion automatique entre différentes conventions de nommage (kebab-case, PascalCase) et substitution de variables dans les stubs.

## Hiérarchie

```
Aucune hiérarchie - Classe finale sans extension ni implémentation
```

## Rôle principal

Ce service assure la conversion cohérente entre les noms de directives (format kebab-case utilisateur) et les noms de classes PHP (format PascalCase avec suffixe `Directive`). Il fournit également des utilitaires pour générer des signatures avec options et pour remplacer des variables dans des templates de stubs lors de la génération automatique de code.

## Installation

```bash
composer require andydefer/php-records
```

Aucune configuration supplémentaire requise.

```php
$naming = new DirectiveNamingService();
$className = $naming->generateClassName('user-create'); // 'UserCreateDirective'
```

## API / Méthodes publiques

### `generateClassName(string $name): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de la directive au format kebab-case (ex: 'user-create') |

**Retourne :** `string` - Nom de classe au format PascalCase avec suffixe 'Directive'

**Exemples :**
```php
$naming = new DirectiveNamingService();

$naming->generateClassName('user-create');     // 'UserCreateDirective'
$naming->generateClassName('clean-log');       // 'CleanLogDirective'
$naming->generateClassName('db-migrate-fresh'); // 'DbMigrateFreshDirective'
$naming->generateClassName('api-v2');          // 'ApiV2Directive'
```

### `generateSignatureWithOption(string $name): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Nom de base de la directive |

**Retourne :** `string` - Signature complète avec placeholder d'option `{--option}`

**Exemple :**
```php
$signature = $naming->generateSignatureWithOption('user-create');
// 'user-create {--option}'
```

### `replaceStubVariables(string $stub, string $className, string $signature): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$stub` | `string` | Contenu du template avec placeholders |
| `$className` | `string` | Nom de la classe de la directive |
| `$signature` | `string` | Signature de base de la directive |

**Retourne :** `string` - Contenu du template avec les placeholders remplacés

**Placeholders disponibles :**
| Placeholder | Description | Exemple |
|-------------|-------------|---------|
| `{{class}}` | Nom de la classe | `UserCreateDirective` |
| `{{signature}}` | Signature avec option | `user-create {--option}` |
| `{{description}}` | Description par défaut | `Generated directive for user-create` |
| `{{date}}` | Date et heure actuelles | `2024-01-15 14:30:00` |

**Exemple :**
```php
$stub = 'class {{class}} extends BaseDirective {
    protected string $signature = "{{signature}}";
    protected string $description = "{{description}}";
}';

$result = $naming->replaceStubVariables(
    $stub,
    'UserCreateDirective',
    'user-create'
);
```

### `extractBaseName(string $className): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$className` | `string` | Nom de la classe de directive (ex: 'UserCreateDirective') |

**Retourne :** `string` - Nom de base au format kebab-case

**Exemples :**
```php
$naming->extractBaseName('UserCreateDirective');     // 'user-create'
$naming->extractBaseName('ApiV2Directive');          // 'api-v2'
$naming->extractBaseName('UserProfileCreateV2Directive'); // 'user-profile-create-v2'
```

## Cas d'utilisation

### Cas 1 : Génération automatique de directives

**Problème :** Créer une commande qui génère automatiquement une classe de directive à partir d'un nom utilisateur.

```php
class GenerateDirectiveCommand
{
    private DirectiveNamingService $naming;
    private FileSystem $fs;
    
    public function execute(string $directiveName): void
    {
        // Générer le nom de classe
        $className = $this->naming->generateClassName($directiveName);
        
        // Générer la signature
        $signature = $this->naming->generateSignatureWithOption($directiveName);
        
        // Charger le stub
        $stub = $this->fs->read(__DIR__ . '/stubs/directive.stub');
        
        // Remplacer les variables
        $content = $this->naming->replaceStubVariables($stub, $className, $directiveName);
        
        // Écrire le fichier
        $this->fs->write("app/Directives/{$className}.php", $content);
        
        echo "✅ Directive {$className} created successfully\n";
    }
}
```

### Cas 2 : Reverse engineering de directives existantes

**Problème :** Analyser des classes existantes pour extraire leur nom de commande.

```php
class DirectiveAnalyzer
{
    private DirectiveNamingService $naming;
    
    public function analyze(string $className): array
    {
        $baseName = $this->naming->extractBaseName($className);
        
        return [
            'class' => $className,
            'command' => $baseName,
            'signature' => $this->naming->generateSignatureWithOption($baseName),
        ];
    }
}

// Utilisation
$analyzer = new DirectiveAnalyzer($naming);
$info = $analyzer->analyze('UserCreateDirective');
// $info = [
//     'class' => 'UserCreateDirective',
//     'command' => 'user-create',
//     'signature' => 'user-create {--option}'
// ]
```

### Cas 3 : Validation et normalisation des noms

**Problème :** Normaliser les noms de directives saisis par les utilisateurs.

```php
class DirectiveNormalizer
{
    private DirectiveNamingService $naming;
    
    public function normalize(string $input): string
    {
        // Convertir différents formats en kebab-case standard
        $kebabCase = str_replace('_', '-', $input);
        $kebabCase = strtolower(preg_replace('/(?<!^)(?=[A-Z])/', '-', $kebabCase));
        
        // Vérifier que le nom normalisé peut être converti en classe
        $className = $this->naming->generateClassName($kebabCase);
        
        return $kebabCase;
    }
}
```

### Cas 4 : Génération de documentation

**Problème :** Générer automatiquement la documentation des directives.

```php
class DocumentationGenerator
{
    private DirectiveNamingService $naming;
    
    public function generateMarkdown(array $directives): string
    {
        $markdown = "# Available Directives\n\n";
        
        foreach ($directives as $directive) {
            $className = $directive['class'];
            $command = $this->naming->extractBaseName($className);
            
            $markdown .= sprintf(
                "## %s\n\n- **Command:** `%s`\n- **Class:** `%s`\n\n",
                ucfirst(str_replace('-', ' ', $command)),
                $command,
                $className
            );
        }
        
        return $markdown;
    }
}
```

### Cas 5 : Migration entre versions

**Problème :** Migrer d'une ancienne convention de nommage vers la nouvelle.

```php
class DirectiveMigrator
{
    private DirectiveNamingService $naming;
    
    public function migrateClassName(string $oldClassName): string
    {
        // Extraire le nom de base de l'ancienne convention
        // Ancienne convention: user_create_directive
        $baseName = str_replace('_directive', '', strtolower($oldClassName));
        $baseName = str_replace('_', '-', $baseName);
        
        // Générer le nouveau nom selon la convention
        return $this->naming->generateClassName($baseName);
    }
}

// 'user_create_directive' -> 'UserCreateDirective'
```

## Flux d'exécution

### Conversion kebab-case → PascalCase

<img src="../graphics/directive-naming-service.png" alt="Directive Naming Service" wodth="800" />

### Conversion PascalCase → kebab-case

```
extractBaseName('UserProfileCreateV2Directive')
    ↓
Supprimer suffixe 'Directive' → 'UserProfileCreateV2'
    ↓
PregSplit sur les majuscules → ['User', 'Profile', 'Create', 'V2']
    ↓
Implode avec '-' → 'User-Profile-Create-V2'
    ↓
strtolower() → 'user-profile-create-v2'
```

## Gestion des erreurs

Aucune exception n'est levée directement par ce service. Cependant, les comportements limites sont gérés :

| Situation | Comportement | Exemple |
|-----------|--------------|---------|
| Nom vide | Génère une classe `Directive` (seulement suffixe) | `''` → `'Directive'` |
| Nom avec tirets consécutifs | Crée des segments vides | `'user--create'` → `'UserCreateDirective'` (segment vide ignoré) |
| Nom avec chiffres | Les chiffres sont préservés | `'v2-api'` → `'V2ApiDirective'` |
| Classe sans suffixe | Extrait quand même le nom de base | `'UserCreate'` → `'user-create'` |

## Intégration

### Avec un générateur de code

```php
class DirectiveGenerator
{
    public function __construct(
        private DirectiveNamingService $naming,
        private string $stubPath,
    ) {}
    
    public function generate(string $name): void
    {
        $className = $this->naming->generateClassName($name);
        $stub = file_get_contents($this->stubPath);
        $content = $this->naming->replaceStubVariables($stub, $className, $name);
        
        file_put_contents("src/Directives/{$className}.php", $content);
    }
}
```

### Avec un système de plugins

```php
class PluginLoader
{
    private DirectiveNamingService $naming;
    
    public function loadPlugin(string $pluginClass): void
    {
        // Extraire le nom de commande depuis la classe
        $commandName = $this->naming->extractBaseName($pluginClass);
        
        // Enregistrer la directive
        $this->register($commandName, $pluginClass);
    }
}
```

### Pattern Builder pour les signatures

```php
$signature = $naming->generateSignatureWithOption($name);
// Peut être combiné avec d'autres services
$fullSignature = $signature . ' {--force}';
```

## Performance

- **Complexité temporelle :** O(n) où n est le nombre de segments dans le nom
- **Opérations :**
  - `explode()` : O(n)
  - `ucfirst()` : O(m) par segment (m = longueur du segment)
  - `preg_split()` : O(n) pour l'extraction
- **Mémoire :** Stocke le tableau de segments temporairement
- **Optimisation :** Les opérations sont légères et adaptées à une utilisation fréquente

### Benchmarks indicatifs

| Opération | Taille moyenne | Temps moyen |
|-----------|---------------|-------------|
| generateClassName | 2-4 segments | ~0.3 µs |
| extractBaseName | 2-4 segments | ~0.5 µs |
| replaceStubVariables | 4 placeholders | ~1.0 µs |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveNamingService;

$naming = new DirectiveNamingService();

// ==================== Conversion des noms ====================

echo "=== Name Conversion ===\n\n";

$directiveNames = [
    'user-create',
    'cache-clear',
    'db-migrate-fresh',
    'api-v2',
    'generate-report-daily',
];

foreach ($directiveNames as $name) {
    $className = $naming->generateClassName($name);
    $extracted = $naming->extractBaseName($className);
    
    echo sprintf(
        "%-25s → %-30s → %-20s %s\n",
        $name,
        $className,
        $extracted,
        $extracted === $name ? '✅' : '❌'
    );
}

// ==================== Génération de signatures ====================

echo "\n=== Signature Generation ===\n\n";

foreach ($directiveNames as $name) {
    $signature = $naming->generateSignatureWithOption($name);
    echo "{$name} → {$signature}\n";
}

// ==================== Template de stub ====================

echo "\n=== Stub Generation ===\n\n";

$stubTemplate = '<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\Attributes\AsDirective;
use AndyDefer\Directive\Contracts\DirectiveInterface;

#[AsDirective(name: "{{signature}}")]
class {{class}} implements DirectiveInterface
{
    /**
     * {{description}}
     *
     * @generated {{date}}
     */
    public function __invoke(array $parameters = []): void
    {
        // TODO: Implement directive logic
    }
}';

// Générer une directive
$className = $naming->generateClassName('send-welcome-email');
$content = $naming->replaceStubVariables(
    $stubTemplate,
    $className,
    'send-welcome-email'
);

echo $content;

// ==================== Validation des conversions ====================

echo "\n=== Round-trip Validation ===\n\n";

$testCases = [
    'simple-name',
    'multiple-hyphens-name',
    'with-numbers-v2',
    'complex-name-with-multiple-parts',
];

$allPassed = true;

foreach ($testCases as $original) {
    $className = $naming->generateClassName($original);
    $extracted = $naming->extractBaseName($className);
    $passed = $original === $extracted;
    $allPassed = $allPassed && $passed;
    
    echo sprintf(
        "%-40s → %-35s → %-40s %s\n",
        $original,
        $className,
        $extracted,
        $passed ? '✅' : '❌'
    );
}

echo "\n" . ($allPassed ? "✅ All tests passed!" : "❌ Some tests failed") . "\n";

// ==================== Utilisation pratique ====================

echo "\n=== Practical Usage ===\n\n";

class DirectiveScanner
{
    private DirectiveNamingService $naming;
    
    public function __construct(DirectiveNamingService $naming)
    {
        $this->naming = $naming;
    }
    
    public function scan(string $directory): array
    {
        $directives = [];
        $files = glob($directory . '/*Directive.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $command = $this->naming->extractBaseName($className);
            
            $directives[] = [
                'class' => $className,
                'command' => $command,
                'file' => $file,
                'signature' => $this->naming->generateSignatureWithOption($command),
            ];
        }
        
        return $directives;
    }
}

$scanner = new DirectiveScanner($naming);
$directives = $scanner->scan(__DIR__ . '/../src/Directives');

echo sprintf("Found %d directives:\n", count($directives));
foreach ($directives as $directive) {
    echo sprintf("  - %s (%s)\n", $directive['command'], $directive['class']);
}
```
---
<!-- ==== ./docs/api-reference/services/directive-parser-service.md ==== -->

# DirectiveParserService - Référence Technique

## Description

Service de parsing et de validation des signatures de directives. Il agit comme un wrapper autour du `SignatureParser`, fournissant une interface unifiée pour toutes les opérations de parsing : validation des signatures, parsing des requêtes, et gestion des parsers personnalisés.

## Hiérarchie / Implémentations

```
ParserRegistryInterface
    └── SignatureParserInterface
        └── DirectiveParserInterface
            └── DirectiveParserService (final)
```

## Rôle principal

Centraliser toutes les opérations de parsing de signatures dans une interface unique. Le service permet de :
1. Parser les requêtes utilisateur contre une signature
2. Valider la syntaxe des signatures
3. Gérer un registre de parsers personnalisés
4. Extraire les éléments d'une signature ou d'une requête

## Installation

### Dépendances

```bash
# Le service dépend du package SignatureParser
composer require andydefer/signature-parser
```

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(DirectiveParserInterface::class, function ($app) {
    return new DirectiveParserService(
        new SignatureParser()
    );
});
```

## API / Méthodes publiques

### `parse(string $signature, string $query): ParsedSignatureRecord`

Parse une requête utilisateur contre une définition de signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature (ex: `"user:create {name} {--admin}"`) |
| `$query` | `string` | La requête à parser (ex: `"John --admin"`) |

**Retourne :** `ParsedSignatureRecord` - Les données parsées (arguments, flags, etc.)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$result = $parser->parse('user:create {name} {--admin}', 'John --admin');
// ParsedSignatureRecord avec les données
```

---

### `validate(string $signature, string $query): ValidationResultRecord`

Valide une requête contre une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature |
| `$query` | `string` | La requête à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$result = $parser->validate('user:create {name}', '');
// ValidationResultRecord avec isValid = false
```

---

### `isValid(string $signature, string $query): bool`

Vérifie rapidement si une requête est valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature |
| `$query` | `string` | La requête à vérifier |

**Retourne :** `bool` - `true` si la requête est valide, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
<?php

if ($parser->isValid('user:create {name}', 'John')) {
    echo "Requête valide";
}
```

---

### `getValidationErrors(string $signature, string $query): StringTypedCollection`

Récupère les erreurs de validation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature |
| `$query` | `string` | La requête validée |

**Retourne :** `StringTypedCollection` - Collection des messages d'erreur

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$errors = $parser->getValidationErrors('user:create {name}', '');

foreach ($errors as $error) {
    echo "❌ " . $error . PHP_EOL;
}
```

---

### `validateSignature(string $signature): ValidationResultRecord`

Valide une définition de signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature à valider |

**Retourne :** `ValidationResultRecord` - Résultat de la validation

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$result = $parser->validateSignature('user:create {name}');
// Vérifie la syntaxe de la signature
```

---

### `isSignatureValid(string $signature): bool`

Vérifie rapidement si une signature est syntaxiquement valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La définition de la signature à vérifier |

**Retourne :** `bool` - `true` si la signature est valide, `false` sinon

**Exceptions :** Aucune

**Exemple :**
```php
<?php

if ($parser->isSignatureValid('user:create {name}')) {
    echo "Signature valide";
}
```

---

### `addParser(ParserInterface $parser): self`

Ajoute un parser personnalisé au registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `ParserInterface` | Le parser à ajouter |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$parser->addParser(new CustomParser());
```

---

### `removeParser(string $parserClass): self`

Retire un parser du registre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parserClass` | `string` | Le nom de classe du parser à retirer |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$parser->removeParser(CustomParser::class);
```

---

### `getParsers(): array`

Récupère tous les parsers enregistrés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, ParserInterface>` - Liste des parsers enregistrés

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$parsers = $parser->getParsers();
foreach ($parsers as $p) {
    echo get_class($p) . PHP_EOL;
}
```

---

### `extractSignatureElements(string $signature): StringTypedCollection`

Extrait les éléments individuels d'une signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | La signature à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$elements = $parser->extractSignatureElements('user:create {name} {--admin}');
// ['user:create', '{name}', '{--admin}']
```

---

### `extractQueryElements(string $query): StringTypedCollection`

Extrait les éléments individuels d'une requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête à analyser |

**Retourne :** `StringTypedCollection` - Collection des éléments

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$elements = $parser->extractQueryElements('John --admin');
// ['John', '--admin']
```

## Format des signatures

### Syntaxe de base

```
commande {argument} {argument?} {argument=default} {--flag} {--flag=value} {arguments*}
```

### Éléments supportés

| Élément | Syntaxe | Exemple |
|---------|---------|---------|
| Arguments requis | `{nom}` | `{name}` |
| Arguments optionnels | `{nom?}` | `{name?}` |
| Arguments par défaut | `{nom=default}` | `{name=John}` |
| Flags (booléens) | `{--nom}` | `{--admin}` |
| Flags avec valeur | `{--nom=valeur}` | `{--format=gzip}` |
| Arguments variadiques | `{nom*}` | `{files*}` |

### Exemples de signatures

```php
// Simple
'list'

// Avec argument
'user:create {name}'

// Avec arguments multiples
'user:create {name} {email} {--admin}'

// Avec arguments optionnels et flags
'backup {file?} {--force} {--compression=gzip}'

// Avec arguments variadiques
'copy {source*} {--recursive}'
```

## Cas d'utilisation

### Cas 1 : Parsing d'une requête utilisateur

```php
<?php

$parser = app(DirectiveParserInterface::class);
$signature = 'backup {file?} {--force} {--compression=gzip}';

// Requête utilisateur : "backup database.sql --force"
$result = $parser->parse($signature, 'backup database.sql --force');

// Accès aux arguments
$file = $result->required->get('file') ?? $result->default->get('file');
$force = $result->flags->get('force');
$compression = $result->flags->get('compression');

echo "Fichier: " . ($file ?? 'aucun') . PHP_EOL;
echo "Force: " . ($force ? 'oui' : 'non') . PHP_EOL;
echo "Compression: " . ($compression ?? 'gzip') . PHP_EOL;
```

### Cas 2 : Validation interactive

```php
<?php

class InteractiveDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        $parser = $this->getParser();
        
        // Valider la requête
        $result = $parser->validate($this->getSignature(), $this->getQuery());
        
        if (!$result->isValid()) {
            foreach ($result->getErrors() as $error) {
                $this->error($error);
            }
            return ExitCode::INVALID_ARGUMENT;
        }
        
        // Si valide, parser
        $parsed = $parser->parse($this->getSignature(), $this->getQuery());
        // ... utiliser les données
    }
}
```

### Cas 3 : Extensions avec parsers personnalisés

```php
<?php

use AndyDefer\SignatureParser\Contracts\ParserInterface;

class CustomParser implements ParserInterface
{
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        // Logique de parsing personnalisée
    }
}

$parser = app(DirectiveParserInterface::class);
$parser->addParser(new CustomParser());

// Le parser personnalisé est maintenant disponible
```

### Cas 4 : Extraction et analyse

```php
<?php

$parser = app(DirectiveParserInterface::class);

// Extraire les éléments pour analyse
$signatureElements = $parser->extractSignatureElements('user:create {name} {--admin}');
$queryElements = $parser->extractQueryElements('John --admin');

echo "Signature elements: " . $signatureElements->join(', ') . PHP_EOL;
echo "Query elements: " . $queryElements->join(', ') . PHP_EOL;
```

## Flux d'exécution

```
DirectiveParserService::parse($signature, $query)
    │
    └── $this->parser->parse($signature, $query)
        │
        ├── Validation de la signature
        ├── Parsing des arguments
        │   ├── Arguments requis → required
        │   ├── Arguments optionnels → default
        │   ├── Arguments variadiques → variadic
        │   └── Flags → flags
        ├── Validation de la requête
        │   ├── Présence des arguments requis
        │   ├── Types des flags
        │   └── Valeurs par défaut
        │
        └── Retourne ParsedSignatureRecord
            ├── required (TypedRecord)
            ├── default (TypedRecord)
            ├── variadic (VariadicCollection)
            ├── flags (FlagCollection)
            └── source (string)
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Signature invalide | `ValidationResultRecord` avec `isValid = false` | `Invalid signature: {message}` |
| Requête invalide | `ValidationResultRecord` avec `isValid = false` | `Missing required argument: {name}` |
| Argument manquant | `ValidationResultRecord` avec `isValid = false` | `Required argument "{name}" is missing` |
| Format de flag invalide | `ValidationResultRecord` avec `isValid = false` | `Invalid flag format: {flag}` |
| Type inattendu | `ValidationResultRecord` avec `isValid = false` | `Unexpected token: {token}` |

### Messages d'erreur typiques

```php
// Signature invalide
"Invalid signature: Missing closing brace"

// Argument requis manquant
"Required argument 'name' is missing"

// Flag invalide
"Invalid flag format: --admin=value should be --admin=value"

// Token inattendu
"Unexpected token: '--force' at position 5"
```

## Intégration

Le `DirectiveParserService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `SignatureParser` | Parsing des signatures et requêtes |
| `AbstractDirective` | Parsing des arguments et flags |
| `DirectiveDiscoveryService` | Validation des signatures réservées |
| `ParserInterface` | Extensibilité via parsers personnalisés |

### Utilisation dans AbstractDirective

```php
abstract class AbstractDirective
{
    private DirectiveParserService $parser;
    
    public function __construct(Application $app, string $query)
    {
        $this->parser = $app->make(DirectiveParserService::class);
        $this->parsed = $this->parser->parse($this->getSignature(), $query);
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n) | n = nombre d'éléments à parser |
| Temps typique | 1-5ms | Parsing simple |
| Mémoire | < 1KB | Données parsées |
| Cache | ❌ Non | Parsing à chaque appel |

### Facteurs de performance

1. **Longueur de la signature** : Plus la signature est complexe, plus le parsing est lent
2. **Nombre d'arguments** : Plus d'arguments → plus de temps de parsing
3. **Flags** : Les flags avec valeurs ajoutent de la complexité
4. **Parsers personnalisés** : Des parsers complexes peuvent ralentir le processus

### Optimisations

```php
class CachedParserService
{
    private array $parseCache = [];
    private array $validateCache = [];
    
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        $key = md5($signature . '|' . $query);
        
        if (isset($this->parseCache[$key])) {
            return $this->parseCache[$key];
        }
        
        $result = $this->parser->parse($signature, $query);
        $this->parseCache[$key] = $result;
        return $result;
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| PHP 8.3+ | ✅ Complet | - |
| SignatureParser 1.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveParserService;
use AndyDefer\SignatureParser\SignatureParser;

// Créer le parser
$parser = new DirectiveParserService(new SignatureParser());

// 1. Définir une signature
$signature = 'user:create {name} {email?} {--admin} {--role=user} {tags*}';

// 2. Valider la signature
$validation = $parser->validateSignature($signature);
if (!$validation->isValid()) {
    foreach ($validation->getErrors() as $error) {
        echo "❌ " . $error . PHP_EOL;
    }
    exit(1);
}

echo "✅ Signature valide\n\n";

// 3. Parser différentes requêtes
$queries = [
    'user:create John john@example.com --admin --role=admin',
    'user:create Jane --admin tags:php,tags:laravel',
    'user:create Bob --role=editor'
];

foreach ($queries as $query) {
    echo "Requête: " . $query . PHP_EOL;
    
    // Valider la requête
    if (!$parser->isValid($signature, $query)) {
        $errors = $parser->getValidationErrors($signature, $query);
        echo "  ❌ Erreurs:\n";
        foreach ($errors as $error) {
            echo "    - " . $error . PHP_EOL;
        }
        continue;
    }
    
    // Parser la requête
    $result = $parser->parse($signature, $query);
    
    echo "  ✅ Parse réussi:\n";
    echo "    Name: " . ($result->required->get('name') ?? 'non fourni') . PHP_EOL;
    echo "    Email: " . ($result->default->get('email') ?? 'non fourni') . PHP_EOL;
    echo "    Admin: " . ($result->flags->get('admin') ? 'oui' : 'non') . PHP_EOL;
    echo "    Role: " . ($result->flags->get('role') ?? 'user') . PHP_EOL;
    
    $tags = $result->variadic->getAllValues();
    if (!empty($tags)) {
        echo "    Tags: " . implode(', ', $tags) . PHP_EOL;
    }
    
    echo PHP_EOL;
}

// 4. Extraire les éléments
$elements = $parser->extractSignatureElements($signature);
echo "Éléments de la signature:\n";
foreach ($elements as $element) {
    echo "  - " . $element . PHP_EOL;
}

// 5. Gérer les parsers personnalisés
class MyCustomParser implements ParserInterface
{
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        // Logique personnalisée
    }
}

$parser->addParser(new MyCustomParser());
$parsers = $parser->getParsers();
echo "Parsers enregistrés: " . count($parsers) . PHP_EOL;
```

## Notes techniques

### Syntaxe de signature détaillée

```php
// Arguments requis - doivent être présents
{name}

// Arguments optionnels - peuvent être omis
{name?}

// Arguments avec valeur par défaut - utilisés si omis
{name=default}

// Flags booléens - présence = true, absence = false
{--flag}

// Flags avec valeur - doivent être spécifiés
{--flag=value}

// Arguments variadiques - peuvent apparaître plusieurs fois
{tags*}
```

### Validation des types

Le parser supporte la validation des types à l'avenir via des annotations :

```php
// Proposition future
{name:string}   // Doit être une chaîne
{age:int}       // Doit être un entier
{active:bool}   // Doit être un booléen
```

### Gestion des espaces

Les arguments avec espaces peuvent être entre guillemets :

```php
// Requête: user:create "John Doe"
// L'argument name sera "John Doe"
```

### Chaînage des méthodes

```php
$parser
    ->addParser(new CustomParser())
    ->parse($signature, $query);
```

### Extensibilité

Le service permet d'ajouter des parsers personnalisés pour des besoins spécifiques :

```php
// Parser pour des formats de date personnalisés
$parser->addParser(new DateParser());

// Parser pour des expressions régulières
$parser->addParser(new RegexParser());
```
---
<!-- ==== ./docs/api-reference/services/composer-reader-service.md ==== -->

# ComposerReaderService - Référence Technique

## Description

Service de lecture et d'accès aux informations des packages Composer. Fournit une abstraction typée sur le fichier `composer.json`, permettant de récupérer les dépendances, la configuration d'autoloading et les métadonnées des packages.

## Hiérarchie / Implémentations

```
ComposerReaderInterface
    └── ComposerReaderService (final)
```

## Rôle principal

Centraliser l'accès au fichier `composer.json` du projet en fournissant une API typée et sécurisée. Le service gère la lecture, le parsing, la mise en cache et la validation des données du fichier Composer.

## Installation

### Dépendances

```bash
# Le service est automatiquement disponible via le conteneur
composer require andydefer/laravel-directive
```

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(ComposerReaderInterface::class, function ($app) {
    return new ComposerReaderService(
        $app->make(DirectiveConfigInterface::class),
        $app->make(FileSystemInterface::class)
    );
});
```

## API / Méthodes publiques

### `getRequire(): array`

Récupère les dépendances de production du fichier `composer.json`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, string>` - Tableau associatif [nom du package → contrainte de version]

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$dependencies = $composerReader->getRequire();
// ['laravel/framework' => '^10.0', 'php' => '^8.1']
```

---

### `getRequireDev(): array`

Récupère les dépendances de développement du fichier `composer.json`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, string>` - Tableau associatif [nom du package → contrainte de version]

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$devDependencies = $composerReader->getRequireDev();
// ['phpunit/phpunit' => '^10.0', 'pestphp/pest' => '^2.0']
```

---

### `getAllDependencies(): array`

Récupère toutes les dépendances (production + développement).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, string>` - Tableau associatif de toutes les dépendances

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$allDependencies = $composerReader->getAllDependencies();
// ['laravel/framework' => '^10.0', 'phpunit/phpunit' => '^10.0']
```

---

### `getVendorDirectories(): array`

Récupère la liste des noms de vendors depuis les dépendances de production.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de vendors (uniques)

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$vendors = $composerReader->getVendorDirectories();
// ['laravel', 'symfony', 'monolog']
```

---

### `getPackageNames(): array`

Récupère la liste des noms de packages (production uniquement).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de packages

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$packages = $composerReader->getPackageNames();
// ['laravel/framework', 'symfony/console', 'monolog/monolog']
```

---

### `hasPackage(string $packageName): bool`

Vérifie si un package spécifique est installé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Le nom du package à vérifier (ex: "laravel/framework") |

**Retourne :** `bool` - `true` si le package existe, `false` sinon

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

if ($composerReader->hasPackage('laravel/framework')) {
    echo "Laravel est installé";
}
```

---

### `getPackageVersion(string $packageName): ?string`

Récupère la contrainte de version d'un package spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$packageName` | `string` | Le nom du package à interroger |

**Retourne :** `string|null` - La contrainte de version, ou `null` si le package n'est pas trouvé

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$version = $composerReader->getPackageVersion('laravel/framework');
// '^10.0'

if ($version === null) {
    echo "Package non installé";
}
```

---

### `getAutoload(): array`

Récupère la configuration d'autoloading de production.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, mixed>` - La configuration d'autoloading

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$autoload = $composerReader->getAutoload();
// ['psr-4' => ['App\\' => 'app/'], 'classmap' => ['database/']]
```

---

### `getAutoloadDev(): array`

Récupère la configuration d'autoloading de développement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<string, mixed>` - La configuration d'autoloading-dev

**Exceptions :** `RuntimeException` - Si le fichier `composer.json` ne peut pas être lu ou parsé

**Exemple :**
```php
<?php

$autoloadDev = $composerReader->getAutoloadDev();
// ['psr-4' => ['Tests\\' => 'tests/']]
```

---

### `getVendorDir(): string`

Récupère le chemin absolu du répertoire vendor.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Le chemin absolu du répertoire vendor

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$vendorDir = $composerReader->getVendorDir();
// '/var/www/project/vendor'
```

## Cas d'utilisation

### Cas 1 : Vérification des dépendances requises

```php
<?php

use AndyDefer\Directive\Services\ComposerReaderService;

$requiredPackages = [
    'laravel/framework',
    'symfony/console',
    'monolog/monolog',
];

$missing = [];

foreach ($requiredPackages as $package) {
    if (!$composerReader->hasPackage($package)) {
        $missing[] = $package;
    }
}

if (!empty($missing)) {
    throw new RuntimeException(
        'Missing required packages: ' . implode(', ', $missing)
    );
}
```

### Cas 2 : Analyse des dépendances pour la découverte

```php
<?php

// Dans VendorDirectiveDiscovery
$packages = $composerReader->getPackageNames();

foreach ($packages as $package) {
    $vendor = $composerReader->getVendorDir() . '/' . $package;
    
    if (is_dir($vendor . '/src/Directives')) {
        echo "Package {$package} contient des directives\n";
    }
}
```

### Cas 3 : Génération d'un rapport de dépendances

```php
<?php

$dependencies = $composerReader->getAllDependencies();

echo "=== Rapport des dépendances ===\n";
echo "Total: " . count($dependencies) . " packages\n\n";

foreach ($dependencies as $package => $version) {
    $isDev = array_key_exists($package, $composerReader->getRequireDev());
    $type = $isDev ? 'DEV' : 'PROD';
    
    echo "[{$type}] {$package} : {$version}\n";
}
```

### Cas 4 : Configuration d'autoloading personnalisée

```php
<?php

$autoload = $composerReader->getAutoload();

if (isset($autoload['psr-4'])) {
    foreach ($autoload['psr-4'] as $namespace => $path) {
        echo "Namespace: {$namespace} → Path: {$path}\n";
    }
}
```

## Flux d'exécution

```
ComposerReaderService::getComposerData()
    │
    ├── Vérifie le cache ($composerData)
    │   └── Si présent → retourne
    │
    ├── $composerPath = $config->getComposerPath()
    │
    ├── validateComposerFileExists()
    │   └── Si non existant → RuntimeException
    │
    ├── readComposerFile()
    │   ├── $fileSystem->get()
    │   └── Si erreur → RuntimeException
    │
    ├── parseComposerJson()
    │   ├── json_decode()
    │   └── Si JSON invalide → RuntimeException
    │
    └── Mise en cache → retourne les données
```

## Structure du composer.json analysé

```json
{
    "require": {
        "laravel/framework": "^10.0",
        "symfony/console": "^6.0"
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
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fichier composer.json inexistant | `RuntimeException` | `composer.json not found at: {path}` |
| Fichier non lisible | `RuntimeException` | `Could not read composer.json at: {path}` |
| JSON invalide | `RuntimeException` | `Invalid JSON in composer.json at {path}: {error}` |
| Package PHP (php, php-64bit) | Ignoré | - |
| Format de package invalide | Ignoré | - |

### Exceptions détaillées

```php
// Exemple 1: Fichier manquant
composer.json not found at: /var/www/project/composer.json

// Exemple 2: JSON invalide
Invalid JSON in composer.json at /var/www/project/composer.json: Syntax error

// Exemple 3: Erreur de lecture
Could not read composer.json at: /var/www/project/composer.json
```

## Intégration

Le `ComposerReaderService` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveConfigInterface` | Fournit le chemin du fichier composer.json |
| `FileSystemInterface` | Opérations de lecture de fichiers |
| `VendorDirectiveDiscovery` | Utilisé pour découvrir les packages vendors |
| `DependencyResolverService` | Utilisé pour résoudre l'arbre des dépendances |

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(1) | Lecture et parsing du fichier JSON |
| Cache | ✅ Oui | Les données sont mises en cache après la première lecture |
| Temps typique | 5-20ms | Première lecture, puis <1ms (cache) |
| Mémoire | ~100KB | Dépend de la taille du fichier composer.json |

### Stratégie de cache

```php
private ?array $composerData = null;

private function getComposerData(): array
{
    if ($this->composerData !== null) {
        return $this->composerData; // ✅ Cache hit
    }
    
    // Chargement et mise en cache
    $this->composerData = $this->loadComposerData();
    return $this->composerData;
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Composer 2.x | ✅ Complet | - |
| Composer 1.x | ⚠️ Limité | `composer.json` version 1 supporté |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Configs\DirectiveConfig;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Config\Repository as ConfigRepository;

// Créer les dépendances
$configRepository = new ConfigRepository([
    'directive' => [
        'base_path' => '/var/www/project'
    ]
]);

$config = new DirectiveConfig($configRepository);
$fileSystem = new FileSystemService();

// Créer le service
$composerReader = new ComposerReaderService($config, $fileSystem);

// Utiliser le service
echo "=== Informations Composer ===\n\n";

// Dépendances de production
$prod = $composerReader->getRequire();
echo "Dépendances PROD (" . count($prod) . "):\n";
foreach ($prod as $package => $version) {
    echo "- {$package}: {$version}\n";
}

// Dépendances de développement
$dev = $composerReader->getRequireDev();
echo "\nDépendances DEV (" . count($dev) . "):\n";
foreach ($dev as $package => $version) {
    echo "- {$package}: {$version}\n";
}

// Toutes les dépendances
$all = $composerReader->getAllDependencies();
echo "\nTotal dépendances: " . count($all) . "\n";

// Vérification d'un package
if ($composerReader->hasPackage('laravel/framework')) {
    $version = $composerReader->getPackageVersion('laravel/framework');
    echo "\n✅ Laravel installé (version: {$version})\n";
}

// Autoloading
$autoload = $composerReader->getAutoload();
if (isset($autoload['psr-4'])) {
    echo "\n=== Autoload PSR-4 ===\n";
    foreach ($autoload['psr-4'] as $namespace => $path) {
        echo "- {$namespace} → {$path}\n";
    }
}

// Vendor directory
$vendorDir = $composerReader->getVendorDir();
echo "\n📁 Vendor directory: {$vendorDir}\n";
```

## Notes techniques

### Packages PHP ignorés

Les packages commençant par `php` sont automatiquement ignorés par les méthodes `getPackageNames()` et `getVendorDirectories()` :

```php
// Ces packages sont ignorés
- php (meta-package)
- php-64bit
- php-80 (extension)
- php-81 (extension)
```

### Format des packages

Le service attend un format de package standard : `vendor/package`.

```php
// ✅ Format valide
'laravel/framework'
'symfony/console'
'monolog/monolog'

// ❌ Format invalide
'laravel'              // Pas de vendor
'laravel/framework/'   // Slash final
'/laravel/framework'   // Slash initial
```

### Gestion des versions

Les versions sont retournées telles quelles, sans parsing :

```php
// Exemples de versions retournées
- '^10.0'
- '~6.0'
- '>=7.0'
- 'dev-master'
- '1.2.3'
```

### Bonnes pratiques

1. **Utiliser le cache** : Le service gère automatiquement le cache
2. **Vérifier l'existence** : Utiliser `hasPackage()` avant `getPackageVersion()`
3. **Gérer les exceptions** : Toujours capturer `RuntimeException` lors des opérations
4. **Validation des packages** : Vérifier que les packages ont le format `vendor/package`

---
<!-- ==== ./docs/api-reference/discovers/workspace-directive-discovery.md ==== -->

# WorkspaceDirectiveDiscovery - Référence Technique

## Description

Source de découverte qui scanne les répertoires de l'application (workspace) pour trouver des directives définies par le développeur. Par défaut, elle recherche dans `src/Directives` et `app/Directives`, mais peut être configurée pour scanner des chemins personnalisés.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── WorkspaceDirectiveDiscovery (final)
```

## Rôle principal

Permettre aux développeurs de créer leurs propres directives dans l'application sans configuration supplémentaire. La classe découvre automatiquement toutes les classes qui étendent `AbstractDirective` dans les dossiers configurés.

## Installation

Cette classe est utilisée automatiquement par le service de découverte. Aucune configuration manuelle n'est nécessaire.

### Configuration via le fichier de config

```php
// config/directive.php
return [
    'directories' => [
        'app/Directives',
        'src/Directives',
        'app/Console/Commands/Directives', // Dossier personnalisé
    ],
];
```

## API / Méthodes publiques

### `discover(): array`

Découvre toutes les directives présentes dans le workspace de l'application.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de classes qualifiés (FQCN)

**Exceptions :** Aucune (les répertoires inexistants sont ignorés silencieusement)

**Exemple :**
```php
<?php

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;

$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$directives = $discovery->discover();
// Retourne les directives trouvées dans src/Directives et app/Directives
```

---

### `addPath(string $path): self`

Ajoute un chemin personnalisé à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin relatif à la racine du projet |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$discovery
    ->addPath('app/CustomDirectives')
    ->addPath('modules/Admin/Directives');
```

---

### `addPaths(array $paths): self`

Ajoute plusieurs chemins personnalisés à scanner.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$paths` | `array<int, string>` | Liste des chemins relatifs à la racine du projet |

**Retourne :** `self` - L'instance courante (fluent interface)

**Exceptions :** Aucune

**Exemple :**
```php
<?php

$discovery->addPaths([
    'app/CustomDirectives',
    'modules/Admin/Directives',
    'packages/Acme/Directives',
]);
```

## Cas d'utilisation

### Cas 1 : Structure de projet Laravel standard

```php
// Dossiers par défaut
// app/Directives/
//   UserDirective.php
//   AdminDirective.php

// src/Directives/
//   ApiDirective.php

// Découverte automatique
$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$directives = $discovery->discover();
// Retourne toutes les directives dans ces dossiers
```

### Cas 2 : Structure modulaire

```php
// Structure : app/Modules/Admin/Directives/
// app/Modules/
//   Admin/
//     Directives/
//       DashboardDirective.php
//     Commands/
//   Api/
//     Directives/
//       EndpointDirective.php

// Configuration
$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);
$discovery->addPaths([
    'app/Modules/Admin/Directives',
    'app/Modules/Api/Directives',
]);

$directives = $discovery->discover();
// Retourne DashboardDirective et EndpointDirective
```

### Cas 3 : Configuration via fichier config

```php
// config/directive.php
<?php

return [
    'directories' => [
        'app/Console/Directives',
        'app/Http/Directives',
        'modules/*/Directives', // Pattern glob (à développer)
    ],
];

// Dans le service provider
$config = app(DirectiveConfigInterface::class);
$discovery = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config // Utilise la configuration
);
```

### Cas 4 : Ajout dynamique lors de l'exécution

```php
<?php

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;

class MyServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $discovery = $this->app->make(WorkspaceDirectiveDiscovery::class);
        
        // Ajouter des chemins basés sur les modules actifs
        if ($this->moduleExists('Admin')) {
            $discovery->addPath('modules/Admin/Directives');
        }
        
        if ($this->moduleExists('Api')) {
            $discovery->addPath('modules/Api/Directives');
        }
    }
}
```

## Flux d'exécution

```
WorkspaceDirectiveDiscovery::discover()
    │
    ├── Vérifie $cache
    │   └── Si cache présent → retourne
    │
    └── doDiscover()
        │
        ├── getProjectRoot()
        │   └── getcwd() (vérifié)
        │
        ├── getScanPaths()
        │   ├── DEFAULT_PATHS
        │   ├── Config::getDirectories() (si config présent)
        │   └── $customPaths
        │
        └── foreach($paths)
            │
            ├── fullPath = projectRoot + path
            ├── Vérifie isDirectory()
            └── scanner->scan(fullPath, maxDepth)
```

## Structure de recherche

### Ordre de priorité des chemins

1. **Config `directories`** (si `$config` fourni)
2. **Chemins par défaut** (`src/Directives`, `app/Directives`)
3. **Chemins personnalisés** (ajoutés via `addPath()`)

Les chemins sont fusionnés, pas remplacés.

### Chemins par défaut

```php
const DEFAULT_PATHS = [
    'src/Directives',    // 1. Pour les applications modernes
    'app/Directives',    // 2. Pour les applications Laravel traditionnelles
];
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Répertoire inexistant | Ignoré silencieusement | - |
| Répertoire non accessible | Ignoré silencieusement | - |
| Chemin invalide | Ignoré silencieusement | - |
| Project root non déterminable | Exception | `Unable to determine current working directory` |
| Erreur de scan | Ignorée (logique interne du scanner) | - |

### Exceptions explicites

| Exception | Condition | Message |
|-----------|-----------|---------|
| `\RuntimeException` | `getcwd()` retourne `false` | `Unable to determine current working directory` |

## Intégration

La classe `WorkspaceDirectiveDiscovery` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `FileSystemInterface` | Opérations sur le système de fichiers |
| `DirectiveScannerInterface` | Scan des classes PHP |
| `DirectiveConfigInterface` | Configuration optionnelle |
| `DirectiveDiscoveryService` | Orchestration de la découverte |

### Ordre dans le processus de découverte

```
1. BuiltInDirectiveDiscovery      (prioritaire)
2. WorkspaceDirectiveDiscovery    (projet)  ← Vous êtes ici
3. VendorDirectiveDiscovery        (packages)
4. CustomSources                  (personnalisées)
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n) | n = nombre de dossiers à scanner |
| Cache | ✅ Oui | Les résultats sont mis en cache |
| Invalidation | Automatique | Le cache est vidé lors de l'ajout de chemins |
| Mémoire | ~1-2 MB | Dépend du nombre de fichiers PHP |

### Stratégie de cache

```php
public function discover(): array
{
    // Cache actif
    if ($this->cache !== null) {
        return $this->cache;
    }
    
    // Calcul et mise en cache
    $this->cache = $this->doDiscover();
    return $this->cache;
}

// Le cache est invalidé lors des modifications
public function addPath(string $path): self
{
    if (!in_array($path, $this->customPaths, true)) {
        $this->customPaths[] = $path;
        $this->cache = null; // Invalidation
    }
    return $this;
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |
| Windows | ✅ Complet | Utilise `DIRECTORY_SEPARATOR` |
| Unix/Linux | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Discovers\WorkspaceDirectiveDiscovery;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

// Créer les dépendances
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Créer le discovery avec configuration par défaut
$discovery = new WorkspaceDirectiveDiscovery($fileSystem, $scanner);

// Ajouter des chemins personnalisés
$discovery->addPaths([
    'app/CustomDirectives',
    'modules/Admin/Directives',
    'packages/Acme/Directives',
]);

// Découvrir les directives
$directives = $discovery->discover();

// Afficher les résultats
echo "Directives trouvées : " . count($directives) . PHP_EOL;

foreach ($directives as $fqcn) {
    echo "- " . $fqcn . PHP_EOL;
    
    // Analyser la directive
    $reflection = new ReflectionClass($fqcn);
    if (!$reflection->isAbstract()) {
        $instance = $reflection->newInstanceWithoutConstructor();
        echo "  Signature: " . $instance->getSignature() . PHP_EOL;
        
        $aliases = $instance->getAliases()->toArray();
        if (!empty($aliases)) {
            echo "  Aliases: " . implode(', ', $aliases) . PHP_EOL;
        }
    }
}

// Exemple avec configuration
$config = app(DirectiveConfigInterface::class);
$discoveryWithConfig = new WorkspaceDirectiveDiscovery(
    $fileSystem,
    $scanner,
    $config
);

// Les chemins de la config sont automatiquement utilisés
$discoveredDirectives = $discoveryWithConfig->discover();
```

## Bonnes pratiques

### 1. Organisation des directives

```
app/
├── Directives/                 # ✅ Bonne pratique
│   ├── UserDirective.php
│   └── AdminDirective.php
├── Console/
│   └── Directives/            # ✅ Alternative
├── Modules/
│   └── Admin/
│       └── Directives/        # ✅ Organisation modulaire
└── Directives/                 # ❌ Dossier à la racine (déconseillé)
```

### 2. Nommage des chemins

```php
// ✅ Utiliser des chemins relatifs à la racine
$discovery->addPath('app/Directives');

// ✅ Utiliser des chemins avec séparateur Unix
$discovery->addPath('app/Modules/Admin/Directives');

// ❌ Éviter les chemins absolus
$discovery->addPath('/var/www/project/app/Directives');

// ❌ Éviter les chemins avec ".."
$discovery->addPath('../app/Directives');
```

### 3. Gestion des modules

```php
class ModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $discovery = $this->app->make(WorkspaceDirectiveDiscovery::class);
        
        // Ajouter automatiquement les directives des modules
        foreach ($this->getActiveModules() as $module) {
            $discovery->addPath("modules/{$module}/Directives");
        }
    }
    
    private function getActiveModules(): array
    {
        // Logique pour déterminer les modules actifs
        return ['Admin', 'Api', 'Blog'];
    }
}
```
---
<!-- ==== ./docs/api-reference/discovers/built-in-directive-discovery.md ==== -->

# BuiltInDirectiveDiscovery - Référence Technique

## Description

Source de découverte pour les directives intégrées au package. Elle fournit les trois directives de base qui sont disponibles par défaut dans Laravel Directive.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── BuiltInDirectiveDiscovery (final)
```

## Rôle principal

Fournir un mécanisme d'auto-découverte pour les directives natives du package. Cette classe garantit que les directives `list`, `help` et `version` sont toujours disponibles, même si l'application ne définit aucune directive personnalisée.

## Directives intégrées

| Directive | Description | Aliases |
|-----------|-------------|---------|
| `ListDirective` | Liste toutes les directives disponibles | `ls`, `-l`, `--list` |
| `HelpDirective` | Affiche l'aide et les options globales | `-h`, `--help` |
| `VersionDirective` | Affiche les informations de version | `-v`, `--version` |

## API / Méthodes publiques

### `discover(): array`

Retourne la liste des classes des directives intégrées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, class-string>` - Liste des noms de classes qualifiés

**Exceptions :** Aucune

**Exemple :**
```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;

$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();

// $directives = [
//     'AndyDefer\Directive\BuiltIn\ListDirective',
//     'AndyDefer\Directive\BuiltIn\HelpDirective',
//     'AndyDefer\Directive\BuiltIn\VersionDirective',
// ]
```

## Cas d'utilisation

### Cas 1 : Découverte des directives intégrées via le service provider

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Dans un service provider Laravel
$this->app->singleton(BuiltInDirectiveDiscovery::class);

// Le service de découverte utilisera automatiquement cette source
$discovery = $this->app->make(DirectiveDiscoveryService::class);
$directives = $discovery->discover(); // Inclut les directives intégrées
```

### Cas 2 : Extension avec des directives intégrées supplémentaires

```php
<?php

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;

// Dans un service provider, vous pouvez ajouter vos propres directives
// mais il est recommandé d'utiliser WorkspaceDirectiveDiscovery ou
// de créer votre propre DiscoverySourceInterface

class CustomBuiltInDirectiveDiscovery extends BuiltInDirectiveDiscovery
{
    private array $builtInDirectives = [
        // Conserver les directives intégrées
        ListDirective::class,
        HelpDirective::class,
        VersionDirective::class,
        // Ajouter vos directives personnalisées
        MyCustomDirective::class,
    ];
}
```

### Cas 3 : Vérification manuelle des directives disponibles

```php
<?php

$discovery = new BuiltInDirectiveDiscovery();
$directives = $discovery->discover();

foreach ($directives as $fqcn) {
    if (is_subclass_of($fqcn, AbstractDirective::class)) {
        $instance = new $fqcn();
        echo "Directive: " . $instance->getSignature() . PHP_EOL;
        echo "Description: " . $instance->getDescription() . PHP_EOL;
    }
}
```

## Flux d'exécution

```
BuiltInDirectiveDiscovery::discover()
    │
    └── return $this->builtInDirectives
        │
        ├── ListDirective::class
        ├── HelpDirective::class
        └── VersionDirective::class
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucune | - | - |

Cette classe ne lève aucune exception car elle travaille avec des valeurs statiques et connues.

## Intégration

La classe `BuiltInDirectiveDiscovery` s'intègre avec :

- **`DirectiveDiscoveryService`** : Utilisée comme l'une des sources de découverte
- **`DirectiveServiceProvider`** : Enregistrée dans le conteneur
- **`AbstractDirective`** : Les classes retournées étendent cette classe abstraite

### Ordre de découverte

Les directives intégrées sont découvertes en **premier** dans le processus :

1. ✅ **BuiltInDirectiveDiscovery** (forcé, prioritaire)
2. `WorkspaceDirectiveDiscovery` (projet)
3. `VendorDirectiveDiscovery` (packages)
4. `CustomSources` (personnalisées)

Les directives intégrées sont marquées comme `force = true` dans le service de découverte, ce qui signifie qu'elles ne peuvent pas être bloquées par des signatures réservées.

## Performance

- **Complexité** : O(1) - retourne un tableau statique
- **Mémoire** : ~200 bytes (3 entrées)
- **Cache** : Aucun nécessaire
- **Optimisation** : Aucune opération lourde, simple retour de tableau

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Discovers\BuiltInDirectiveDiscovery;
use AndyDefer\Directive\AbstractDirective;

// Créer l'instance
$discovery = new BuiltInDirectiveDiscovery();

// Découvrir les directives intégrées
$directives = $discovery->discover();

// Analyser chaque directive
foreach ($directives as $fqcn) {
    echo "Classe: " . $fqcn . PHP_EOL;
    
    // Vérifier que c'est bien une directive valide
    if (!is_subclass_of($fqcn, AbstractDirective::class)) {
        echo "⚠️ " . $fqcn . " n'est pas une directive valide" . PHP_EOL;
        continue;
    }
    
    // Créer une instance sans exécuter le constructeur
    $reflection = new ReflectionClass($fqcn);
    $instance = $reflection->newInstanceWithoutConstructor();
    
    echo "  Signature: " . $instance->getSignature() . PHP_EOL;
    echo "  Description: " . $instance->getDescription() . PHP_EOL;
    
    $aliases = $instance->getAliases()->toArray();
    if (!empty($aliases)) {
        echo "  Aliases: " . implode(', ', $aliases) . PHP_EOL;
    }
    echo PHP_EOL;
}
```

## Notes supplémentaires

### Pourquoi cette classe existe ?

Cette classe permet d'isoler la liste des directives intégrées dans un composant dédié, ce qui facilite :

1. **Tests** : Facilement mockable
2. **Maintenance** : Une seule source de vérité pour les directives par défaut
3. **Extensibilité** : Peut être remplacée par une implémentation personnalisée

### Quand l'utiliser ?

- Directement : Pour obtenir la liste des directives natives
- Indirectement : Via `DirectiveDiscoveryService` qui l'utilise automatiquement
- En test : Pour vérifier les directives disponibles par défaut

### Différence avec les autres sources

| Source | Type | Priorité |
|--------|------|----------|
| `BuiltInDirectiveDiscovery` | Intégrées | 1 (la plus haute) |
| `WorkspaceDirectiveDiscovery` | Projet | 2 |
| `VendorDirectiveDiscovery` | Packages | 3 |
| `CustomSources` | Personnalisées | 4 |

Les directives intégrées ne peuvent pas être écrasées ou ignorées par des directives du même nom.
---
<!-- ==== ./docs/api-reference/discovers/vendor-directive-dscovery.md ==== -->

# VendorDirectiveDiscovery - Référence Technique

## Description

Source de découverte qui scanne les packages Composer installés pour trouver des directives. Elle examine les chemins PSR-4 d'autoloading et les fichiers de configuration personnalisés des packages vendors.

## Hiérarchie / Implémentations

```
DiscoverySourceInterface
    └── VendorDirectiveDiscovery (final)
```

## Rôle principal

Permettre aux packages tiers de fournir leurs propres directives en les découvrant automatiquement. Cela rend l'écosystème Laravel Directive extensible : n'importe quel package Composer peut inclure des directives qui seront automatiquement disponibles.

## Installation

Cette classe est utilisée automatiquement par le service de découverte. Aucune configuration manuelle n'est nécessaire.

```php
// Le service provider l'enregistre automatiquement
$this->app->singleton(VendorDirectiveDiscovery::class, function ($app) {
    return new VendorDirectiveDiscovery(
        $app->make(ComposerReaderInterface::class),
        $app->make(DependencyResolverInterface::class),
        $app->make(FileSystemInterface::class),
        $app->make(DirectiveScannerInterface::class)
    );
});
```

## API / Méthodes publiques

### `discover(): array`

Découvre toutes les directives présentes dans les packages Composer installés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, string>` - Liste des noms de classes qualifiés (FQCN)

**Exceptions :** Aucune (les erreurs sont silencieusement ignorées)

**Exemple :**
```php
<?php

use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;

$discovery = $app->make(VendorDirectiveDiscovery::class);
$directives = $discovery->discover();

// Retourne les classes trouvées dans les packages vendors
// Exemple: ['Vendor\Package\Directives\MyDirective']
```

## Cas d'utilisation

### Cas 1 : Fournir des directives dans un package vendor

```php
// Dans un package vendor (ex: vendor/mon-package/composer.json)
{
    "autoload": {
        "psr-4": {
            "MonPackage\\": "src/"
        }
    }
}

// Structure du package
// vendor/mon-package/
//   src/
//     Directives/
//       MaDirective.php

// La directive sera automatiquement découverte
class MaDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'mon-package:commande';
    }
}
```

### Cas 2 : Configuration personnalisée dans un package vendor

```php
// vendor/mon-package/config/directive.php
<?php

return [
    'custom_sources' => [
        'src/Commands',     // Dossier supplémentaire à scanner
    ],
];

// La classe sera découverte
// vendor/mon-package/src/Commands/MaCommande.php
```

### Cas 3 : Utilisation dans un script d'analyse

```php
<?php

$discovery = $app->make(VendorDirectiveDiscovery::class);
$directives = $discovery->discover();

echo "Directives trouvées dans les vendors :" . PHP_EOL;

foreach ($directives as $fqcn) {
    $reflection = new ReflectionClass($fqcn);
    $instance = $reflection->newInstanceWithoutConstructor();
    echo "- " . $instance->getSignature() . " (" . $fqcn . ")" . PHP_EOL;
}
```

## Flux d'exécution

```
VendorDirectiveDiscovery::discover()
    │
    ├── $this->dependencyResolver->getFlatDependencies()
    │   └── Retourne la liste des packages installés
    │
    └── foreach($packages)
        │
        └── scanPackage($package)
            │
            ├── getPackagePath()
            │   └── /vendor/{package}
            │
            ├── scanAutoloadPaths()
            │   ├── readComposerJson()
            │   ├── Extrait les chemins PSR-4
            │   └── Scan /{path}/Directives/
            │
            └── scanCustomSources()
                ├── Vérifie config/directive.php
                ├── extractCustomSources()
                └── Scan chaque source personnalisée
```

## Structure de recherche

### 1. Chemins PSR-4

La classe recherche automatiquement dans les sous-dossiers `Directives` de chaque chemin PSR-4.

```php
// Exemple : Package "laravel/framework"
{
    "autoload": {
        "psr-4": {
            "Illuminate\\": "src/"
        }
    }
}

// Scan : vendor/laravel/framework/src/Directives/
```

### 2. Configuration personnalisée

Un package peut définir des sources supplémentaires via `config/directive.php` :

```php
// vendor/mon-package/config/directive.php
<?php

return [
    'custom_sources' => [
        'src/Commands',           // Relatif au package
        'src/Console/Commands',   // Autre dossier
    ],
];
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Package introuvable | Ignoré silencieusement | - |
| composer.json manquant | Ignoré silencieusement | - |
| JSON invalide | Ignoré silencieusement | - |
| Fichier de config inexistant | Ignoré silencieusement | - |
| Erreur de lecture fichier | Ignoré silencieusement | - |
| Erreur d'extraction des sources | Ignoré silencieusement | - |

⚠️ **Important** : Cette classe utilise `require` pour charger les fichiers de configuration. Assurez-vous que les packages tiers sont dignes de confiance.

## Intégration

La classe `VendorDirectiveDiscovery` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `ComposerReaderInterface` | Lecture du composer.json du projet |
| `DependencyResolverInterface` | Résolution des dépendances |
| `FileSystemInterface` | Opérations sur le système de fichiers |
| `DirectiveScannerInterface` | Scan des classes PHP |
| `DirectiveDiscoveryService` | Orchestration de la découverte |

### Ordre dans le processus de découverte

```
1. BuiltInDirectiveDiscovery      (prioritaire)
2. WorkspaceDirectiveDiscovery    (projet)
3. VendorDirectiveDiscovery       (packages)  ← Vous êtes ici
4. CustomSources                  (personnalisées)
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n × m) | n = packages, m = fichiers par package |
| Temps typique | 200-500ms | Pour 10-20 packages avec scan modéré |
| Mémoire | 2-5 MB | Dépend du nombre de packages |
| Cache | Non | Recommandé de mettre en cache les résultats |

### Optimisations possibles

```php
// Ajout d'un cache pour les résultats
class VendorDirectiveDiscovery
{
    private ?array $cache = null;
    
    public function discover(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        
        $this->cache = $this->doDiscover();
        return $this->cache;
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |
| Windows | ✅ Complet | Utilise `DIRECTORY_SEPARATOR` |
| Unix/Linux | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Discovers\VendorDirectiveDiscovery;
use AndyDefer\Directive\Services\ComposerReaderService;
use AndyDefer\Directive\Services\DependencyResolverService;
use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;

// Construire les dépendances
$fileSystem = new FileSystemService();
$config = $app->make(DirectiveConfigInterface::class);
$composerReader = new ComposerReaderService($config, $fileSystem);
$dependencyResolver = new DependencyResolverService($composerReader, $fileSystem);
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Créer le discovery
$discovery = new VendorDirectiveDiscovery(
    $composerReader,
    $dependencyResolver,
    $fileSystem,
    $scanner
);

// Découvrir les directives des vendors
$vendorDirectives = $discovery->discover();

// Analyser les résultats
echo "Directives trouvées : " . count($vendorDirectives) . PHP_EOL;

foreach ($vendorDirectives as $fqcn) {
    echo "- " . $fqcn . PHP_EOL;
}

// Pour voir d'où viennent les directives
// Utiliser le résolveur de dépendances
$dependencies = $dependencyResolver->getFlatDependencies();

foreach ($dependencies as $package) {
    echo "Package: " . $package . PHP_EOL;
    // Les directives de ce package sont incluses dans le résultat
}
```

## Notes de sécurité

⚠️ **Attention** : Cette classe utilise `require` pour charger les fichiers `config/directive.php` des packages vendors. Cela signifie que tout code dans ces fichiers sera exécuté. Bien que cela soit nécessaire pour la flexibilité, cela peut présenter un risque de sécurité si un package malveillant est installé.

### Bonnes pratiques

1. **Vérifier les packages** : N'installez que des packages de sources fiables
2. **Audit de sécurité** : Utilisez `composer audit` pour vérifier les vulnérabilités
3. **Environnement de développement** : Testez les packages dans un environnement isolé
4. **Contrôle des versions** : Utilisez des versions stables et vérifiées

### Alternatives sécurisées

```php
// Si vous souhaitez limiter les risques, vous pouvez désactiver
// la découverte des vendors ou limiter aux packages autorisés
class VendorDirectiveDiscovery
{
    private const ALLOWED_PACKAGES = [
        'andydefer/laravel-directive',
        'trusted-vendor/trusted-package',
    ];
    
    private function shouldScanPackage(string $package): bool
    {
        return in_array($package, self::ALLOWED_PACKAGES, true);
    }
}
```
<!-- ==== ./docs/api-reference/WHY_DIRECTIVE.md ==== -->

# Pourquoi Laravel Directive ?

## Le manifeste d'une alternative à Artisan

---

## Introduction : Le constat

Laravel Artisan est un outil remarquable. Il a posé les bases de ce qu'une CLI frameworkée devrait être. Mais après des années à l'utiliser, à l'étendre, et à le subir, des fissures apparaissent.

Ce manifeste n'est pas une attaque contre Artisan. C'est une analyse honnête de ses limites et la présentation d'une alternative qui les adresse.

---

## Les 7 problèmes fondamentaux d'Artisan

### 1. L'héritage unique imposé

**Le problème :** Pour créer une commande Artisan, vous DEVEZ étendre `Illuminate\Console\Command`.

```php
// Artisan - Pas le choix
class MyCommand extends Command { }

// Votre classe ne peut rien étendre d'autre
// Impossible d'utiliser un Value Object ou une autre base
```

**Pourquoi c'est un problème :**
- Votre logique métier est enfermée dans une hiérarchie imposée
- Impossible de réutiliser une classe existante comme commande
- Le pattern Template Method vous force à implémenter `handle()` mais vous laisse peu de contrôle sur le reste

**La solution Directive :**
```php
// Directive - Vous choisissez
class MyDirective extends AbstractDirective { }
// ou
final class MyDirective extends AbstractDirective { } // final, si vous voulez
// Votre logique reste VOTRE logique
```

---

### 2. Le couplage logique métier / présentation

**Le problème :** Dans Artisan, votre `handle()` contient à la fois la logique métier ET l'affichage.

```php
// Artisan - Tout est mélangé
public function handle()
{
    $users = User::all();  // Logique métier
    
    // Mélangé avec l'affichage
    $this->table(['ID', 'Name'], $users->toArray());
    $this->info('Done!');
    
    // Pas de séparation, pas de test unitaire pur
}
```

**Pourquoi c'est un problème :**
- Impossible de tester la logique métier sans tester l'affichage
- Changer le format de sortie (JSON, XML, etc.) demande de modifier la commande
- Le Single Responsibility Principle est violé

**La solution Directive :**
```php
// Directive - Logique et présentation séparées
public function execute(): ExitCode
{
    // Logique métier pure
    $users = $this->userRepository->getActiveUsers();
    
    // Présentation déléguée
    $this->renderUsers($users);
    
    return ExitCode::SUCCESS;
}

private function renderUsers(array $users): void
{
    // L'affichage peut être mocké, remplacé, ou testé séparément
    $this->table($this->getHeaders(), $this->formatRows($users));
}
```

---

### 3. La testabilité impossible

**Le problème :** Les commandes Artisan sont extrêmement difficiles à tester proprement.

```php
// Artisan - Comment tester ça proprement ?
public function handle()
{
    $name = $this->ask('What is your name?');  // ← impossible à mock
    
    if ($this->confirm('Continue?')) {          // ← impossible à mock
        // ...
    }
}
```

**Pourquoi c'est un problème :**
- `ask()` et `confirm()` ne peuvent pas être mockés facilement
- Les tests d'acceptance sont lourds (`artisan command --flag`)
- Impossible de faire des tests unitaires ; forcé de faire des tests d'intégration

**La solution Directive :**
```php
// Directive - Tout est injectable et mockable
class MyDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,  // ← injecté, donc mockable
        private readonly UserRepository $users,    // ← vos dépendances
    ) {
        parent::__construct($interaction);
    }
    
    public function execute(): ExitCode
    {
        $name = $this->ask('Name?');  // ← interaction service, donc mockable
        // ...
    }
}
```

En test :
```php
// Test unitaire pur - l'interaction est mockée
$interaction->expects($this->once())->method('ask')->willReturn('John');
```

---

### 4. L'extensibilité des packages inexistante

**Le problème :** Un package Laravel ne peut pas facilement enregistrer ses propres commandes.

**Pourquoi c'est un problème :**
- Obligation d'utiliser le service provider et `$this->commands([...])`
- Les commandes doivent être explicitement listées
- Pas de découverte automatique
- Pour un package qui livre 10 commandes, c'est 10 lignes à maintenir

**La solution Directive :**
```php
// Découverte automatique - rien à configurer
// Il suffit de placer vos directives dans src/Directives/
```

Le package scanne automatiquement :
- `app/Directives/*.php` (l'application)
- `vendor/*/src/Directives/*.php` (les packages)

**Aucune configuration requise.**

---

### 5. L'absence de typage fort

**Le problème :** Artisan passe les arguments et options comme un tableau brut.

```php
// Artisan - Tableau brut non typé
protected $signature = 'user:create {name} {--role=admin}';

public function handle()
{
    $name = $this->argument('name');     // mixed, pas typé
    $role = $this->option('role');       // mixed, pas typé
    
    // Si $name n'existe pas, c'est null
    // Si $role n'existe pas, c'est null
    // Tout est à vérifier manuellement
}
```

**La solution Directive :**
```php
// Directive - Accès typé
public function execute(): ExitCode
{
    // string|null - vous savez ce que vous manipulez
    $name = $this->argument('name');
    
    // bool|string|null - les flags sont automatiquement des booléens
    $force = $this->option('force');  // true ou false
    
    // hasArgument() / hasOption() pour vérifier l'existence
    if ($this->hasArgument('count')) {
        $count = (int) $this->argument('count');
    }
}
```

---

### 6. Le parsing des signatures rigide

**Le problème :** La signature d'Artisan permet des caractères ambigus.

```php
// Artisan - Syntaxe permissive
protected $signature = 'user:create {name?} {--role=admin}';
```

**Pourquoi c'est un problème :**
- Les `:` et `_` peuvent causer des conflits selon les shells
- Pas de validation stricte du format
- Des erreurs silencieuses

**La solution Directive :**
```php
// Directive - Format strict validé
// Seuls les tirets '-' sont autorisés, pas d'underscore, pas de deux-points
public function getSignature(): string
{
    return 'user-create {name} {email} {--role=admin}';
}

// Ordre imposé et validé :
// 1. Arguments requis
// 2. Arguments avec valeur par défaut
// 3. Arguments optionnels
// 4. Options
```

Et surtout, **validation automatique** :
- Signature invalide → exit code `INVALID_ARGUMENT`
- Message d'erreur clair avec la raison

---

### 7. L'absence de bootstrap Laravel à la demande

**Le problème :** Artisan charge TOUT Laravel, TOUT LE TEMPS.

```php
// Même pour une commande qui fait juste echo "Hello World"
// Laravel charge la base de données, le cache, les providers, etc.
```

**Pourquoi c'est un problème :**
- Des commandes simples sont pénalisées par un bootstrap lourd
- Performance dégradée
- Pas de contrôle sur ce qui est chargé

**La solution Directive :**
```php
// Directive - Bootstrap uniquement si demandé
public function shouldBootLaravel(): bool
{
    return false;  // Par défaut, pas de bootstrap
}

// Pour une directive qui a besoin d'Eloquent :
public function shouldBootLaravel(): bool
{
    return true;   // Bootstrap uniquement pour celle-ci
}
```

**Résultat :**
- Les directives simples s'exécutent en millisecondes
- Le bootstrap se fait une seule fois par exécution
- Contrôle total sur les performances

---

## Les avantages synthétisés

| Problème Artisan | Solution Directive |
|-----------------|-------------------|
| Héritage unique imposé | `AbstractDirective` mais vous pouvez faire `final` |
| Logique métier + présentation mélangées | Séparation claire via `execute()` |
| Testabilité difficile (ask/confirm) | Injection de `DirectiveInteractionService` |
| Extensibilité manuelle des packages | Découverte automatique (`vendor/*/src/Directives/`) |
| Pas de typage (`array` brut) | `argument(): ?string`, `option(): bool\|string\|null` |
| Parsing permissif | Format strict + validation automatique |
| Bootstrap Laravel systématique | Bootstrap à la demande (`shouldBootLaravel()`) |

---

## Les inconvénients assumés

Aucune solution n'est parfaite. Directive a aussi ses compromis :

### 1. Une dépendance supplémentaire
- Artisan est natif à Laravel
- Directive ajoute `andydefer/php-records` comme dépendance

### 2. Une courbe d'apprentissage
- Artisan est la solution "standard"
- Directive demande d'apprendre une nouvelle API

### 3. Moins de "magie"
- Artisan a des années d'optimisation
- Directive est plus récente, avec moins de recul

### 4. Pas de compatibilité directe
- Vous ne pouvez PAS exécuter une directive via `php artisan`
- Vous devez utiliser `./vendor/bin/directive`
- C'est un choix délibéré : une philosophie différente

---

## Pour qui est ce package ?

### Vous devriez utiliser Directive si :

- ✅ Vous voulez une **séparation claire** entre logique métier et présentation
- ✅ Vous avez des **commandes simples** qui ne nécessitent pas Laravel
- ✅ Vous **testez intensivement** vos commandes
- ✅ Vous développez des **packages** avec des commandes
- ✅ Vous voulez une **API typée** pour les arguments et options
- ✅ Vous voulez **contrôler** quand Laravel est bootstrappé

### Vous devriez rester sur Artisan si :

- ❌ Vous êtes satisfait d'Artisan
- ❌ Vous ne voulez pas de dépendance supplémentaire
- ❌ Toutes vos commandes ont besoin de Laravel de toute façon
- ❌ Vous préférez la solution "officielle"

---

## Conclusion : Une question de philosophie

Artisan et Directive ne sont pas en compétition. Ils répondent à des besoins différents.

**Artisan** excelle quand :
- Votre commande a BESOIN de Laravel
- Vous êtes dans un écosystème 100% Laravel
- La "magie" vous convient

**Directive** excelle quand :
- Vous voulez séparer logique et présentation
- Vous testez intensivement
- Vous voulez contrôler le bootstrap
- Vous développez des packages réutilisables

**Laravel Directive n'est pas un remplacement d'Artisan. C'est une alternative pour ceux qui veulent une architecture différente.**

---

## Un dernier mot

Ce package est né de la frustration. La frustration de ne pas pouvoir tester proprement. La frustration de voir des commandes simples payer le prix d'un bootstrap lourd. La frustration de devoir lister manuellement chaque commande dans un package.

Mais cette frustration a donné naissance à une solution. Pas parfaite, mais honnête.

**Laravel Directive : pour ceux qui veulent écrire des commandes CLI comme ils écrivent le reste de leur code : propre, testable, et découplé.**

---

*Andy Defer*

---

## Annexe : Comparaison côte à côte

### Artisan
```php
use Illuminate\Console\Command;

class UserCreateCommand extends Command
{
    protected $signature = 'user:create {name} {email} {--role=admin}';
    protected $description = 'Create a new user';
    
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $role = $this->option('role');
        
        // Logique + affichage mélangés
        $this->info("User {$name} created with role {$role}");
        
        return 0;
    }
}
```

### Directive
```php
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-create {name} {email} {--role=admin}';
    }
    
    public function getDescription(): string
    {
        return 'Create a new user';
    }
    
    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $role = $this->option('role');
        
        // Logique pure ici
        $this->userService->create($name, $email, $role);
        
        // Affichage délégué
        $this->info("User {$name} created with role {$role}");
        
        return ExitCode::SUCCESS;
    }
}
```

La différence ? La **séparation**. Et c'est toute la philosophie.
<!-- ==== ./docs/api-reference/scanners/directive-class-scanner.md ==== -->

# DirectiveClassScanner - Référence Technique

## Description

Scanner qui analyse des fichiers PHP pour découvrir les classes de directives. Il utilise l'AST (Abstract Syntax Tree) pour détecter de manière fiable les classes qui étendent `AbstractDirective`, même avec une syntaxe complexe ou des imports aliasés.

## Hiérarchie / Implémentations

```
DirectiveScannerInterface
    └── DirectiveClassScanner (final)
```

## Rôle principal

Parcourir récursivement les répertoires, analyser les fichiers PHP, et identifier les classes qui sont des directives valides (non abstraites, étendant `AbstractDirective`). Il retourne la liste des FQCN (Fully Qualified Class Names) de toutes les directives trouvées.

## Installation

### Dépendances

```bash
composer require nikic/php-parser
```

### Configuration dans le conteneur

```php
// Dans le service provider
$this->app->singleton(DirectiveScannerInterface::class, function ($app) {
    $fileSystem = $app->make(FileSystemInterface::class);
    $parser = $app->make(Parser::class);
    
    return new DirectiveClassScanner($fileSystem, $parser);
});
```

## API / Méthodes publiques

### `scan(string $directory, int $maxDepth = 3): array`

Scanne un répertoire récursivement pour trouver les classes de directives.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Le chemin du répertoire à scanner |
| `$maxDepth` | `int` | Profondeur maximale de récursion (défaut: 3) |

**Retourne :** `array<int, string>` - Liste des FQCN des directives trouvées

**Exceptions :** Aucune (les erreurs de lecture ou de parsing sont ignorées)

**Exemple :**
```php
<?php

use AndyDefer\Directive\Scanners\DirectiveClassScanner;

$scanner = new DirectiveClassScanner($fileSystem, $parser);
$directives = $scanner->scan('/var/www/project/app/Directives', 3);

// Retourne : ['App\Directives\UserDirective', 'App\Directives\AdminDirective']
```

## Cas d'utilisation

### Cas 1 : Scan des directives de l'application

```php
<?php

use AndyDefer\Directive\Scanners\DirectiveClassScanner;

// Scanner les directives du projet
$scanner = new DirectiveClassScanner($fileSystem, $parser);
$directives = $scanner->scan('/var/www/project/app/Directives', 3);

foreach ($directives as $fqcn) {
    echo "Directive trouvée : " . $fqcn . PHP_EOL;
}
```

### Cas 2 : Scan des directives d'un package vendor

```php
<?php

$vendorPath = '/var/www/project/vendor/mon-package/src';
$directives = $scanner->scan($vendorPath . '/Directives', 2);

// Retourne les directives du package
```

### Cas 3 : Scan avec profondeur limitée

```php
<?php

// Scan uniquement sur 2 niveaux de profondeur
$directives = $scanner->scan('/var/www/project/app', 2);

// Structure : app/
//   Directives/          <- Niveau 1 (scanné)
//     UserDirective.php  <- Niveau 2 (scanné)
//     Admin/
//       AdminDirective.php <- Niveau 3 (IGNORÉ)
```

## Flux d'exécution

```
DirectiveClassScanner::scan($directory, $maxDepth)
    │
    ├── Vérifie que le répertoire existe
    │
    └── scanDirectory()
        │
        ├── Pour chaque fichier *.php
        │   ├── analyzeFile()
        │   │   ├── Parser::parse() → AST
        │   │   ├── Traverse l'AST avec le visitor
        │   │   │   ├── Capture du namespace
        │   │   │   ├── Capture des use (aliases)
        │   │   │   ├── Analyse des classes
        │   │   │   │   ├── Vérifie l'héritage AbstractDirective
        │   │   │   │   ├── Vérifie non-abstraite
        │   │   │   │   └── Construction du FQCN
        │   │   │   └── Retourne la liste des classes trouvées
        │   │   └── Retourne les FQCN
        │   └── Merge dans le tableau résultat
        │
        └── Pour chaque sous-répertoire (si profondeur < maxDepth)
            └── Appel récursif de scanDirectory()
```

## Détection des directives

### Critères de validation

Une classe est considérée comme une directive si :

1. ✅ **Non abstraite** : `!$node->isAbstract()`
2. ✅ **Étend AbstractDirective** : `$node->extends === AbstractDirective::class` ou via alias
3. ✅ **A un namespace** : `$this->currentNamespace !== null`

### Gestion des alias (use)

```php
use AndyDefer\Directive\AbstractDirective;

class MyDirective extends AbstractDirective // ✅ Détecté
{
    // ...
}
```

```php
use AndyDefer\Directive\AbstractDirective as BaseDirective;

class MyDirective extends BaseDirective // ✅ Détecté via alias
{
    // ...
}
```

### Syntaxe supportée

| Syntaxe | Support |
|---------|---------|
| `class MyDirective extends AbstractDirective` | ✅ |
| `class MyDirective extends \AndyDefer\Directive\AbstractDirective` | ✅ |
| `class MyDirective extends AbstractDirective { ... }` | ✅ |
| `final class MyDirective extends AbstractDirective` | ✅ |
| `readonly class MyDirective extends AbstractDirective` | ✅ (PHP 8.2+) |
| `class MyDirective extends AbstractDirective implements Interface` | ✅ |
| `abstract class AbstractDirective extends ...` | ❌ (ignoré) |
| `class MyDirective extends OtherClass` | ❌ (ignoré) |
| `class MyDirective { ... }` | ❌ (ignoré) |

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Répertoire inexistant | Retourne un tableau vide | - |
| Fichier PHP invalide | Ignoré silencieusement | - |
| Erreur de parsing AST | Ignoré silencieusement | - |
| Erreur de lecture fichier | Ignoré silencieusement | - |
| Classe abstraite | Ignorée (non ajoutée) | - |
| Classe n'étendant pas AbstractDirective | Ignorée | - |
| Fichier sans namespace | Ignoré | - |

### Pourquoi les erreurs sont ignorées ?

Le scanner ignore silencieusement les erreurs pour :
1. **Robustesse** : Un fichier mal formé ne doit pas bloquer le scan complet
2. **Performance** : Évite les arrêts coûteux
3. **Pratique** : Les fichiers PHP invalides sont rares dans un projet fonctionnel

## Intégration

La classe `DirectiveClassScanner` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `FileSystemInterface` | Opérations sur le système de fichiers |
| `Parser` (nikic/php-parser) | Analyse AST des fichiers PHP |
| `DirectiveDiscoveryService` | Utilisée par le service de découverte |
| `WorkspaceDirectiveDiscovery` | Scan du workspace |
| `VendorDirectiveDiscovery` | Scan des packages vendors |

### Utilisation dans le service de découverte

```php
class DirectiveDiscoveryService
{
    public function discover(): DirectiveMetadataCollection
    {
        // Scan des sources
        $fqcns = $this->scanner->scan('/var/www/project/app/Directives');
        
        foreach ($fqcns as $fqcn) {
            $this->addDirective($fqcn);
        }
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Complexité | O(n × m) | n = fichiers PHP, m = profondeur |
| Temps typique | 50-200ms | Pour 50-100 fichiers |
| Mémoire | 1-5 MB | Dépend de la taille des fichiers |
| Cache | ❌ Non | Recommandé d'ajouter un cache |

### Facteurs de performance

1. **Nombre de fichiers** : Plus il y a de fichiers, plus le scan est lent
2. **Profondeur de récursion** : Plus la profondeur est grande, plus le scan est lent
3. **Taille des fichiers** : Les gros fichiers prennent plus de temps à parser
4. **Parser** : L'AST parsing est plus lent que les regex mais plus fiable

### Optimisations recommandées

```php
class DirectiveClassScanner
{
    private ?array $cache = null;
    
    public function scan(string $directory, int $maxDepth = 3): array
    {
        $cacheKey = md5($directory . $maxDepth);
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $this->cache[$cacheKey] = $this->doScan($directory, $maxDepth);
        return $this->cache[$cacheKey];
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | Support `readonly` classes |
| PHP 8.3+ | ✅ Complet | - |
| nikic/php-parser 4.x | ✅ Complet | - |
| nikic/php-parser 5.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Scanners\DirectiveClassScanner;
use AndyDefer\PhpServices\Services\FileSystemService;
use PhpParser\ParserFactory;

// Créer les dépendances
$fileSystem = new FileSystemService();
$parser = (new ParserFactory())->createForNewestSupportedVersion();

// Créer le scanner
$scanner = new DirectiveClassScanner($fileSystem, $parser);

// Scanner un répertoire
$directives = $scanner->scan('/var/www/project/app/Directives', 3);

// Afficher les résultats
echo "Directives trouvées : " . count($directives) . PHP_EOL;

foreach ($directives as $fqcn) {
    echo "- " . $fqcn . PHP_EOL;
    
    // Vérification supplémentaire
    $reflection = new ReflectionClass($fqcn);
    if ($reflection->isSubclassOf(AbstractDirective::class)) {
        $instance = $reflection->newInstanceWithoutConstructor();
        echo "  Signature: " . $instance->getSignature() . PHP_EOL;
    }
}

// Exemple avec profondeur personnalisée
$shallowScan = $scanner->scan('/var/www/project/app/Directives', 1);
// Ne scanne que le dossier immédiat, pas les sous-dossiers

// Exemple avec répertoire inexistant
$empty = $scanner->scan('/var/www/project/inexistant', 3);
// Retourne [] sans erreur
```

## Notes techniques

### Pourquoi l'AST plutôt que les regex ?

| Approche | Avantages | Inconvénients |
|----------|-----------|---------------|
| **Regex** | Rapide, simple | Fragile, échoue sur syntaxe complexe |
| **AST** | Fiable, robuste | Plus lent, dépendance externe |

### Limitations connues

1. **Fichiers inclus** : Les fichiers inclus via `include` ou `require` ne sont pas analysés
2. **Classes anonymes** : Les classes anonymes sont ignorées
3. **Trait** : Les traits ne sont pas détectés comme des directives
4. **Interfaces** : Les interfaces ne sont pas détectées

### Bonnes pratiques

1. **Limiter la profondeur** : Utilisez une profondeur raisonnable (3 par défaut)
2. **Utiliser le cache** : Mettez en cache les résultats pour les performances
3. **Fichiers séparés** : Une directive par fichier pour un scan optimal
4. **Namespace explicite** : Toujours déclarer un namespace

```php
// ✅ Bonne pratique
namespace App\Directives;

class UserDirective extends AbstractDirective
{
    // ...
}

// ❌ Mauvaise pratique (pas de namespace, sera ignoré)
class UserDirective extends AbstractDirective
{
    // ...
}
---
<!-- ==== ./docs/api-reference/cli-runner.md ==== -->

# CliBootstrap - Référence Technique

## Description

Classe responsable du démarrage complet de l'application Laravel pour l'exécution des commandes Directive en CLI. Elle gère le chargement de l'environnement, l'autoloader Composer, la création de l'application, l'enregistrement des providers et le bootstrapping.

## Hiérarchie / Implémentations

```
final readonly class CliBootstrap
```

- **Final** : Ne peut pas être étendue
- **Readonly** : Toutes les propriétés sont en lecture seule

## Rôle principal

Servir de point d'entrée unique pour le lancement de l'application Laravel en mode CLI. Elle encapsule toute la logique de démarrage nécessaire pour que les directives puissent s'exécuter dans un contexte Laravel complet, même sans serveur web.

## Installation

Cette classe est utilisée automatiquement par le point d'entrée CLI du package.

```bash
# Le package est installé via Composer
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `run(array $arguments): int`

Exécute le runner CLI avec les arguments fournis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$arguments` | `array<int, string>` | Les arguments de la ligne de commande (ex: `['directive', 'list']`) |

**Retourne :** `int` - Le code de sortie (0 = succès, >0 = erreur)

**Exceptions :** Aucune exception directe, mais la méthode peut propager les exceptions du `CliRunner`

**Exemple :**
```php
<?php

$bootstrap = CliBootstrap::create();
$exitCode = $bootstrap->run(['directive', 'list']);
```

---

### `create(): self`

Crée une instance de `CliBootstrap` avec une application Laravel entièrement bootstrappée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `self` - Une nouvelle instance configurée

**Exceptions :** `BootstrapException` - Si une étape du bootstrap échoue (autoloader manquant, fichier bootstrap introuvable, etc.)

**Exemple :**
```php
<?php

$bootstrap = CliBootstrap::create();
// L'application est maintenant prête à exécuter des directives
```

## Cas d'utilisation

### Cas 1 : Lancement d'une directive simple

```php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Créer le bootstrap avec l'application chargée
$bootstrap = CliBootstrap::create();

// Exécuter la directive "list"
$exitCode = $bootstrap->run(['directive', 'list']);

// Vérifier le résultat
if ($exitCode === 0) {
    echo "Commande exécutée avec succès";
}
```

### Cas 2 : Intégration dans un script personnalisé

```php
#!/usr/bin/env php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

try {
    $bootstrap = CliBootstrap::create();
    $exitCode = $bootstrap->run($argv);
    exit($exitCode);
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
    exit(1);
}
```

### Cas 3 : Exécution avec arguments complexes

```php
<?php

$bootstrap = CliBootstrap::create();

// Arguments avec option et valeur
$args = [
    'directive',
    'db:backup',
    '--force',
    '--compression=gzip',
    '--format=sql'
];

$exitCode = $bootstrap->run($args);
```

## Flux d'exécution

```
CliBootstrap::create()
    │
    ├── loadEnvironment()
    │   └── .env → putenv()
    │
    ├── loadAutoloader()
    │   ├── vendor/autoload.php → require_once
    │   └── vendor/autoload.php (package) → require_once
    │
    ├── createApplication()
    │   ├── bootstrap/app.php → require
    │   └── Vérifie instanceof Application
    │
    ├── registerProviders()
    │   ├── resolveProvidersFromStorage()
    │   │   └── storage/framework/providers.php
    │   ├── resolveProvidersFromConfig()
    │   │   └── config/app.php → providers[]
    │   └── filterValidProviders()
    │
    ├── bootApplication()
    │   └── Kernel::bootstrap()
    │
    └── new self($app)

CliBootstrap::run($argv)
    │
    ├── $this->app->make(CliRunner::class)
    └── $runner->run($arguments)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Autoloader non trouvé | `BootstrapException` | `Autoloader not found at [PATH]. Run 'composer install' first.` |
| Fichier bootstrap Laravel manquant | `BootstrapException` | `Laravel bootstrap file not found at [PATH].` |
| Bootstrap ne retourne pas une instance de `Application` | `BootstrapException` | `Bootstrap file must return an instance of Illuminate\Contracts\Foundation\Application` |
| Répertoire courant non déterminable | `RuntimeException` | `Unable to determine current working directory` |

## Intégration

La classe `CliBootstrap` s'intègre avec les composants suivants :

- **`Paths`** : Utilisée pour résoudre tous les chemins de fichiers
- **`CliRunner`** : Construite via le conteneur et exécutée avec les arguments
- **Application Laravel** : Créée et bootstrappée via le conteneur
- **Service Providers** : Chargés depuis le stockage et la configuration

## Performance

- **Temps de chargement** : ~200-500ms (dépend du nombre de providers)
- **Mémoire** : ~4-8 MB (application Laravel chargée)
- **Cache** : Utilise le fichier `storage/framework/providers.php` pour accélérer le chargement des providers
- **Optimisation** : Le bootstrap est effectué une seule fois par instance

### Points d'attention

- Le chargement de l'environnement `.env` utilise `putenv()` qui peut être désactivé sur certains environnements
- Les providers sont chargés depuis deux sources (storage + config) ce qui peut causer des doublons si mal configuré

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| Laravel 9.x | ✅ Testé |
| Laravel 10.x | ✅ Testé |
| Laravel 11.x | ✅ Testé |

## Exemple complet

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\CliBootstrap;
use AndyDefer\Directive\Exceptions\BootstrapException;

try {
    // Créer le bootstrap
    $bootstrap = CliBootstrap::create();
    
    // Exécuter avec les arguments reçus
    $exitCode = $bootstrap->run($argv);
    
    // Terminer avec le code approprié
    exit($exitCode);
    
} catch (BootstrapException $e) {
    // Erreur de bootstrap
    fwrite(STDERR, "❌ Bootstrap error: " . $e->getMessage() . PHP_EOL);
    exit(1);
    
} catch (Throwable $e) {
    // Erreur inattendue
    fwrite(STDERR, "💥 Unexpected error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
```

## Notes de sécurité

⚠️ **Important** : Cette classe utilise `putenv()` pour charger les variables d'environnement. Assurez-vous que cette fonction n'est pas désactivée dans votre environnement (souvent le cas dans les environnements de production avec `disable_functions`).

⚠️ **Provider Storage** : Le fichier `storage/framework/providers.php` doit être accessible en lecture. Si le fichier n'existe pas, les providers seront chargés depuis la configuration `config/app.php`.
---
<!-- ==== ./docs/api-reference/directive-kernel.md ==== -->

# DirectiveKernel - Référence Technique

## Description

Le noyau central qui orchestre l'exécution des directives. Il est responsable de la découverte des directives, de la résolution de la directive appropriée pour une commande donnée, et de son exécution.

## Hiérarchie / Implémentations

```
DirectiveKernel (final)
```

## Rôle principal

Agir comme point d'entrée principal du système de directives. Le kernel :
1. Reçoit les arguments de la ligne de commande
2. Découvre toutes les directives disponibles
3. Identifie la directive correspondant à la commande
4. Instancie et exécute la directive
5. Retourne le code de sortie approprié

## Installation

### Utilisation automatique

Le kernel est automatiquement instancié par le conteneur via le service provider :

```php
// Dans DirectiveServiceProvider
$this->app->singleton(DirectiveKernel::class, function ($app) {
    return new DirectiveKernel(
        $app,
        $app->make(DirectiveDiscoveryService::class)
    );
});
```

### Utilisation manuelle

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

$kernel = new DirectiveKernel($app, $discovery);
$exitCode = $kernel->run(['directive', 'list']);
```

## API / Méthodes publiques

### `run(array $argv): ExitCode`

Exécute le kernel avec les arguments de la ligne de commande.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$argv` | `array<int, string>` | Les arguments de la ligne de commande |

**Retourne :** `ExitCode` - Le code de sortie de l'exécution

**Exceptions :** Aucune (les erreurs sont gérées par les directives)

**Exemple :**
```php
<?php

// Exécution d'une directive
$exitCode = $kernel->run(['directive', 'list']);
// Ou
$exitCode = $kernel->run(['directive', 'user:create', 'John', '--admin']);
```

## Cas d'utilisation

### Cas 1 : Exécution d'une directive simple

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

// Arguments: directive list
$argv = ['directive', 'list'];
$exitCode = $kernel->run($argv);

if ($exitCode->isSuccess()) {
    echo "Commande exécutée avec succès\n";
} else {
    echo "Erreur: " . $exitCode->getLabel() . "\n";
}
```

### Cas 2 : Exécution avec arguments et flags

```php
<?php

// Arguments: directive user:create John --admin --role=editor
$argv = ['directive', 'user:create', 'John', '--admin', '--role=editor'];
$exitCode = $kernel->run($argv);

// La directive user:create sera exécutée avec ces arguments
```

### Cas 3 : Exécution avec alias

```php
<?php

// Utilisation d'un alias: directive ls (alias de list)
$argv = ['directive', 'ls'];
$exitCode = $kernel->run($argv);

// La directive list sera exécutée
```

### Cas 4 : Intégration dans un script CLI

```php
#!/usr/bin/env php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Le bootstrap crée automatiquement le kernel
$bootstrap = CliBootstrap::create();
$exitCode = $bootstrap->run($argv);

exit($exitCode);
```

## Flux d'exécution

```
DirectiveKernel::run($argv)
    │
    ├── isMissingCommand($argv)
    │   ├── count($argv) < 2 → true
    │   └── executeHelpDirective()
    │       └── executeDirective('help', 'help')
    │
    └── parseArguments($argv)
        ├── $query = implode(' ', array_slice($argv, 1))
        ├── $parts = explode(' ', $query)
        └── $commandName = $parts[0]
    │
    └── executeDirective($commandName, $query)
        │
        ├── $directives = $this->discovery->discover()
        │
        ├── findDirective($directives, $commandName)
        │   ├── matchesCommandName()
        │   │   └── Comparaison avec la première partie de la signature
        │   └── matchesAlias()
        │       └── Comparaison avec les alias
        │
        ├── if ($directive === null) → ExitCode::NOT_FOUND
        │
        └── instantiateAndRun($directive, $query)
            ├── $this->app->make($directive->class, ['query' => $query])
            └── $instance->run()
```

## Exemples de résolution

### Résolution par nom de commande

```php
// Directive avec signature: 'user:create {name}'
// Commande: directive user:create
// Résultat: Directive trouvée par nom de commande 'user:create'
```

### Résolution par alias

```php
// Directive avec alias: '-l' pour 'list'
// Commande: directive -l
// Résultat: Directive trouvée par alias '-l' → 'list'
```

### Résolution par nom court

```php
// Directive avec signature: 'list'
// Commande: directive list
// Résultat: Directive trouvée par nom de commande 'list'
```

## Gestion des erreurs

| Situation | Comportement | Code de sortie |
|-----------|--------------|----------------|
| Aucune commande fournie | Exécute `help` | `ExitCode::SUCCESS` |
| Directive non trouvée | Retourne `NOT_FOUND` | `ExitCode::NOT_FOUND` |
| Erreur d'exécution | Gérée par la directive | Variable |

### Scénarios d'erreur

```php
// Pas de commande
$kernel->run(['directive']);
// → Exécute help

// Commande inexistante
$kernel->run(['directive', 'nonexistent']);
// → Retourne ExitCode::NOT_FOUND

// Commande avec erreur interne
$kernel->run(['directive', 'failing:command']);
// → Retourne ExitCode::RUNTIME_ERROR (si géré par la directive)
```

## Intégration

Le `DirectiveKernel` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveDiscoveryService` | Découverte des directives |
| `DirectiveMetadataCollection` | Collection des directives découvertes |
| `DirectiveMetadataRecord` | Métadonnées des directives |
| `ExitCode` | Codes de retour |
| `Application` | Conteneur Laravel pour l'instanciation |

### Utilisation avec CliBootstrap

```php
// CliBootstrap utilise le kernel via CliRunner
class CliRunner
{
    public function run(array $argv): int
    {
        $kernel = $this->buildKernel();
        return $kernel->run($argv)->value;
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Temps de découverte | 200-800ms | Première exécution |
| Temps de résolution | < 1ms | Recherche dans la collection |
| Temps d'instanciation | 1-5ms | Création de la directive |
| Mémoire | 2-5 MB | Collection des directives |

### Optimisations

```php
class DirectiveKernel
{
    private ?DirectiveMetadataCollection $cachedDirectives = null;
    
    private function executeDirective(string $commandName, string $query): ExitCode
    {
        if ($this->cachedDirectives === null) {
            $this->cachedDirectives = $this->discovery->discover();
        }
        
        $directive = $this->findDirective($this->cachedDirectives, $commandName);
        // ...
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use Illuminate\Foundation\Application;

class KernelExample
{
    private DirectiveKernel $kernel;
    
    public function __construct(Application $app)
    {
        $discovery = $app->make(DirectiveDiscoveryService::class);
        $this->kernel = new DirectiveKernel($app, $discovery);
    }
    
    public function runCommand(string $command): int
    {
        // Construire les arguments
        $argv = ['directive', ...explode(' ', $command)];
        
        // Exécuter
        $exitCode = $this->kernel->run($argv);
        
        return $exitCode->value;
    }
}

// Utilisation
$example = new KernelExample($app);

// Exécuter une commande simple
$result = $example->runCommand('list');
echo "Résultat: " . $result . PHP_EOL;

// Exécuter avec arguments
$result = $example->runCommand('user:create John --admin');
echo "Résultat: " . $result . PHP_EOL;

// Gérer les erreurs
$result = $example->runCommand('nonexistent');
if ($result !== 0) {
    echo "Erreur: Code {$result}\n";
}

// Exécution programmatique
$commands = [
    'cache:clear',
    'config:cache',
    'view:clear',
];

foreach ($commands as $command) {
    $code = $example->runCommand($command);
    if ($code !== 0) {
        echo "Échec de: {$command} (code: {$code})\n";
        break;
    }
    echo "Succès: {$command}\n";
}
```

## Notes techniques

### Résolution des directives

Le kernel utilise deux méthodes pour trouver une directive :

1. **Par nom de commande** : La première partie de la signature
2. **Par alias** : Les alias définis dans la directive

```php
// Signature: 'user:create {name}'
// Nom de commande: 'user:create'
// Alias possibles: ['u', 'uc']

// Résolution:
// directive user:create → trouvée par nom
// directive u → trouvée par alias
// directive uc → trouvée par alias
```

### Commande par défaut

Si aucune commande n'est fournie, le kernel exécute automatiquement `help` :

```php
// Pas de commande
$kernel->run(['directive']);
// → Exécute help
```

### Instanciation des directives

Le kernel utilise le conteneur Laravel pour instancier les directives :

```php
$instance = $this->app->make($directive->class, [
    'query' => $query,
]);
```

Cela permet d'injecter automatiquement les dépendances via le conteneur.

### Points d'extension

1. **Nouvelles directives** : Ajoutées via `DirectiveDiscoveryService`
2. **Nouveaux alias** : Définis dans `getAliases()` de la directive
3. **Comportement par défaut** : Peut être modifié en surchargeant `executeHelpDirective()`

### Bonnes pratiques

1. **Toujours utiliser le conteneur** : Pour l'instanciation des directives
2. **Gérer les erreurs** : Retourner des `ExitCode` appropriés
3. **Tester les directives** : Utiliser `DirectiveTestingService`
4. **Documenter les commandes** : Utiliser `getDescription()`

```php
// ✅ Bonne pratique
$exitCode = $kernel->run(['directive', 'list']);

// ✅ Gestion du code de sortie
if ($exitCode->isFailure()) {
    // Gérer l'erreur
}

// ❌ Mauvaise pratique
$kernel->run(['directive', 'list']); // Ignorer le code de sortie
```
---
<!-- ==== ./docs/api-reference/abstract-directive.md ==== -->

# AbstractDirective - Référence Technique

## Description

Classe abstraite de base pour toutes les directives. Elle fournit les fonctionnalités communes nécessaires à l'exécution des directives : parsing des arguments, gestion des flags, méthodes de sortie, et exécution des appels internes. Les directives sont des commandes CLI autonomes qui définissent une signature, des alias et une logique d'exécution.

## Hiérarchie / Implémentations

```
DirectiveInterface
    └── AbstractDirective (abstract)
        ├── HelpDirective
        ├── ListDirective
        └── VersionDirective
```

## Rôle principal

Servir de classe de base pour toutes les directives du package. Elle centralise :
1. Le parsing des signatures et des requêtes
2. L'accès aux arguments, flags et valeurs variadiques
3. Les méthodes de sortie (console)
4. La gestion des appels internes (chaînage de directives)
5. La détection des dépendances circulaires

## Installation

### Créer une nouvelle directive

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class MyDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'my:command {name} {--force}';
    }

    public function getDescription(): string
    {
        return 'Ma commande personnalisée';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['mc']);
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $force = $this->flag('force');
        
        $this->info("Bonjour {$name} !");
        
        if ($force) {
            $this->line("Mode forcé activé");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

## API / Méthodes publiques

### `getApplication(): Application`

Récupère l'instance de l'application Laravel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `Application` - L'application Laravel

**Exemple :**
```php
<?php

$app = $this->getApplication();
$config = $app->make(Config::class);
```

---

### `getConsole(): Console`

Récupère l'instance de la console pour les opérations de sortie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `Console` - L'instance de la console

**Exemple :**
```php
<?php

$console = $this->getConsole();
$console->title('Mon Titre');
```

---

### `getParsed(): ParsedSignatureRecord`

Récupère le record de la signature parsée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `ParsedSignatureRecord` - Les données parsées de la signature

**Exemple :**
```php
<?php

$parsed = $this->getParsed();
$required = $parsed->required->toArray();
```

---

### `argument(string $key): mixed`

Récupère la valeur d'un argument (requis ou par défaut).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom de l'argument |

**Retourne :** `mixed` - La valeur de l'argument, ou `null` si non trouvé

**Exemple :**
```php
<?php

$name = $this->argument('name');
$email = $this->argument('email') ?? 'default@example.com';
```

---

### `hasArgument(string $key): bool`

Vérifie si un argument existe (requis ou par défaut).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom de l'argument |

**Retourne :** `bool` - `true` si l'argument existe, `false` sinon

**Exemple :**
```php
<?php

if ($this->hasArgument('name')) {
    $name = $this->argument('name');
}
```

---

### `flag(string $key): bool`

Récupère la valeur d'un flag.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom du flag |

**Retourne :** `bool` - `true` si le flag est présent, `false` sinon

**Exemple :**
```php
<?php

if ($this->flag('force')) {
    $this->line('Mode forcé');
}
```

---

### `hasFlag(string $key): bool`

Vérifie si un flag existe dans la signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom du flag |

**Retourne :** `bool` - `true` si le flag existe, `false` sinon

**Exemple :**
```php
<?php

if ($this->hasFlag('force')) {
    $force = $this->flag('force');
}
```

---

### `isFlagActive(string $key): bool`

Vérifie si un flag est actif dans la requête courante.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Le nom du flag |

**Retourne :** `bool` - `true` si le flag est actif, `false` sinon

**Exemple :**
```php
<?php

if ($this->isFlagActive('admin')) {
    // Exécuter en mode administrateur
}
```

---

### `getVariadicArguments(): StringTypedCollection`

Récupère tous les arguments variadiques.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StringTypedCollection` - Collection des valeurs variadiques

**Exemple :**
```php
<?php

$files = $this->getVariadicArguments();
foreach ($files as $file) {
    $this->line("Fichier: {$file}");
}
```

---

### `line(string $message): void`

Affiche une ligne de texte.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
<?php

$this->line('Hello World');
```

---

### `info(string $message): void`

Affiche un message d'information (en vert).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
<?php

$this->info('Succès !');
```

---

### `error(string $message): void`

Affiche un message d'erreur (en rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
<?php

$this->error('Une erreur est survenue');
```

---

### `table(ListCollection|array $headers, ListCollection|array $rows): void`

Affiche un tableau.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `ListCollection|array` | Les en-têtes du tableau |
| `$rows` | `ListCollection|array` | Les lignes du tableau |

**Exemple :**
```php
<?php

$this->table(
    ['ID', 'Nom', 'Email'],
    [
        [1, 'John Doe', 'john@example.com'],
        [2, 'Jane Doe', 'jane@example.com'],
    ]
);
```

---

### `call(string $query): void`

Enfile un appel interne vers une autre directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | La requête à exécuter |

**Exemple :**
```php
<?php

$this->call('list');
$this->call('db:backup --force');
```

---

### `getCalls(): array`

Récupère la liste des appels internes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `array<int, DirectiveCallRecord>` - Liste des appels

**Exemple :**
```php
<?php

$calls = $this->getCalls();
foreach ($calls as $call) {
    echo $call->query . PHP_EOL;
}
```

---

### `run(): ExitCode`

Exécute la directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `ExitCode` - Le code de sortie

**Exemple :**
```php
<?php

$exitCode = $this->run();
exit($exitCode->value);
```

---

### `getAliases(): StringTypedCollection`

Récupère les alias de la directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
<?php

public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['u', 'user']);
}
```

---

### `getSignature(): string`

Récupère la signature de la directive (à implémenter).

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - La signature

**Exemple :**
```php
<?php

public function getSignature(): string
{
    return 'user:create {name} {email?} {--admin}';
}
```

## Hooks

### `beforeExecute(): void`

Hook appelé avant l'exécution principale.

**Exemple :**
```php
<?php

protected function beforeExecute(): void
{
    $this->line('Début de l\'exécution...');
}
```

### `afterExecute(ExitCode $exitCode): void`

Hook appelé après l'exécution principale.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$exitCode` | `ExitCode` | Le code de sortie de l'exécution |

**Exemple :**
```php
<?php

protected function afterExecute(ExitCode $exitCode): void
{
    $this->line('Fin de l\'exécution');
}
```

## Cas d'utilisation

### Cas 1 : Directive avec arguments et flags

```php
<?php

final class BackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'backup {file?} {--force} {--compression=gzip} {--format=sql}';
    }
    
    protected function execute(): ExitCode
    {
        $file = $this->argument('file') ?? date('Y-m-d') . '.sql';
        $compression = $this->flag('compression');
        $format = $this->flag('format');
        $force = $this->flag('force');
        
        $this->info("Sauvegarde de {$file}");
        $this->info("Format: {$format}");
        
        if ($compression) {
            $this->info("Compression: {$compression}");
        }
        
        if ($force) {
            $this->line('Mode forcé');
        }
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Directive avec appel interne

```php
<?php

final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--migrate} {--seed}';
    }
    
    protected function execute(): ExitCode
    {
        $env = $this->argument('environment');
        
        $this->info("Déploiement vers {$env}");
        
        // Exécuter les directives de maintenance
        $this->call('cache:clear');
        $this->call('config:cache');
        
        if ($this->flag('migrate')) {
            $this->call('db:migrate --force');
        }
        
        if ($this->flag('seed')) {
            $this->call('db:seed');
        }
        
        $this->info("Déploiement terminé");
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 3 : Directive interactive

```php
<?php

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:create {name} {--admin}';
    }
    
    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $isAdmin = $this->flag('admin');
        
        // Confirmation interactive
        if (!$this->confirm("Créer l'utilisateur {$name} ?")) {
            $this->line('Annulé');
            return ExitCode::SUCCESS;
        }
        
        // Demander des informations supplémentaires
        $email = $this->ask("Email pour {$name} :");
        
        $this->info("Utilisateur {$name} créé avec l'email {$email}");
        
        if ($isAdmin) {
            $this->info("Droits administrateur attribués");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

## Flux d'exécution

```
AbstractDirective::run()
    │
    ├── try
    │   ├── beforeExecute()
    │   │   └── Hook personnalisé
    │   │
    │   ├── execute()
    │   │   └── Logique métier de la directive
    │   │       ├── Accès aux arguments
    │   │       ├── Accès aux flags
    │   │       └── Appels internes ($this->call())
    │   │
    │   ├── executeCalls()
    │   │   └── foreach($calls)
    │   │       └── executeCall()
    │   │           ├── extractCommandName()
    │   │           ├── findDirective()
    │   │           ├── isCircularCall()
    │   │           └── executeDirectiveInstance()
    │   │
    │   └── afterExecute()
    │       └── Hook personnalisé
    │
    └── catch(Throwable)
        ├── Gestion des erreurs
        └── Retourne ExitCode::RUNTIME_ERROR
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Erreur dans `beforeExecute()` | `Throwable` | `Error in before hook: {message}` |
| Erreur dans `execute()` | `Throwable` | `Error in execute hook: {message}` |
| Directive non trouvée | Aucune | `Directive not found: {command}` |
| Appel circulaire détecté | Aucune | `Circular call detected: {query}` |
| Erreur d'exécution d'appel | Aucune | `Error executing call: {message}` |

### Messages d'erreur typiques

```php
// Erreur dans beforeExecute()
"Error in before hook: Undefined variable $foo"

// Erreur dans execute()
"Error in execute hook: Connection refused"

// Directive non trouvée
"Directive not found: nonexistent"

// Appel circulaire
"Circular call detected: list"

// Erreur d'exécution
"Error executing call: Invalid argument"
```

## Intégration

L'`AbstractDirective` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveInterface` | Implémentation de l'interface |
| `DirectiveParserService` | Parsing des signatures |
| `DirectiveDiscoveryService` | Découverte des directives |
| `Console` | Sortie console |
| `Application` | Conteneur Laravel |

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Parsing | 1-5ms | Parsing de la signature |
| Exécution | Variable | Dépend de la directive |
| Mémoire | < 1MB | Minimal |

### Optimisations

```php
// Utiliser le cache pour les opérations coûteuses
protected function execute(): ExitCode
{
    static $cache = [];
    
    if (isset($cache['expensive_operation'])) {
        return $cache['expensive_operation'];
    }
    
    // Opération coûteuse
    $result = $this->expensiveOperation();
    $cache['expensive_operation'] = $result;
    
    return $result;
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | Support `readonly` |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Commande de déploiement avancée.
 *
 * Exemple complet d'une directive avec toutes les fonctionnalités :
 * - Arguments requis et optionnels
 * - Flags avec valeurs
 * - Arguments variadiques
 * - Appels internes
 * - Hooks
 * - Sortie interactive
 */
final class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--migrate} {--seed} {--compression=gzip} {files*}';
    }
    
    public function getDescription(): string
    {
        return 'Déploie l\'application dans l\'environnement spécifié';
    }
    
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['d', 'dp']);
    }
    
    protected function beforeExecute(): void
    {
        $this->newLine();
        $this->info('=== DÉPLOIEMENT ===');
        $this->newLine();
    }
    
    protected function execute(): ExitCode
    {
        $env = $this->argument('environment');
        $files = $this->getVariadicArguments();
        
        $this->info("Environnement: {$env}");
        
        // Vérification des fichiers
        if ($files->isNotEmpty()) {
            $this->line("Fichiers à déployer:");
            foreach ($files as $file) {
                $this->line("  - {$file}");
            }
        }
        
        // Exécution des tâches de maintenance
        $this->call('cache:clear');
        $this->call('config:cache');
        
        // Migration
        if ($this->flag('migrate')) {
            $this->call('db:migrate --force');
            $this->info('✅ Migration effectuée');
        }
        
        // Seed
        if ($this->flag('seed')) {
            $this->call('db:seed');
            $this->info('✅ Seed effectué');
        }
        
        // Compression
        $compression = $this->flag('compression');
        $this->info("Compression: {$compression}");
        
        return ExitCode::SUCCESS;
    }
    
    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->newLine();
        
        if ($exitCode->isSuccess()) {
            $this->info('✅ Déploiement réussi !');
        } else {
            $this->error('❌ Déploiement échoué');
        }
        
        $this->newLine();
    }
}

// Utilisation
// php directive deploy production --migrate --seed --compression=zstd fichiers/*
```

## Notes techniques

### Détection des appels circulaires

L'AbstractDirective utilise une pile d'exécution pour détecter les appels circulaires :

```php
private static array $executionStack = [];

// Exemple de circulation
// Directive A appelle Directive B
// Directive B appelle Directive A
// → "Circular call detected: A"
```

### Gestion des hooks

Les hooks `beforeExecute()` et `afterExecute()` sont optionnels mais permettent de :
- Initialiser des ressources
- Afficher des en-têtes/pieds de page
- Effectuer des nettoyages
- Logger des informations

### Méthodes finales

Les méthodes suivantes sont `final` et ne peuvent pas être surchargées :
- `getApplication()`, `getConsole()`, `getParsed()`, `getStructure()`
- Toutes les méthodes d'accès aux arguments et flags
- `run()`, `call()`, `getCalls()`
- Les méthodes de sortie (`line()`, `info()`, `error()`, etc.)

### Bonnes pratiques

1. **Toujours documenter** : Ajouter une description avec `getDescription()`
2. **Utiliser les alias** : Rendre la directive facile à utiliser
3. **Gérer les erreurs** : Toujours retourner un `ExitCode` approprié
4. **Fournir des feedbacks** : Utiliser `info()`, `line()`, `error()`
5. **Tester les directives** : Utiliser `DirectiveTestingService`
---
<!-- ==== ./docs/api-reference/tasks/render-task.md ==== -->

# RenderDispatcher - Référence Technique

## Description

Task responsable du rendu des différents types de sorties (aide, liste, messages, tableaux, etc.) en utilisant le pattern Strategy. Délègue le rendu à des stratégies spécialisées selon le type demandé.

## Hiérarchie

```
RenderDispatcher (final)
    └── Utilise : RenderStrategyInterface (via les stratégies concrètes)
```

## Rôle principal

Centraliser la logique de rendu des directives CLI. Gère les fallbacks (ex: LIST sans directives → EMPTY), sélectionne la stratégie appropriée et exécute le rendu avec les remplacements de variables.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `execute(object $record, RenderType $type): string`

Exécute le processus de rendu pour l'enregistrement et le type donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `object` | Enregistrement contenant les données à rendre (ex: `RenderRecord`, `ConflictDisplayRecord`) |
| `$type` | `RenderType` | Type de rendu à effectuer (`HELP`, `LIST`, `SUCCESS`, `ERROR`, etc.) |

**Retourne :** `string` - Le contenu rendu (prêt à être affiché)

**Exemple :**
```php
$task = new RenderDispatcher();
$record = new RenderRecord(type: RenderType::HELP);
$output = $task->execute($record, RenderType::HELP);
echo $output;
```

## Cas d'utilisation

### Cas 1 : Rendu de l'aide

```php
$record = new RenderRecord(type: RenderType::HELP);
$output = $this->renderDispatcher->execute($record, RenderType::HELP);
// Affiche l'aide complète du système de directives
```

### Cas 2 : Rendu de la liste des directives

```php
$directives = new DirectiveMetadataCollection();
$directives->add(new DirectiveMetadataRecord(...));

$record = new RenderRecord(type: RenderType::LIST, directives: $directives);
$output = $this->renderDispatcher->execute($record, RenderType::LIST);
// Affiche la liste formatée des directives disponibles
```

### Cas 3 : Rendu d'un message de succès

```php
$record = new RenderRecord(type: RenderType::SUCCESS, message: 'Operation completed');
$output = $this->renderDispatcher->execute($record, RenderType::SUCCESS);
// Affiche "Operation completed" en vert avec icône ✓
```

### Cas 4 : Rendu d'un conflit d'alias

```php
$record = new ConflictDisplayRecord(
    name: 'user-create',
    classNames: new StringTypedCollection(['UserCreateDirective', 'AdminUserCreateDirective']),
    signatures: new StringTypedCollection(['user-create', 'admin-user-create']),
    descriptions: new StringTypedCollection(['Create user', 'Create admin user']),
);
$output = $this->renderDispatcher->execute($record, RenderType::CONFLICT);
// Affiche les directives en conflit avec choix interactif
```

## Flux d'exécution

<img src="../graphics/render_task_execute_flow.png" alt="Render Task Execution Flow" width="800" />

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Aucune stratégie ne supporte le type | Retourne `ReplacementCollection` vide → rendu minimal |
| `RenderRecord` avec `directives` null pour LIST | Fallback vers type `EMPTY` |
| Enregistrement non supporté par la stratégie | La stratégie doit gérer l'erreur en interne |
| Template de rendu invalide | L'exception est propagée (gérée par l'appelant) |

## Intégration

`RenderDispatcher` s'intègre avec :

- **`DirectiveRendererService`** : Utilise la task pour générer les sorties
- **`RenderStrategyInterface`** : Interface que toutes les stratégies implémentent
- **`ReplacementCollection`** : Collection des remplacements pour les templates
- **`RenderType`** : Enum définissant les types de rendu disponibles

## Strategies disponibles

| Stratégie | Type supporté | Description |
|-----------|---------------|-------------|
| `HelpRenderStrategy` | `HELP` | Affiche l'aide générale |
| `ListRenderStrategy` | `LIST`, `EMPTY` | Affiche la liste des directives |
| `NotFoundRenderStrategy` | `NOT_FOUND` | Affiche une erreur "directive non trouvée" |
| `MessageRenderStrategy` | `SUCCESS`, `ERROR` | Affiche des messages simples |
| `ConflictRenderStrategy` | `CONFLICT` | Affiche les conflits d'alias |
| `TableRenderStrategy` | `TABLE` | Affiche un tableau formaté |
| `ValidationErrorRenderStrategy` | `VALIDATION_ERROR` | Affiche les erreurs de validation |
| `DisplayMessageRenderStrategy` | `DISPLAY_MESSAGE` | Affiche des messages formatés |
| `WarningRenderStrategy` | `WARNING` | Affiche des avertissements |
| `DebugRenderStrategy` | `DEBUG` | Affiche des messages de debug |
| `VersionRenderStrategy` | `VERSION` | Affiche les informations de version |

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Sélection de stratégie | O(n) avec n = nombre de stratégies (11 actuellement) |
| Rendu | Dépend de la stratégie et de la taille des données |
| Cache | Aucun mécanisme de cache interne |
| Mémoire | Une instance par appel (partagée via injection) |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis (readonly properties, union types) |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Enums\RenderType;
use AndyDefer\Directive\Records\RenderRecord;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;

// 1. Créer une instance du RenderDispatcher
$renderDispatcher = new RenderDispatcher();

// 2. Rendre l'aide
$helpRecord = new RenderRecord(type: RenderType::HELP);
echo $renderDispatcher->execute($helpRecord, RenderType::HELP);

// 3. Rendre une liste de directives
$directives = new DirectiveMetadataCollection();
$directives->add(new DirectiveMetadataRecord(
    signature: 'user-create',
    class: UserCreateDirective::class,
    description: 'Create a new user',
    aliases: new StringTypedCollection(),
));

$listRecord = new RenderRecord(type: RenderType::LIST, directives: $directives);
echo $renderDispatcher->execute($listRecord, RenderType::LIST);

// 4. Rendre un message de succès
$successRecord = new RenderRecord(type: RenderType::SUCCESS, message: 'User created successfully');
echo $renderDispatcher->execute($successRecord, RenderType::SUCCESS);

// 5. Rendre une erreur
$errorRecord = new RenderRecord(type: RenderType::ERROR, message: 'Failed to create user');
echo $renderDispatcher->execute($errorRecord, RenderType::ERROR);
```
---
<!-- ==== ./docs/api-reference/tasks/input-task.md ==== -->

# InputDispatcher - Référence Technique

## Description

Task responsable de la gestion des interactions utilisateur en ligne de commande. Utilise le pattern Strategy pour déléguer différents types d'entrée (questions simples, confirmations, choix utilisateur) à des stratégies spécialisées.

## Hiérarchie

```
InputDispatcher (final)
    └── Utilise : InputStrategyInterface
            ├── SimpleQuestionStrategy
            ├── ConfirmationStrategy
            └── UserChoiceStrategy
```

## Rôle principal

Centraliser la logique d'interaction utilisateur. Gère le flux d'entrée standard, sélectionne la stratégie appropriée selon le type d'entrée demandé et exécute la capture de la réponse utilisateur.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `execute(object $record, InputType $type): mixed`

Exécute la stratégie d'entrée pour l'enregistrement et le type donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `object` | Enregistrement contenant la configuration de l'entrée (`QuestionRecord`, `UserChoiceRecord`) |
| `$type` | `InputType` | Type d'entrée à effectuer (`SIMPLE_QUESTION`, `CONFIRMATION`, `USER_CHOICE`) |

**Retourne :** `mixed` - Le résultat de l'entrée utilisateur, ou `null` si aucune stratégie ne supporte le type

**Exemple :**
```php
$task = new InputDispatcher();
$record = new QuestionRecord('What is your name?');
$name = $task->execute($record, InputType::SIMPLE_QUESTION);
```

## Cas d'utilisation

### Cas 1 : Question simple

```php
$task = new InputDispatcher();
$record = new QuestionRecord('What is your name?');
$name = $task->execute($record, InputType::SIMPLE_QUESTION);
// Affiche: "What is your name? " 
// Attend la saisie utilisateur, retourne la valeur trimée
```

### Cas 2 : Confirmation (Oui/Non)

```php
$task = new InputDispatcher();
$record = new QuestionRecord('Do you want to continue?');
$confirmed = $task->execute($record, InputType::CONFIRMATION);
// Affiche: "Do you want to continue? (y/n) "
// Retourne true pour 'y'/'yes', false pour 'n'/'no'
```

### Cas 3 : Choix utilisateur

```php
$task = new InputDispatcher();
$record = new UserChoiceRecord(choice: 0, max: 5);
$choice = $task->execute($record, InputType::USER_CHOICE);
// Affiche: "Which one do you want to use? [1-5]: "
// Retourne l'entier choisi (1-5) ou null si invalide
```

## Flux d'exécution
<img src="../graphics/input_task_execution_flow.png" alt="Input Task Execution Flow" width="800" />
## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Type non supporté | `execute()` retourne `null` |
| Record incompatible avec la stratégie | Stratégie retourne valeur par défaut (chaîne vide, false, null) |
| Entrée utilisateur invalide | Validation spécifique à chaque stratégie |
| Flux d'entrée indisponible | Exception selon l'implémentation PHP |

## Intégration

`InputDispatcher` s'intègre avec :

- **`DirectiveInteractionService`** : Utilise la task pour les interactions utilisateur
- **`InputStrategyInterface`** : Interface que toutes les stratégies implémentent
- **`QuestionRecord`** : Enregistrement pour les questions et confirmations
- **`UserChoiceRecord`** : Enregistrement pour les choix utilisateur
- **`InputType`** : Enum définissant les types d'entrée disponibles

## Strategies disponibles

| Stratégie | Type supporté | Description |
|-----------|---------------|-------------|
| `SimpleQuestionStrategy` | `SIMPLE_QUESTION` | Question simple, retourne la réponse brute |
| `ConfirmationStrategy` | `CONFIRMATION` | Confirmation Oui/Non, retourne booléen |
| `UserChoiceStrategy` | `USER_CHOICE` | Choix dans une plage, retourne entier ou null |

## Détail des stratégies

### SimpleQuestionStrategy

- **Affiche** : `question + suffixe (espace)`
- **Lit** : Une ligne de l'entrée standard
- **Retourne** : La ligne lue, trimée
- **Erreur** : Retourne une chaîne vide si record invalide

### ConfirmationStrategy

- **Affiche** : `question + suffixe " (y/n)"`
- **Lit** : Une ligne de l'entrée standard
- **Retourne** : `true` pour `y`/`yes` (insensible à la casse), `false` sinon
- **Erreur** : Retourne `false` si record invalide

### UserChoiceStrategy

- **Affiche** : `"Which one do you want to use? [1-{max}]: "`
- **Lit** : Une ligne de l'entrée standard
- **Valide** : Entier entre 1 et `max`
- **Retourne** : L'entier choisi, ou `null` si invalide
- **Erreur** : Retourne `null` si record invalide

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Sélection de stratégie | O(n) avec n = nombre de stratégies (3 actuellement) |
| Lecture entrée | Dépend de l'utilisateur (temps réel) |
| Validation | O(1) par stratégie |
| Mémoire | Une instance par appel (partagée via injection) |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Enums\InputType;
use AndyDefer\Directive\Records\QuestionRecord;
use AndyDefer\Directive\Records\UserChoiceRecord;
use AndyDefer\Directive\Dispatchers\InputDispatcher;

// 1. Créer une instance du InputDispatcher
$inputDispatcher = new InputDispatcher();

// 2. Question simple
$nameRecord = new QuestionRecord('What is your name?');
$name = $inputDispatcher->execute($nameRecord, InputType::SIMPLE_QUESTION);
echo "Hello, {$name}!\n";

// 3. Confirmation
$confirmRecord = new QuestionRecord('Do you want to save?');
if ($inputDispatcher->execute($confirmRecord, InputType::CONFIRMATION)) {
    echo "Saving...\n";
} else {
    echo "Cancelled.\n";
}

// 4. Choix utilisateur
$choiceRecord = new UserChoiceRecord(choice: 0, max: 3);
$choice = $inputDispatcher->execute($choiceRecord, InputType::USER_CHOICE);

if ($choice !== null) {
    echo "You selected option {$choice}\n";
} else {
    echo "Invalid selection\n";
}
```
---
<!-- ==== ./docs/api-reference/entry-point.md ==== -->

# Directive CLI - Référence Technique

## Description

Point d'entrée principal de l'application CLI Laravel Directive. Bootstrap l'environnement, charge les autoloaders, initialise le conteneur d'injection de dépendances et exécute la directive demandée.

## Rôle principal

Servir de script exécutable (`./vendor/bin/directive`) qui orchestre le chargement de l'application, la résolution des dépendances et l'exécution des directives CLI. Gère le chargement des variables d'environnement, la découverte des autoloaders et la configuration personnalisée.

## Installation

```bash
composer require andydefer/laravel-directive
```

Le script est automatiquement disponible dans `./vendor/bin/directive`.

## Utilisation

```bash
# Afficher l'aide
./vendor/bin/directive --help

# Lister toutes les directives disponibles
./vendor/bin/directive --list

# Exécuter une directive
./vendor/bin/directive user-list --verbose

# Exécuter une directive avec arguments
./vendor/bin/directive user-create "John Doe" john@example.com --role=admin

# Afficher la version
./vendor/bin/directive --version
```

## Flux d'exécution

<img src="./graphics/complete_directive_flow.png" alt="Complete Directive Flow" width="800" />

## Gestion des erreurs

| Situation | Comportement | Code de sortie |
|-----------|--------------|----------------|
| Autoloader non trouvé | Message d'erreur + exit(1) | 1 |
| Fichier de configuration manquant | Utilise le chemin par défaut (`app/Directives`) | - |
| Directive non trouvée | Message "not found" + exit(3) | 3 |
| Signature invalide | Message d'erreur + exit(4) | 4 |
| Exception non capturée | Message d'erreur + exit(1) | 1 |

## Configuration

### Fichier de configuration `config/directive.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Directives Path
    |--------------------------------------------------------------------------
    |
    | This option defines where your directive classes are located.
    |
    */
    'path' => getcwd() . '/app/Directives',
];
```

### Variables d'environnement

| Variable | Description |
|----------|-------------|
| `DIRECTIVE_DEBUG` | Active le mode debug (`true`/`false`) |

## Dépendances chargées

| Service | Rôle |
|---------|------|
| `DirectiveKernel` | Orchestrateur principal |
| `DirectiveExecutionService` | Exécution des directives |
| `DirectiveDiscoveryService` | Découverte des directives |
| `DirectiveParserService` | Parsing des signatures |
| `DirectiveHydratorService` | Hydratation des instances |
| `DirectiveRendererService` | Rendu des sorties |
| `LaravelBootstrapper` | Bootstrap optionnel de Laravel |
| `SignatureValidationService` | Validation des signatures |

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Bootstrap | Une fois par exécution |
| Conteneur | Singleton pour les services réutilisables |
| Découverte | Mise en cache des packages scannés |
| Mémoire | Libérée après l'exécution |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis |
| Laravel 10+ | ✅ Optionnel (via bootstrapper) |
| Composer | ✅ Requis pour l'autoloading |

## Exemple complet

```bash
# Créer une nouvelle directive
$ ./vendor/bin/directive make-directive user-list

# Afficher la liste des directives
$ ./vendor/bin/directive --list

Available Directives
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  user-list (App\Directives\UserListDirective)
    List all users
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

# Exécuter une directive avec options
$ ./vendor/bin/directive user-list --role=admin --verbose

Listing users with role: admin
Verbose mode enabled
Users listed successfully!

# Afficher l'aide d'une directive
$ ./vendor/bin/directive user-list --help

Usage:
  user-list [options]

Options:
  --role=     Filter by role
  --verbose   Enable verbose output

Description:
  List all users with optional filters
```

## Voir aussi

- [`DirectiveKernel`](directive-kernel.md) - Noyau d'exécution
- [`DirectiveExecutionService`](directive-execution-service.md) - Service d'exécution
- [`DirectiveConfig`](directive-config.md) - Configuration des chemins
- [`LaravelBootstrapper`](laravel-bootstrapper.md) - Bootstrap de Laravel
- [`RenderDispatcher`](./tasks/render-task.md) - Tâche de rendu
- [`InputDispatcher`](./tasks/input-task.md) - Tâche d'entrée utilisateur
<!-- ==== ./docs/api-reference/testing.md ==== -->

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
7. [Tester les arguments et options](#tester-les-arguments-et-options)
8. [Tester les arguments variadiques](#tester-les-arguments-variadiques)
9. [Tester les interactions utilisateur (mocks)](#tester-les-interactions-utilisateur-mocks)
10. [Tester les codes de sortie](#tester-les-codes-de-sortie)
11. [Tester avec la base de données](#tester-avec-la-base-de-données)
12. [Bonnes pratiques](#bonnes-pratiques)
13. [Exemple complet](#exemple-complet)

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
// Les fichiers sont créés dans /tmp/directive_test_xxx/
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

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    // (accessible via réflexion si nécessaire)
    $reflection = new \ReflectionClass($service);
    $tempDirProperty = $reflection->getProperty('tempDir');
    $tempDir = $tempDirProperty->getValue($service);
    
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

    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    $this->assertStringContainsString('Hello, Jane!', $response->output);
}
```

---

## Tests en mode intégré (avec Laravel)

### ⚠️ Prérequis important : Enregistrement des directives dans le conteneur

Pour que `DirectiveTestingService` puisse instancier automatiquement vos directives via `run(string $class)`, vos directives doivent être **enregistrées dans le conteneur de services**. En mode intégré (avec Laravel), le service utilise `$application->make($class)` qui nécessite que la directive soit bindée dans le conteneur.

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

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}

// Test - avec argument
public function test_optional_argument_provided(): void
{
    $response = $this->service->run(UserListDirective::class, ['10']);
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}

// Test - valeur surchargée
public function test_default_value_overridden(): void
{
    $response = $this->service->run(UserListDirective::class, ['25']);
    // $limit = 25
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}

// Test - option présente
public function test_option_present(): void
{
    $response = $this->service->run(CacheClearDirective::class, ['--force']);
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}
```

---

## Tester les arguments variadiques

### Directive avec arguments variadiques

```php
// Directive
public function getSignature(): string
{
    return 'process {name} {files*}'
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
```

```php
// Test
public function test_variadic_arguments(): void
{
    $response = $this->service->run(ProcessDirective::class, [
        'John', 'file1.txt', 'file2.txt', 'file3.txt'
    ]);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    $this->assertStringContainsString('file1.txt', $response->output);
    $this->assertStringContainsString('file2.txt', $response->output);
    $this->assertStringContainsString('file3.txt', $response->output);
}

public function test_variadic_with_no_files(): void
{
    $response = $this->service->run(ProcessDirective::class, ['John']);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    $this->assertStringContainsString('Processing 0 files', $response->output);
}
```

---

## Tester les interactions utilisateur (mocks)

### Problème : les méthodes `ask()` et `confirm()` bloquent les tests

```php
// ❌ Ce test va bloquer car il attend une saisie utilisateur
public function test_interactive_directive(): void
{
    $response = $this->service->run(SetupDirective::class, []);
    // Bloque !!!
}
```

### Solution 1 : Mocker l'interaction (recommandée)

```php
public function test_interactive_directive_with_mock(): void
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
    // Note : Nous devons passer par réflexion car le constructeur
    // de la directive attend un DirectiveContext et un DirectiveInteractionService
    $context = $this->createMock(DirectiveContext::class);
    $context->method('getBlueprint')->willReturn(
        new DirectiveBlueprintRecord(SetupDirective::class, 'setup', 'Setup wizard')
    );
    $context->method('getAliases')->willReturn(new StringTypedCollection());
    
    $directive = new SetupDirective($context, $interaction);
    
    // Nous devons utiliser la réflexion pour injecter la directive
    // car run() ne permet pas d'injecter directement une instance
    $reflection = new \ReflectionClass($this->service);
    $createDirectiveMethod = $reflection->getMethod('createDirective');
    $executeDirectiveMethod = $reflection->getMethod('executeDirective');
    
    // Alternative plus simple : utiliser la réflexion pour exécuter directement
    $response = $executeDirectiveMethod->invoke($this->service, $directive, []);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}
```

### Solution 2 : Rendre la directive non-interactive pour les tests

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
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}
```

### Solution 3 : Étendre DirectiveTestingService pour supporter les mocks

```php
// Créer une classe de test personnalisée
class TestableDirectiveTestingService extends DirectiveTestingService
{
    private ?DirectiveInteractionService $mockInteraction = null;
    
    public function setMockInteraction(DirectiveInteractionService $interaction): void
    {
        $this->mockInteraction = $interaction;
    }
    
    private function createDirective(string $class): AbstractDirective
    {
        if ($this->mockInteraction !== null) {
            // Injecter le mock à la place de l'interaction réelle
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            
            if ($constructor) {
                $args = [];
                foreach ($constructor->getParameters() as $param) {
                    $paramName = $param->getType()?->getName();
                    if ($paramName === DirectiveInteractionService::class) {
                        $args[] = $this->mockInteraction;
                    } elseif ($paramName === DirectiveContext::class) {
                        $args[] = $this->createDirectiveContext($class);
                    } else {
                        $args[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                    }
                }
                return $reflection->newInstanceArgs($args);
            }
        }
        
        return parent::createDirective($class);
    }
}

// Utilisation
public function test_with_mock(): void
{
    $mockInteraction = $this->createMock(DirectiveInteractionService::class);
    $mockInteraction->expects($this->once())->method('ask')->willReturn('Test');
    
    $service = new TestableDirectiveTestingService();
    $service->setMockInteraction($mockInteraction);
    
    $response = $service->run(InteractiveDirective::class, []);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
}
```

### Tester une erreur

```php
public function test_directive_failure(): void
{
    $response = $this->service->run(FailingDirective::class, []);
    $this->assertSame(ExitCode::FAILURE, $response->exit_code);
    $this->assertStringContainsString('Something went wrong', $response->output);
}
```

### Tester un argument invalide

```php
public function test_invalid_argument(): void
{
    $response = $this->service->run(CalculatorDirective::class, []);
    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
    $this->assertStringContainsString('Not enough arguments', $response->output);
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
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
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

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('created successfully', $response->output);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_create_user_missing_name_returns_error(): void
    {
        $response = $this->service->run(
            UserManagerDirective::class,
            ['create', '--role=admin']
        );

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Name and email are required', $response->output);
    }

    public function test_create_user_with_default_role(): void
    {
        $response = $this->service->run(
            UserManagerDirective::class,
            ['create', 'Jane Doe', 'jane@example.com']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        
        $user = User::where('email', 'jane@example.com')->first();
        $this->assertSame('user', $user->role);
    }

    // ==================== List Action Tests ====================

    public function test_list_users_shows_all_users(): void
    {
        User::create(['name' => 'John', 'email' => 'john@test.com', 'role' => 'admin']);
        User::create(['name' => 'Jane', 'email' => 'jane@test.com', 'role' => 'user']);

        $response = $this->service->run(UserManagerDirective::class, ['list']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('John', $response->output);
        $this->assertStringContainsString('Jane', $response->output);
    }

    public function test_list_users_when_empty_shows_warning(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['list']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No users found', $response->output);
    }

    // ==================== Delete Action Tests ====================

    public function test_delete_user_success(): void
    {
        User::create(['name' => 'John Doe', 'email' => 'john@test.com', 'role' => 'admin']);

        $response = $this->service->run(UserManagerDirective::class, ['delete', 'John Doe']);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('deleted successfully', $response->output);
        $this->assertDatabaseMissing('users', ['name' => 'John Doe']);
    }

    public function test_delete_nonexistent_user_returns_error(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['delete', 'Nonexistent']);

        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('not found', $response->output);
    }

    public function test_delete_missing_name_returns_error(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['delete']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Name is required', $response->output);
    }

    // ==================== Invalid Action Tests ====================

    public function test_invalid_action_returns_error(): void
    {
        $response = $this->service->run(UserManagerDirective::class, ['invalid']);

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
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
- ✅ Simuler les interactions utilisateur via des mocks
- ✅ Vérifier les codes de sortie et les sorties
- ✅ Tester les cas d'erreur

**Rappel :** Toujours appeler `destroy()` dans `tearDown()` pour nettoyer le répertoire temporaire.

**Important pour les mocks :** Le service utilise actuellement `$application->make($class)` pour instancier les directives. Pour injecter des mocks de `DirectiveInteractionService`, vous devrez soit :
1. Enregistrer votre mock dans le conteneur Laravel avant d'instancier le service
2. Étendre `DirectiveTestingService` pour permettre l'injection de mocks
3. Utiliser la réflexion pour remplacer l'interaction après l'instanciation
---