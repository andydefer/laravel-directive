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