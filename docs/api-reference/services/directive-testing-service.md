# DirectiveTestingService - Référence Technique

## Description

Service de test pour les directives. Il permet d'exécuter des directives dans un environnement isolé, avec traçabilité complète et sans dépendance au système de fichiers.

## Hiérarchie

```
DirectiveTestingService
    ├── Dépend de DirectiveTestingContext (état)
    ├── Utilise une chaîne de responsabilité (Chain of Responsibility)
    └── Agrège OptionParserService, ArgumentApplierService, etc.
```

## Rôle principal

Ce service est le point d'entrée pour tester des directives. Il est **stateless** : tout l'état est stocké dans `DirectiveTestingContext`. Il suit le principe de séparation entre l'état et le comportement, ce qui le rend parfaitement testable.

## Installation

```bash
composer require --dev andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(?DirectiveTestingContext $context = null, ?DirectiveTestingConfigInterface $config = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `DirectiveTestingContext|null` | Contexte optionnel (créé par défaut) |
| `$config` | `DirectiveTestingConfigInterface|null` | Configuration optionnelle |

**Exemple :**
```php
$config = new DirectiveTestingConfig();
$context = new DirectiveTestingContext(false);
$context->setConfig($config);
$service = new DirectiveTestingService($context);
```

### `registerDirective(AbstractDirective $directive): void`

Enregistre une directive pour les tests.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directive` | `AbstractDirective` | Instance de directive à enregistrer |

**Exemple :**
```php
$directive = new MyDirective($service->getInteraction());
$service->registerDirective($directive);
```

### `registerDirectives(array $directives): void`

Enregistre plusieurs directives.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directives` | `array<AbstractDirective>` | Tableau de directives |

**Exemple :**
```php
$service->registerDirectives([$directive1, $directive2]);
```

### `clearRegisteredDirectives(): void`

Supprime toutes les directives enregistrées.

### `createTestDirective(string $signature, callable $execute): ClosureDirective`

Crée une directive temporaire avec une closure.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la directive |
| `$execute` | `callable` | Logique d'exécution |

**Retourne :** `ClosureDirective` - La directive créée

**Exemple :**
```php
$service->createTestDirective('test:cmd', function ($d) {
    $d->line('Hello World');
    return ExitCode::SUCCESS;
});
```

### `runDirective(string $className, array $arguments = []): DirectiveResponseRecord`

Exécute une directive.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$className` | `string` | Signature ou FQCN de la directive |
| `$arguments` | `array<string>` | Arguments à passer |

**Retourne :** `DirectiveResponseRecord` - Réponse avec code de sortie et sortie

**Exceptions :** Aucune (les erreurs sont capturées et retournées dans la réponse)

**Exemple :**
```php
$response = $service->runDirective('calculator', ['add', '5', '3']);
if ($response->exitCode === ExitCode::SUCCESS) {
    echo $response->output;
}
```

### `getInteraction(): DirectiveInteractionService`

Retourne le service d'interaction pour créer des directives.

**Exemple :**
```php
$directive = new MyDirective($service->getInteraction());
```

### `getContext(): DirectiveTestingContext`

Retourne le contexte pour inspection.

**Exemple :**
```php
$context = $service->getContext();
$executed = $context->hasBeenExecuted('my-cmd');
```

### `destroy(): void`

Nettoie l'environnement de test (supprime le répertoire temporaire, vide les registres).

## Cas d'utilisation

### Cas 1 : Tester une directive simple

```php
<?php

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;

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

    public function test_calculator_adds_numbers(): void
    {
        $directive = new CalculatorDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);

        $response = $this->service->runDirective('calculator', ['add', '5', '3']);

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('8', $response->output);
    }
}
```

### Cas 2 : Tester une directive temporaire avec closure

```php
public function test_temporary_directive(): void
{
    $executed = false;

    $this->service->createTestDirective('test:ping', function ($d) use (&$executed) {
        $executed = true;
        $d->line('Pong!');
        return ExitCode::SUCCESS;
    });

    $response = $this->service->runDirective('test:ping');

    $this->assertTrue($executed);
    $this->assertStringContainsString('Pong!', $response->output);
}
```

### Cas 3 : Tester une directive avec arguments variadiques

```php
public function test_variadic_arguments(): void
{
    $this->service->createTestDirective('process {files*}', function ($d) {
        $files = $d->getVariadicArguments()->toArray();
        $count = count($files);
        $d->line("Processing {$count} files");
        return ExitCode::SUCCESS;
    });

    $response = $this->service->runDirective('process', ['[', 'file1.txt,', 'file2.txt', ']']);

    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Processing 2 files', $response->output);
}
```

### Cas 4 : Inspecter le contexte après exécution

```php
public function test_context_tracks_execution(): void
{
    $directive = new CalculatorDirective($this->service->getInteraction());
    $this->service->registerDirective($directive);

    $response = $this->service->runDirective('calculator', ['add', '1', '2']);

    $context = $this->service->getContext();

    $this->assertTrue($context->hasBeenExecuted('calculator'));
    $this->assertEquals(1, $context->getExecutedDirectivesCount());
    $this->assertTrue($context->hasStepResult(TestingStep::CREATE_TEMP_DIRECTORY));
    $this->assertTrue($context->hasStepResult(TestingStep::BUILD_CONTAINER));
}
```

