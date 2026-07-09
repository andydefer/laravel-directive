# DirectiveTestingService - Référence Technique

## Description

Service d'isolation et de test des directives dans un environnement temporaire. Permet d'exécuter des directives de manière isolée avec un conteneur dédié et un répertoire de travail autonome.

## Hiérarchie / Implémentations

```
DirectiveTestingService (Service de test)
    ├── DirectiveKernel (noyau d'exécution)
    ├── Container (conteneur de dépendances)
    └── Environnement temporaire
```

## Rôle principal

`DirectiveTestingService` est conçu pour faciliter les tests unitaires et d'intégration des directives. Il permet de :

- Exécuter des directives dans un environnement isolé
- Créer automatiquement un répertoire temporaire avec un `composer.json` minimal
- Capturer la sortie et le code de retour des directives
- Nettoyer automatiquement les ressources après les tests
- Ajouter des sources personnalisées pour les tests

## Installation

```bash
composer require andydefer/directive --dev
```

### Dépendances

- `Container` ou `LaravelApplication` - Conteneur de dépendances
- `DirectiveKernel` - Noyau d'exécution
- PHP 8.1+

## API / Méthodes publiques

### `__construct(Container|LaravelApplication $container, array $sourcePaths = [])`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$container` | `Container\|LaravelApplication` | Conteneur de dépendances |
| `$sourcePaths` | `array<int, string>` | Sources supplémentaires à scanner |

**Retourne :** `void`

**Exemple :**
```php
$container = DirectiveContainer::create();
$testingService = new DirectiveTestingService(
    $container,
    [__DIR__ . '/Fixtures/Directives']
);
```

---

### `run(string $query): DirectiveResponseRecord`

Exécute une directive dans l'environnement de test.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête complète à exécuter |

**Retourne :** `DirectiveResponseRecord` - Enregistrement de la réponse (code + sortie)

**Exceptions :** Aucune (les exceptions sont capturées et retournées dans le record)

**Exemple :**
```php
$response = $testingService->run('greet John --formal');

if ($response->exitCode === ExitCode::SUCCESS) {
    echo "Success: " . $response->output . "\n";
} else {
    echo "Error: " . $response->output . "\n";
}
```

---

### `getKernel(): DirectiveKernel`

Retourne le noyau d'exécution configuré.

**Retourne :** `DirectiveKernel` - Instance du noyau

**Exemple :**
```php
$kernel = $testingService->getKernel();
$kernel->addSource(__DIR__ . '/MoreDirectives');
```

---

### `getTempDir(): string`

Retourne le chemin du répertoire temporaire créé pour les tests.

**Retourne :** `string` - Chemin absolu du répertoire temporaire

**Exemple :**
```php
echo "Testing in: " . $testingService->getTempDir() . "\n";
// /tmp/directive_test_67a3b8c9d4e5f
```

---

### `destroy(): void`

Nettoie l'environnement de test (restaure le répertoire et supprime les fichiers temporaires).

**Retourne :** `void`

**Exemple :**
```php
// Dans tearDown() ou afterEach()
$testingService->destroy();
```

---

## Cas d'utilisation

### Cas 1 : Tests unitaires simples

```php
<?php

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;

final class GreetDirectiveTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        $this->testingService = new DirectiveTestingService($container);
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    public function test_greet_directive_with_name(): void
    {
        $response = $this->testingService->run('greet John');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello, John', $response->output);
    }
    
    public function test_greet_directive_with_formal_option(): void
    {
        $response = $this->testingService->run('greet John --formal');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Good day, John', $response->output);
    }
}
```

### Cas 2 : Tests avec sources personnalisées

```php
<?php

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;

final class CustomDirectiveTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        
        // Ajouter des sources personnalisées pour les tests
        $this->testingService = new DirectiveTestingService(
            $container,
            [
                __DIR__ . '/Fixtures/Directives',
                __DIR__ . '/src/Commands',
            ]
        );
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    public function test_custom_directive_from_fixtures(): void
    {
        $response = $this->testingService->run('test-custom param');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }
}
```

### Cas 3 : Tests avec contexte partagé

```php
<?php

final class ContextAwareDirectiveTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        $this->testingService = new DirectiveTestingService($container);
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    public function test_context_persistence_between_calls(): void
    {
        $kernel = $this->testingService->getKernel();
        
        // Définir un contexte
        $kernel->runSignature('context:set John');
        
        // Vérifier que le contexte est accessible
        $response = $this->testingService->run('context:get');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('John', $response->output);
        
        // Modifier le contexte
        $kernel->runSignature('context:increment');
        
        // Vérifier la modification
        $response = $this->testingService->run('context:get');
        $this->assertStringContainsString('counter":2', $response->output);
    }
}
```

