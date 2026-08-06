# DirectiveTestingService - Référence Technique

## Description

`DirectiveTestingService` est un service de test qui permet d'exécuter des directives dans un environnement isolé. Il crée un répertoire temporaire, configure un environnement de test minimal, et fournit une API simple pour tester les directives de manière programmatique.

## Hiérarchie / Implémentations

```
DirectiveTestingService
```

**Aucune interface implémentée** - Service autonome dédié aux tests.

## Rôle principal

`DirectiveTestingService` agit comme un environnement de test isolé pour les directives. Il assure :

- La création d'un **répertoire temporaire** pour les tests
- La génération d'un **`composer.json` minimal** pour simuler un environnement package
- L'**isolation** des tests (chaque instance a son propre répertoire)
- L'**exécution** des directives via trois méthodes différentes
- La **capture** de la sortie et des codes de retour
- La **collecte des problèmes** générés lors de l'exécution
- Le **nettoyage automatique** des fichiers temporaires

## Installation

```bash
composer require --dev andydefer/laravel-directive
```

### Dépendances

- PHP 8.1+
- `Application` - Conteneur Laravel
- `DirectiveKernel` - Noyau d'exécution
- `TestHelper` - Helper pour les tests
- `DirectiveResponseRecord` - Enregistrement de la réponse

---

## API / Méthodes publiques

### `__construct(Application $application, array $sourcePaths = [])`

Crée une nouvelle instance du service de test.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$application` | `Application` | Instance de l'application Laravel |
| `$sourcePaths` | `array<int, string>` | Chemins supplémentaires à scanner pour les directives |

**Exemple :**
```php
$service = new DirectiveTestingService(
    $app,
    ['/path/to/directives', '/other/path']
);
```

---

### `run(string $query): DirectiveResponseRecord`

Exécute une directive à partir d'une requête complète.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Requête complète (ex: "greet John --formal") |

**Retourne :** `DirectiveResponseRecord` - Enregistrement de la réponse

**Exemple :**
```php
$response = $service->run('greet John --formal');
echo $response->output; // "Hello, John!"
```

---

### `runDirective(string $fqcn, array $argv = []): DirectiveResponseRecord`

Exécute une directive par son FQCN (Fully Qualified Class Name).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fqcn` | `string` | Nom complet de la classe de la directive |
| `$argv` | `array<int, string>` | Arguments supplémentaires |

**Retourne :** `DirectiveResponseRecord` - Enregistrement de la réponse

**Exemple :**
```php
$response = $service->runDirective(
    'App\Directives\GreetDirective',
    ['John', '--formal']
);
```

---a

### `runSignature(string $query): DirectiveResponseRecord`

Exécute une directive par sa signature.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Signature complète (ex: "greet John --formal") |

**Retourne :** `DirectiveResponseRecord` - Enregistrement de la réponse

**Exemple :**
```php
$response = $service->runSignature('greet John --formal');
```

---

### `getKernel(): DirectiveKernel`

Retourne le noyau d'exécution.

**Retourne :** `DirectiveKernel` - Instance du noyau

**Exemple :**
```php
$kernel = $service->getKernel();
$kernel->addSource('/custom/path');
```

---

### `getTempDir(): string`

Retourne le chemin du répertoire temporaire.

**Retourne :** `string` - Chemin absolu du répertoire temporaire

**Exemple :**
```php
$tempDir = $service->getTempDir();
// /tmp/directive_test_67a1b2c3d4e5f
```

---

### `destroy(): void`

Nettoie le répertoire temporaire et restaure l'environnement.

**Exemple :**
```php
$service->destroy();
// Tous les fichiers temporaires sont supprimés
```

---

## Cas d'utilisation

### Cas 1 : Test d'une directive simple

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;

$service = new DirectiveTestingService(
    $app,
    ['/path/to/directives']
);

$response = $service->run('greet John');

assert($response->exit_code === ExitCode::SUCCESS);
assert(str_contains($response->output, 'Hello, John!'));