### Cas 5 : Nettoyage garanti

```php
public function test_service_cleans_up(): void
{
    $service = new DirectiveTestingService();
    $tempDir = $service->getContext()->getTempDir();

    $this->assertDirectoryExists($tempDir);

    $service->destroy();

    $this->assertNull($service->getContext()->getTempDir());
    $this->assertFileDoesNotExist($tempDir);
}
```

## Flux d'exécution

```
DirectiveTestingService::__construct()
    │
    ├── setConfig()
    │
    ├── initializeSteps()
    │       ├── CreateTempDirectoryStep
    │       ├── ChangeToTempDirectoryStep
    │       ├── CreateLaravelStructureStep (optionnel)
    │       ├── BootstrapLaravelStep (optionnel)
    │       └── BuildContainerStep
    │
    └── executeChain()
            │
            └── Chaque étape modifie le contexte
                    ├── setTempDir()
                    ├── setOriginalCwd()
                    ├── setLaravelApp()
                    ├── setContainer()
                    ├── setKernel()
                    ├── addStepResult()
                    └── addCreatedPath()
```

## Gestion des erreurs

| Situation | Code retour | Message dans la réponse |
|-----------|-------------|------------------------|
| Directive non trouvée | `ExitCode::NOT_FOUND` | `Directive not found: {name}` |
| Arguments invalides (parser) | `ExitCode::INVALID_ARGUMENT` | Message de l'exception (ex: `Not enough arguments (missing: "name")`) |
| Exception pendant l'exécution | `ExitCode::FAILURE` | Message de l'exception |

## Intégration

### Avec PHPUnit

```php
use PHPUnit\Framework\TestCase;
use AndyDefer\Directive\Services\DirectiveTestingService;

final class MyTest extends TestCase
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
}
```

### Avec Laravel

```php
$config = new DirectiveTestingConfig();
$context = new DirectiveTestingContext(bootLaravel: true);
$context->setConfig($config);
$service = new DirectiveTestingService($context);

$directive = new EloquentDirective($service->getInteraction());
$service->registerDirective($directive);
$response = $service->runDirective('db:query');
```

## Performance

| Opération | Complexité |
|-----------|------------|
| `registerDirective()` | O(1) |
| `runDirective()` (premier appel) | O(n) avec n = initialisation des steps |
| `runDirective()` (appels suivants) | O(1) après initialisation |
| `destroy()` | O(m) avec m = fichiers créés |

- L'initialisation des steps (création du répertoire, bootstrap Laravel) est **une seule fois** par instance
- Le service est **stateless** : toutes les données sont dans le contexte
- Aucun cache interne

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |

| Version Laravel | Support (bootLaravel = true) |
|----------------|-------------------------------|
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Contexts\DirectiveTestingContext;
use AndyDefer\Directive\Configs\DirectiveTestingConfig;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Enums\TestingStep;
use PHPUnit\Framework\TestCase;

final class CompleteTest extends TestCase
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

    public function test_full_workflow_with_traçabilité(): void
    {
        // 1. Création d'une directive temporaire
        $this->service->createTestDirective('test:cmd', function ($d) {
            $d->line('Hello World');
            return ExitCode::SUCCESS;
        });

        // 2. Exécution
        $response = $this->service->runDirective('test:cmd');

        // 3. Assertions sur le résultat
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello World', $response->output);

        // 4. Assertions sur le contexte
        $this->assertTrue($this->context->hasBeenExecuted('test:cmd'));
        $this->assertEquals(1, $this->context->getExecutedDirectivesCount());
        $this->assertTrue($this->context->hasTempDir());
        $this->assertTrue($this->context->hasStepResult(TestingStep::CREATE_TEMP_DIRECTORY));
        $this->assertTrue($this->context->hasStepResult(TestingStep::BUILD_CONTAINER));

        // 5. Inspection des étapes
        $stepResult = $this->context->getStepResult(TestingStep::CREATE_TEMP_DIRECTORY);
        $this->assertNotNull($stepResult);
        $this->assertTrue($stepResult->isSuccess());
        $this->assertStringContainsString('directive_test_', $stepResult->message);
    }

    public function test_multiple_directives(): void
    {
        $executed = [];

        $this->service->createTestDirective('cmd:one', function ($d) use (&$executed) {
            $executed[] = 'one';
            $d->line('Command One');
            return ExitCode::SUCCESS;
        });

        $this->service->createTestDirective('cmd:two', function ($d) use (&$executed) {
            $executed[] = 'two';
            $d->line('Command Two');
            return ExitCode::SUCCESS;
        });

        $response1 = $this->service->runDirective('cmd:one');
        $response2 = $this->service->runDirective('cmd:two');

        $this->assertStringContainsString('Command One', $response1->output);
        $this->assertStringContainsString('Command Two', $response2->output);
        $this->assertContains('one', $executed);
        $this->assertContains('two', $executed);
    }

    public function test_error_handling(): void
    {
        $this->service->createTestDirective('test:error', function () {
            throw new \RuntimeException('Something went wrong');
        });

        $response = $this->service->runDirective('test:error');

        $this->assertSame(ExitCode::FAILURE, $response->exitCode);
        $this->assertStringContainsString('Something went wrong', $response->output);
        $this->assertTrue($this->context->hasBeenExecuted('test:error'));
    }
}
```
---