### Cas 4 : Tests d'erreurs et d'exceptions

```php
<?php

final class ErrorHandlingTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        $this->testingService = new DirectiveTestingService($container);
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    public function test_unknown_command_returns_not_found(): void
    {
        $response = $this->testingService->run('unknown-command');
        
        $this->assertSame(ExitCode::NOT_FOUND, $response->exitCode);
        $this->assertStringContainsString('Directive not found', $response->output);
    }
    
    public function test_missing_required_parameter_returns_runtime_error(): void
    {
        $response = $this->testingService->run('test-directive');
        
        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exitCode);
        $this->assertStringContainsString('Missing required parameter', $response->output);
    }
    
    public function test_circular_dependency_returns_conflict(): void
    {
        $response = $this->testingService->run('test-circular');
        
        $this->assertSame(ExitCode::CONFLICT, $response->exitCode);
    }
}
```

### Cas 5 : Tests avec des fichiers et ressources

```php
<?php

final class FileOperationTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        $this->testingService = new DirectiveTestingService($container);
        
        // Créer un fichier de test dans le répertoire temporaire
        $tempDir = $this->testingService->getTempDir();
        file_put_contents($tempDir . '/test.txt', 'Hello World');
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    public function test_directive_reading_file(): void
    {
        $response = $this->testingService->run('file:read test.txt');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello World', $response->output);
    }
}
```

---

## Flux d'exécution

```
__construct($container, $sourcePaths)
    ↓
setupTempDirectory()
    ├── createTempDirectory()
    │   └── sys_get_temp_dir()/directive_test_{uniqid}
    ├── createMinimalComposerJson()
    │   └── composer.json (php ^8.1, autoload psr-4)
    └── changeToTempDirectory()
        └── chdir($tempDir)
    ↓
Adapter le conteneur
    ├── LaravelApplication → LaravelContainerAdapter
    └── Container → utilisé directement
    ↓
Initialiser DirectiveKernel
    ↓
Ajouter les sourcePaths au kernel
    ↓
run($query)
    ↓
ob_start() (capture de la sortie)
    ↓
Kernel->run(['directive', ...$query])
    ├── Découverte des directives
    ├── Exécution de la directive
    └── Retour du code de sortie
    ↓
ob_get_clean() (récupération de la sortie)
    ↓
Retourner DirectiveResponseRecord
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Création du répertoire temporaire échoue | Exception levée (RuntimeException) |
| Écriture du composer.json échoue | Exception levée (RuntimeException) |
| Changement de répertoire échoue | Exception levée (RuntimeException) |
| Directive introuvable | Retourne `ExitCode::NOT_FOUND` |
| Exception dans la directive | Capturée, retourne `ExitCode::RUNTIME_ERROR` |
| Nettoyage du répertoire temporaire | Logique robuste (scandir, suppression récursive) |

**Note :** Les exceptions pendant l'exécution d'une directive sont capturées et retournées dans `DirectiveResponseRecord`. Le service ne lève pas d'exceptions lors de l'exécution.

---

## Intégration

### Avec PHPUnit

```php
<?php

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;

abstract class DirectiveTestCase extends TestCase
{
    protected DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        $this->testingService = new DirectiveTestingService(
            $container,
            [__DIR__ . '/src/Directives']
        );
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    protected function assertDirectiveSuccess(string $query, string $expectedOutput = ''): void
    {
        $response = $this->testingService->run($query);
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        
        if ($expectedOutput !== '') {
            $this->assertStringContainsString($expectedOutput, $response->output);
        }
    }
    
    protected function assertDirectiveError(string $query, ExitCode $code): void
    {
        $response = $this->testingService->run($query);
        $this->assertSame($code, $response->exitCode);
    }
}
```

### Avec Pest

```php
<?php

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveTestingService;

function createTestingService(): DirectiveTestingService
{
    $container = DirectiveContainer::create(__DIR__);
    return new DirectiveTestingService(
        $container,
        [__DIR__ . '/src/Directives']
    );
}

beforeEach(function () {
    $this->testingService = createTestingService();
});

afterEach(function () {
    $this->testingService->destroy();
});

test('greet directive works', function () {
    $response = $this->testingService->run('greet John');
    
    expect($response->exitCode)->toBe(ExitCode::SUCCESS)
        ->and($response->output)->toContain('Hello, John');
});
```

### Avec Laravel

```php
<?php