$service->destroy();
```

---

### Cas 2 : Test d'une directive avec arguments variadiques

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;

$service = new DirectiveTestingService(
    $app,
    ['/path/to/directives']
);

$response = $service->run('delete [file1.txt, file2.txt, file3.txt] --force');

assert($response->exit_code === ExitCode::SUCCESS);
assert(str_contains($response->output, 'Deleting 3 file(s)'));

$service->destroy();
```

---

### Cas 3 : Test d'une directive par FQCN

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;
use App\Directives\CalculatorDirective;

$service = new DirectiveTestingService($app);

$response = $service->runDirective(
    CalculatorDirective::class,
    ['add', '10', '5']
);

assert($response->exit_code === ExitCode::SUCCESS);
assert(str_contains($response->output, '15'));

$service->destroy();
```

---

### Cas 4 : Test des erreurs et des problèmes

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;

$service = new DirectiveTestingService($app);

// Test d'une directive inexistante
$response = $service->run('unknown-command');

assert($response->exit_code === ExitCode::NOT_FOUND);
assert(str_contains($response->output, 'Directive not found'));

// Vérification des problèmes
assert(!$response->problems->isEmpty());

$firstProblem = $response->problems->first();
assert($firstProblem['key'] === 'directive_not_found');
assert($firstProblem['context'] === 'Directive not found: unknown-command');

$service->destroy();
```

---

### Cas 5 : Test d'une directive avec contexte partagé

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;

$service = new DirectiveTestingService($app);

// Exécution de directives qui partagent un contexte
$service->run('context:set user_id 123');
$response = $service->run('context:get user_id');

assert(str_contains($response->output, '123'));

// Récupération du contexte via le noyau
$kernel = $service->getKernel();
$userId = $kernel->getContext()->get('user_id');
assert($userId === '123');

$service->destroy();
```

---

### Cas 6 : Test avec environnement isolé

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;

$service = new DirectiveTestingService($app);

// Le service crée un répertoire temporaire
$tempDir = $service->getTempDir();
assert(is_dir($tempDir));

// Le répertoire contient un composer.json minimal
assert(file_exists($tempDir . '/composer.json'));

// Le répertoire courant est le répertoire temporaire
$cwd = getcwd();
assert($cwd === $tempDir);

// Exécution d'une directive
$response = $service->run('greet John');

// Nettoyage
$service->destroy();
assert(!is_dir($tempDir));
```

---

### Cas 7 : Intégration avec PHPUnit

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
use PHPUnit\Framework\TestCase;

final class GreetDirectiveTest extends TestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new DirectiveTestingService(
            $this->app,
            ['/tests/Fixtures/Directives']
        );
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_greet_with_name(): void
    {
        $response = $this->service->run('greet John');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }

    public function test_greet_with_formal_flag(): void
    {
        $response = $this->service->run('greet John --formal');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Good day, John!', $response->output);
    }

    public function test_greet_without_name_uses_default(): void
    {
        $response = $this->service->run('greet');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, World!', $response->output);
    }
}
```

---

## Flux d'exécution

```
new DirectiveTestingService($app, $sourcePaths)
    ↓
__construct()
    ├── originalCwd = getcwd()
    ├── setupTempDirectory()
    │   ├── createTempDirectory()
    │   │   └── tempDir = sys_get_temp_dir() . '/directive_test_' . uniqid()
    │   ├── createMinimalComposerJson()
    │   │   └── file_put_contents(tempDir . '/composer.json', $content)
    │   └── changeToTempDirectory()
    │       └── chdir(tempDir)
    ├── kernel = DirectiveKernel::init($app)
    └── foreach ($sourcePaths) kernel->addSource($path)
    ↓
run($query)
    ├── ob_start()
    ├── try
    │   ├── argv = ['directive', ...explode(' ', $query)]
    │   ├── exitCode = kernel->run($argv)
    │   ├── output = ob_get_clean()
    │   └── return new DirectiveResponseRecord(exitCode, output, problems)
    └── catch (Throwable $e)
        ├── ob_end_clean()
        └── return new DirectiveResponseRecord(RUNTIME_ERROR, $e->getMessage(), problems)
    ↓