namespace Tests\Feature;

use AndyDefer\Directive\Services\DirectiveTestingService;
use Illuminate\Foundation\Testing\TestCase;

abstract class DirectiveTest extends TestCase
{
    protected DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testingService = new DirectiveTestingService(
            $this->app,
            [app_path('Commands')]
        );
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
}
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `__construct()` | O(n) | Création du répertoire + composer.json |
| `run()` | O(n) | Dépend de la directive exécutée |
| `destroy()` | O(n) | Suppression récursive des fichiers |
| `getTempDir()` | O(1) | Accès à la propriété |
| `getKernel()` | O(1) | Accès à la propriété |

**Optimisations :**
- Le répertoire temporaire est créé une seule fois
- Le `composer.json` minimal évite de lourdes dépendances
- Nettoyage robuste pour éviter les fuites de fichiers

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

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
use PHPUnit\Framework\TestCase;

final class CompleteExampleTest extends TestCase
{
    private DirectiveTestingService $testingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $container = DirectiveContainer::create(__DIR__);
        
        $this->testingService = new DirectiveTestingService(
            $container,
            [
                __DIR__ . '/Fixtures/Directives',
                __DIR__ . '/src/Commands',
            ]
        );
        
        // Configuration du noyau pour les tests
        $kernel = $this->testingService->getKernel();
        $kernel->setLogBasePath(sys_get_temp_dir() . '/logs');
    }
    
    protected function tearDown(): void
    {
        $this->testingService->destroy();
        parent::tearDown();
    }
    
    public function test_successful_execution(): void
    {
        $response = $this->testingService->run('test-directive John john@example.com');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertNotEmpty($response->output);
        
        echo "Output: " . $response->output . "\n";
    }
    
    public function test_directive_with_options(): void
    {
        $response = $this->testingService->run(
            'test-directive John john@example.com json --force --verbose'
        );
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('force', $response->output);
    }
    
    public function test_directive_with_files_parameter(): void
    {
        $response = $this->testingService->run(
            'test-directive John john@example.com file1.txt file2.txt file3.txt'
        );
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('3 files', $response->output);
    }
    
    public function test_context_operations(): void
    {
        $kernel = $this->testingService->getKernel();
        
        // 1. Définir le contexte
        $response = $this->testingService->run('context:set John');
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        
        // 2. Vérifier le contexte
        $response = $this->testingService->run('context:get');
        $this->assertStringContainsString('John', $response->output);
        
        // 3. Modifier le contexte
        $kernel->runSignature('context:increment 5');
        $response = $this->testingService->run('context:get');
        $this->assertStringContainsString('counter":6', $response->output);
    }
    
    public function test_error_handling(): void
    {
        $testCases = [
            ['unknown-command', ExitCode::NOT_FOUND],
            ['test-circular', ExitCode::CONFLICT],
            ['test-directive', ExitCode::RUNTIME_ERROR], // Paramètres manquants
        ];
        
        foreach ($testCases as [$query, $expectedCode]) {
            $response = $this->testingService->run($query);
            $this->assertSame($expectedCode, $response->exitCode);
            
            if ($response->exitCode !== ExitCode::SUCCESS) {
                $this->assertNotEmpty($response->output);
            }
        }
    }
    
    public function test_custom_directive_from_source(): void
    {
        // Cette directive doit exister dans Fixtures/Directives
        $response = $this->testingService->run('fixture-test');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }
    
    public function test_access_to_temp_directory(): void
    {
        $tempDir = $this->testingService->getTempDir();
        
        $this->assertDirectoryExists($tempDir);
        $this->assertFileExists($tempDir . '/composer.json');
        
        // Le répertoire est isolé
        $this->assertDirectoryDoesNotExist($tempDir . '/vendor');
    }
    
    public function test_kernel_configuration(): void
    {
        $kernel = $this->testingService->getKernel();
        
        // Configurer le noyau
        $kernel
            ->ignoreSource(DiscoverySource::VENDOR)
            ->onlyNamespace('App\\Commands\\')
            ->setMaxDepth(5);
        
        // La configuration est prise en compte lors de l'exécution
        $response = $this->testingService->run('list');
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    }
}
```

## Voir aussi

- `DirectiveKernel` - Noyau d'exécution
- `DirectiveResponseRecord` - Enregistrement de la réponse
- `ExitCode` - Énumération des codes de sortie
- `Container` - Conteneur de dépendances
- `LaravelContainerAdapter` - Adaptateur pour Laravel