destroy()
    ├── restoreOriginalDirectory()
    │   └── chdir(originalCwd)
    └── removeTempDirectory()
        └── removeDirectory(tempDir)
```

---

## Gestion des erreurs

| Situation | Code de sortie | Comportement |
|-----------|----------------|--------------|
| Directive trouvée et exécutée avec succès | `ExitCode::SUCCESS` (0) | Retourne la sortie et les problèmes vides |
| Directive non trouvée | `ExitCode::NOT_FOUND` (3) | Retourne l'erreur et les problèmes |
| Erreur d'exécution | `ExitCode::RUNTIME_ERROR` (5) | Retourne l'exception et les problèmes |
| Argument invalide | `ExitCode::INVALID_ARGUMENT` (1) | Retourne l'erreur et les problèmes |
| Exception non capturée | `ExitCode::RUNTIME_ERROR` (5) | Retourne le message d'erreur |

### Messages d'erreur typiques

```
Directive not found: unknown-command
Division by zero
Unknown operation: invalid_op
Cannot convert value to int for parameter count
```

---

## Performance

- **Création du service** : O(1) - création d'un répertoire temporaire
- **Exécution d'une directive** : O(n) - dépend de la complexité de la directive
- **Nettoyage** : O(n) - suppression récursive des fichiers
- **Mémoire** : Minimale, les outputs sont capturés en mémoire tampon

### Optimisations

- Répertoires temporaires uniques par instance
- Nettoyage automatique via `destroy()`
- Pas de persistance entre les tests
- Composer.json minimal pour réduire les dépendances

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Helpers\Paths;

// 1. Création de l'application de test
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])->build();

// 2. Création du service de test
$service = new DirectiveTestingService(
    $app,
    [Paths::projectRoot() . '/tests/Fixtures/Directives']
);

// 3. Exécution des tests
$responses = [];

// Test 1: Directive simple
$responses['greet'] = $service->run('greet John');
// ✅ ExitCode::SUCCESS, output: "Hello, John!"

// Test 2: Directive avec flag
$responses['greet_formal'] = $service->run('greet John --formal');
// ✅ ExitCode::SUCCESS, output: "Good day, John!"

// Test 3: Directive avec arguments variadiques
$responses['delete'] = $service->run('delete [file1.txt, file2.txt] --force');
// ✅ ExitCode::SUCCESS, output: "Deleting 2 file(s)..."

// Test 4: Directive inexistante
$responses['unknown'] = $service->run('unknown-command');
// ❌ ExitCode::NOT_FOUND, output: "Directive not found: unknown-command"
// 💡 Suggestions disponibles

// 4. Vérification des résultats
foreach ($responses as $name => $response) {
    echo "Test: {$name}\n";
    echo "Exit Code: " . $response->exit_code->value . "\n";
    echo "Output: " . $response->output . "\n";
    
    if (!$response->problems->isEmpty()) {
        echo "Problems:\n";
        foreach ($response->problems as $problem) {
            echo "  - {$problem['context']}\n";
        }
    }
    echo "\n";
}

// 5. Nettoyage
$service->destroy();

// Output:
// Test: greet
// Exit Code: 0
// Output: Hello, John!
//
// Test: greet_formal
// Exit Code: 0
// Output: Good day, John!
//
// Test: delete
// Exit Code: 0
// Output: Deleting 2 file(s)...
//
// Test: unknown
// Exit Code: 3
// Output: Directive not found: unknown-command
// 💡 Did you mean:
//   • greet
// Problems:
//   - Directive not found: unknown-command
```

---

## Voir aussi

- `DirectiveKernel` - Noyau d'exécution
- `DirectiveResponseRecord` - Enregistrement de la réponse
- `TestHelper` - Helper pour les tests
- `ExitCode` - Énumération des codes de sortie
- `Paths` - Helper de résolution des chemins