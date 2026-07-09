# Laravel Directive

**Un framework CLI pour Laravel. Orchestration de pipelines, contexte partagé, découverte automatique, appels internes - avec ou sans Laravel.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Pourquoi Laravel Directive ?](#pourquoi-laravel-directive-)
3. [Première directive](#première-directive)
4. [Arguments et options](#arguments-et-options)
5. [Contexte partagé](#contexte-partagé)
6. [Appels internes (call)](#appels-internes-call)
7. [Découverte automatique](#découverte-automatique)
8. [Journalisation JSONL](#journalisation-jsonl)
9. [Suggestions de commandes](#suggestions-de-commandes)
10. [Directives intégrées](#directives-intégrées)
11. [Mode autonome (sans Laravel)](#mode-autonome-sans-laravel)
12. [Tests des directives](#tests-des-directives)
13. [Cas d'usage concrets](#cas-dusage-concrets)
14. [Bonnes pratiques](#bonnes-pratiques)
15. [Référence des commandes](#référence-des-commandes)

---

## Installation

```bash
composer require andydefer/laravel-directive

# Pour Laravel (optionnel)
php artisan vendor:publish --tag=directive-config
```

**Prérequis :** PHP 8.1+ | Laravel 12.x, 13.x, 14.x ou 15.x

---

## Pourquoi Laravel Directive ?

**Le problème :** Vous construisez un pipeline de déploiement avec plusieurs commandes qui doivent partager un état. Ou une application CLI complexe où les commandes s'appellent entre elles.

Avec Artisan, chaque commande est isolée. Pas de contexte partagé. Les appels internes sont limités. Les commandes doivent être enregistrées manuellement.

**La solution :** Laravel Directive. Des directives composables avec un contexte partagé, des appels internes structurés, et une découverte automatique.

```bash
# Un pipeline de déploiement en une seule commande
./vendor/bin/directive deploy staging --skip-tests
```
---

## Première directive

### 1. Créer la classe

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

class GreetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'greet {name} {--formal}';
    }

    public function getDescription(): string
    {
        return 'Say hello to someone';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['hello', 'hi']);
    }

    protected function beforeExecute(): void
    {
        if ($this->flag('formal')) {
            $this->info('Formal mode enabled');
        }
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $formal = $this->flag('formal');

        $greeting = $formal ? "Good day, $name" : "Hello, $name";
        $this->info($greeting);

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->newLine();
        $this->line('Execution completed');
    }
}
```

### 2. Exécuter

```bash
# Exécution simple
./vendor/bin/directive greet John
# Hello, John

# Avec option
./vendor/bin/directive greet John --formal
# Formal mode enabled
# Good day, John

# Avec alias
./vendor/bin/directive hello John
# Hello, John
```

---

## Arguments et options

### Signature syntax

```php
public function getSignature(): string
{
    return 'test {name} {email} {format=zip} {files*} {--force} {--verbose}';
}
```

| Syntaxe | Type | Description |
|---------|------|-------------|
| `{name}` | Requis | Argument obligatoire |
| `{email=value}` | Default | Valeur par défaut |
| `{files*}` | Variadic | Zéro ou plusieurs valeurs |
| `{--force}` | Flag | Option booléenne |
| `{--verbose}` | Flag | Option booléenne |

### Accès aux valeurs

```php
protected function execute(): ExitCode
{
    // Arguments requis
    $name = $this->argument('name');
    $email = $this->argument('email');

    // Argument avec valeur par défaut
    $format = $this->argument('format'); // 'zip' par défaut

    // Arguments variadiques
    $files = $this->getVariadicArguments();
    foreach ($files as $file) {
        $this->line("Processing: $file");
    }

    // Flags
    $force = $this->flag('force');
    $verbose = $this->flag('verbose');

    // Vérifications
    if ($this->hasArgument('email')) {
        // ...
    }

    if ($this->hasFlag('force')) {
        // ...
    }

    return ExitCode::SUCCESS;
}
```

### Exemple complet

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

class ProcessFilesDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {directory} {files*} {--verbose} {--force} {--limit=10}';
    }

    protected function execute(): ExitCode
    {
        $directory = $this->argument('directory');
        $files = $this->getVariadicArguments();
        $verbose = $this->flag('verbose');
        $force = $this->flag('force');
        $limit = (int) $this->argument('limit');

        $this->info("Processing files in $directory");

        $count = 0;
        foreach ($files as $file) {
            if ($count >= $limit) {
                $this->line("Limit reached ($limit)");
                break;
            }

            if ($verbose) {
                $this->line("Processing: $file");
            }

            if ($force) {
                $this->line("Force mode: overwriting existing files");
            }

            $count++;
        }

        $this->info("Processed $count files");

        return ExitCode::SUCCESS;
    }
}
```

---

## Contexte partagé

Le contexte est un `MapCollection` mutable accessible par toutes les directives.

### Définir et lire le contexte

```php
// Dans une directive
class SetContextDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:set {name}';
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');

        $this->contextSet('user_name', $name);
        $this->contextSet('timestamp', time());
        $this->contextSet('user_data', [
            'name' => $name,
            'role' => 'admin',
        ]);

        $this->info("Context set for: $name");

        return ExitCode::SUCCESS;
    }
}

// Dans une autre directive
class GetContextDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'context:get';
    }

    protected function execute(): ExitCode
    {
        $name = $this->contextGet('user_name', 'anonymous');
        $role = $this->contextGet('user_data.role', 'guest');

        $this->info("Current user: $name ($role)");

        // Obtenir tout le contexte
        $all = $this->contextAll();
        $this->table(['Key', 'Value'], $all->toArray());

        return ExitCode::SUCCESS;
    }
}
```

### Méthodes du contexte

```php
// Définir une valeur
$this->contextSet('key', 'value');

// Lire une valeur
$value = $this->contextGet('key', 'default');

// Vérifier l'existence
if ($this->contextHas('key')) {
    // ...
}

// Obtenir tout le contexte
$all = $this->contextAll();

// Fusionner des valeurs
$this->contextMerge([
    'user_id' => 42,
    'user_role' => 'admin',
]);

// Supprimer une clé
$this->contextRemove('temporary_data');

// Effacer tout le contexte
$this->contextClear();

// Incrémenter / Décrémenter
$this->contextIncrement('counter', 5);
$this->contextDecrement('counter', 2);
```

### Exemple : Pipeline de traitement

```php
class ProcessDataDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'data:process {file}';
    }

    protected function execute(): ExitCode
    {
        $file = $this->argument('file');

        // Étape 1 : Chargement
        $this->contextSet('file', $file);
        $this->call('data:load');

        // Étape 2 : Nettoyage
        $this->call('data:clean');

        // Étape 3 : Transformation
        $this->call('data:transform');

        // Récupérer les résultats
        $stats = $this->contextGet('processing_stats');
        $output = $this->contextGet('output_file');

        $this->info("✅ Processing complete");
        $this->info("📊 Stats: " . json_encode($stats));
        $this->info("📄 Output: $output");

        return ExitCode::SUCCESS;
    }
}
```

---

## Appels internes (call)

La méthode `call()` permet d'exécuter d'autres directives depuis une directive.

### Syntaxe

```php
// Appel simple
$this->call('build --clean');

// Avec argument
$this->call('deploy:backup staging');

// Avec options
$this->call('deploy:migrate --force');

// Appel dynamique
$env = $this->argument('environment');
$this->call("deploy:validate $env");
```

### Pipeline de déploiement

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--skip-tests} {--force}';
    }

    protected function beforeExecute(): void
    {
        $env = $this->argument('environment');
        $this->info("🚀 Starting deployment to $env");
        $this->contextSet('deployment_start', microtime(true));
    }

    protected function execute(): ExitCode
    {
        $env = $this->argument('environment');
        $skipTests = $this->flag('skip-tests');
        $force = $this->flag('force');

        // 1. Validation
        $this->call("deploy:validate $env");

        // 2. Backup
        $this->call("deploy:backup $env");

        // 3. Build
        if (!$skipTests) {
            $this->call('deploy:build --with-tests');
        } else {
            $this->call('deploy:build');
        }

        // 4. Migration
        $forceFlag = $force ? '--force' : '';
        $this->call("deploy:migrate $env $forceFlag");

        // 5. Activation
        $this->call("deploy:activate $env");

        // 6. Post-deploiement
        $this->call('deploy:post --notify');

        // 7. Cleanup
        $this->call('deploy:cleanup --keep-last=3');

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $duration = microtime(true) - $this->contextGet('deployment_start');
        $this->info("✅ Deployment completed in " . round($duration, 2) . "s");
    }
}
```

### Détection de circularité

```php
// Si une directive s'appelle elle-même ou crée un cycle
class CircularDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'circular';
    }

    protected function execute(): ExitCode
    {
        // Appel récursif détecté
        $this->call('circular'); // CONFLICT (circular)
        return ExitCode::SUCCESS;
    }
}

// Sortie :
// Circular call detected: circular
```

---

## Découverte automatique

Laravel Directive découvre automatiquement les directives via AST (Abstract Syntax Tree).

### Sources de découverte

| Source | Description | Dossier par défaut |
|--------|-------------|-------------------|
| **Built-in** | Directives intégrées | `src/BuiltIn/` |
| **Workspace** | Directives de l'application | `src/Directives/`, `app/Directives/` |
| **Vendor** | Directives des packages | `vendor/*/src/Directives/` |
| **Custom** | Sources configurées | Configurable |

### Configuration

```php
// config/directive.php
return [
    'directories' => [
        'app/Commands',
        'src/Directives',
        'lib/Console',
    ],
    'custom_sources' => [
        'packages/admin/src',
        'modules/core/src/Directives',
    ],
    'max_depth' => 4,
    'reserved' => ['help', 'list', 'version'],
];
```

### Filtrer la découverte

```php
// Dans le Kernel
$kernel = DirectiveKernel::init($container);

// Ignorer une source
$kernel->ignoreSource(DiscoverySource::VENDOR);

// Ajouter une source
$kernel->addSource(__DIR__ . '/src/Commands');

// Filtrer par namespace
$kernel->onlyNamespace('App\\Directives\\');
$kernel->excludeNamespace('App\\Directives\\Internal\\');

// Filtrer par préfixe
$kernel->onlyPrefix('app:');
$kernel->excludePrefix('admin:');

// Ignorer une directive spécifique
$kernel->ignoreDirective('deprecated:command');

// Mode silencieux
$kernel->silent(true);
```

### Exemple de découverte

```php
// Nouvelles directives découvertes automatiquement
class NewDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'new:command';
    }
}

// Immédiatement disponible, sans enregistrement
$ ./vendor/bin/directive new:command
```

---

## Journalisation JSONL

Chaque exécution est automatiquement journalisée au format JSONL (JSON Lines).

### Structure du log

```json
{
  "time": "2026-07-09T11:45:23+00:00",
  "level": "info",
  "type": "directive_execution",
  "payload": {
    "command": "deploy staging",
    "directive_class": "App\\Directives\\DeployDirective",
    "signature": "deploy {environment} {--skip-tests} {--force}",
    "exit_code": 0,
    "exit_code_label": "Success",
    "success": true,
    "duration_seconds": 12.345,
    "memory_bytes": 2048,
    "memory_human": "2.00 KB",
    "peak_memory_bytes": 4096,
    "peak_memory_human": "4.00 KB",
    "calls_count": 7,
    "context": {
      "environment": "staging",
      "deployment_start": 1700000000,
      "backup_file": "backup_staging_2026-07-09.sql"
    }
  }
}
```

### Configuration

```php
// config/directive.php
return [
    'log_base_path' => storage_path('logs/directive'),
];

// Ou en code
$kernel->setLogBasePath('/var/log/directive');
```

### Analyse des logs

```bash
# Compter les exécutions par jour
$ cat .directive/2026-07-09/11.jsonl | jq '.payload.command' | sort | uniq -c

# Trouver les erreurs
$ cat .directive/2026-07-09/11.jsonl | jq 'select(.level == "error")'

# Statistiques de durée
$ cat .directive/2026-07-09/11.jsonl | jq '.payload.duration_seconds' | awk '{sum+=$1; count++} END {print sum/count}'
```

### Résumé des logs

```php
$logger = $kernel->getLogger();
$summary = $logger->getSummary();

// [
//     'total' => 42,
//     'success' => 38,
//     'failed' => 4,
//     'success_rate' => 90.48,
//     'avg_duration' => 0.015,
//     'avg_memory' => 2048.5,
//     'total_calls' => 156,
//     'avg_calls' => 3.71,
// ]
```

---

## Suggestions de commandes

Laravel Directive utilise un BK-tree (algorithme de distance de Levenshtein) pour suggérer des commandes similaires.

### Exemple

```bash
$ ./vendor/bin/directive depoy
Directive not found: depoy

💡 Did you mean:
  • deploy
  • deploy:validate
  • deploy:backup
```

### Distance de Levenshtein

Les suggestions sont basées sur la distance d'édition (Levenshtein) avec un seuil de 2.

```bash
$ ./vendor/bin/directive lst
Directive not found: lst

💡 Did you mean:
  • list
  • help
  • version
  • build
  • test
```

### Alias

Les alias sont également pris en compte dans les suggestions.

```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['hello', 'hi']);
}

// ./vendor/bin/directive hel
💡 Did you mean:
  • hello
  • help
```

---

## Directives intégrées

### `help` - Aide

```bash
./vendor/bin/directive help
./vendor/bin/directive help deploy
./vendor/bin/directive -h
./vendor/bin/directive --help
```

### `list` - Liste des directives

```bash
./vendor/bin/directive list
./vendor/bin/directive ls
./vendor/bin/directive -l
./vendor/bin/directive --list
```

### `version` - Version

```bash
./vendor/bin/directive version
./vendor/bin/directive -v
./vendor/bin/directive --version
```

### `clean-logs` - Nettoyage des logs

```bash
# Supprimer les logs de plus de 30 jours
./vendor/bin/directive clean-logs

# Supprimer les logs de plus de 7 jours
./vendor/bin/directive clean-logs 7

# Simulation (dry-run)
./vendor/bin/directive clean-logs 14 --dry-run

# Mode verbeux
./vendor/bin/directive clean-logs 30 --verbose
```

---

## Mode autonome (sans Laravel)

Laravel Directive peut fonctionner sans Laravel, avec son propre conteneur.

### Script d'entrée

```php
#!/usr/bin/env php
<?php

// bin/app
require_once __DIR__ . '/vendor/autoload.php';

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\DirectiveKernel;

$container = DirectiveContainer::create(__DIR__);
$kernel = DirectiveKernel::init($container);

// Ajouter des sources
$kernel->addSource(__DIR__ . '/src/Directives');
$kernel->addSource(__DIR__ . '/app/Commands');

// Exécuter
exit($kernel->run($argv)->value);
```

### Utilisation

```bash
chmod +x bin/app
./bin/app deploy staging
./bin/app list
```

### Intégration avec Laravel

```php
// Dans Laravel (via ServiceProvider)
class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DirectiveKernel::class, function ($app) {
            return DirectiveKernel::init(
                new LaravelContainerAdapter($app)
            );
        });
    }
}
```

---

## Tests des directives

`DirectiveTestingService` permet de tester les directives en isolation.

### Configuration des tests

```php
<?php

namespace Tests\Directives;

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
use PHPUnit\Framework\TestCase;

class GreetDirectiveTest extends TestCase
{
    private DirectiveTestingService $testing;

    protected function setUp(): void
    {
        parent::setUp();

        $container = DirectiveContainer::create(__DIR__);
        $this->testing = new DirectiveTestingService(
            $container,
            [__DIR__ . '/../app/Directives']
        );
    }

    protected function tearDown(): void
    {
        $this->testing->destroy();
        parent::tearDown();
    }

    public function test_greet_directive_returns_success(): void
    {
        $response = $this->testing->run('greet John');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, John', $response->output);
    }

    public function test_greet_directive_with_formal_option(): void
    {
        $response = $this->testing->run('greet John --formal');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Good day, John', $response->output);
    }

    public function test_greet_directive_with_alias(): void
    {
        $response = $this->testing->run('hello John');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, John', $response->output);
    }

    public function test_greet_directive_handles_missing_argument(): void
    {
        $response = $this->testing->run('greet');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Missing required parameter', $response->output);
    }
}
```

### Tests avec contexte

```php
public function test_context_persistence(): void
{
    $kernel = $this->testing->getKernel();

    // Définir le contexte
    $kernel->runSignature('context:set John');

    // Vérifier le contexte
    $context = $kernel->getContext();
    $this->assertSame('John', $context->get('user_name'));

    // Lire le contexte depuis une directive
    $response = $this->testing->run('context:get');
    $this->assertStringContainsString('John', $response->output);
}
```

### Tests de pipeline

```php
public function test_deploy_pipeline_completes_successfully(): void
{
    $response = $this->testing->run('deploy staging --skip-tests');

    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    $this->assertStringContainsString('Deployment completed', $response->output);
}
```

---

## Cas d'usage concrets

### 1. Pipeline de déploiement

```php
class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--skip-tests} {--force}';
    }

    protected function execute(): ExitCode
    {
        $env = $this->argument('environment');

        $this->call("deploy:validate $env");
        $this->call("deploy:backup $env");
        $this->call('deploy:build' . ($this->flag('skip-tests') ? ' --skip-tests' : ''));
        $this->call("deploy:migrate $env" . ($this->flag('force') ? ' --force' : ''));
        $this->call("deploy:activate $env");
        $this->call('deploy:post --notify');

        return ExitCode::SUCCESS;
    }
}
```

### 2. Application CLI multi-commandes

```php
class AppDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'app {action} {--id=} {--format=}';
    }

    protected function execute(): ExitCode
    {
        $action = $this->argument('action');

        match ($action) {
            'deploy' => $this->call('deploy'),
            'reports' => $this->call('reports --format=' . $this->argument('format')),
            'monitor' => $this->call('monitor'),
            'status' => $this->call('status --id=' . $this->argument('id')),
            default => $this->error("Unknown action: $action"),
        };

        return ExitCode::SUCCESS;
    }
}
```

### 3. Data processing pipeline

```php
class ProcessDataDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'data:process {file} {--format=csv} {--dry-run}';
    }

    protected function execute(): ExitCode
    {
        $file = $this->argument('file');

        $this->call("data:load $file");
        $this->call('data:clean');
        $this->call('data:transform --aggressive');
        $this->call('data:validate');

        if (!$this->flag('dry-run')) {
            $format = $this->argument('format');
            $this->call("data:export --format=$format");
        }

        $stats = $this->contextGet('processing_stats');
        $this->info("📊 Processed {$stats['total']} records");

        return ExitCode::SUCCESS;
    }
}
```

### 4. Pipeline CI/CD

```php
class PipelineDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'ci:pipeline {--branch=main} {--parallel}';
    }

    protected function execute(): ExitCode
    {
        $branch = $this->argument('branch');

        $this->call('ci:lint');

        if ($this->flag('parallel')) {
            $this->call('ci:test --parallel');
        } else {
            $this->call('ci:test');
        }

        $this->call('ci:coverage');
        $this->call('ci:build');

        if ($branch === 'main') {
            $this->call('deploy --skip-tests');
        }

        $coverage = $this->contextGet('coverage', 0);
        $this->info("📊 Coverage: {$coverage}%");

        return ExitCode::SUCCESS;
    }
}
```

### 5. Application de monitoring

```php
class MonitorDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'monitor {--interval=5} {--services=*}';
    }

    protected function execute(): ExitCode
    {
        $interval = (int) $this->argument('interval');
        $services = $this->getVariadicArguments();

        while (true) {
            foreach ($services as $service) {
                $this->call("monitor:check $service");
            }

            $checkHistory = $this->contextGet('check_history', []);
            $checkHistory[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'services' => $services,
                'status' => $this->contextGet('service_status', []),
            ];

            if (count($checkHistory) > 100) {
                array_shift($checkHistory);
            }

            $this->contextSet('check_history', $checkHistory);
            $this->call('monitor:alert');

            sleep($interval);
        }

        return ExitCode::SUCCESS;
    }
}
```

---

## Bonnes pratiques

### ✅ Injection de services

```php
// BON
class UserController
{
    public function __construct(
        private readonly UniqueTaskServiceInterface $taskService
    ) {}
}

// ÉVITER (facade)
use AndyDefer\Task\Facades\Task;
Task::register(...);
```

### ✅ Validation des arguments

```php
protected function beforeExecute(): void
{
    $name = $this->argument('name');

    if (empty($name) || strlen($name) < 3) {
        $this->error('Name must be at least 3 characters');
        throw new \RuntimeException('Invalid name');
    }
}
```

### ✅ Utiliser le contexte pour les données partagées

```php
// ✅ BON
$this->contextSet('user_id', $user->id);
$userId = $this->contextGet('user_id');

// ÉVITER
global $userId;
$userId = 42;
```

### ✅ Gérer les erreurs

```php
protected function execute(): ExitCode
{
    try {
        // Logique métier
        return ExitCode::SUCCESS;
    } catch (ConnectionException $e) {
        $this->error('Database connection failed');
        return ExitCode::RUNTIME_ERROR;
    }
}
```

### ✅ Hooks before/after

```php
protected function beforeExecute(): void
{
    // Initialisation
    $this->contextSet('start_time', microtime(true));
    $this->info('Starting...');
}

protected function afterExecute(ExitCode $exitCode): void
{
    // Nettoyage
    $duration = microtime(true) - $this->contextGet('start_time');
    $this->info("Completed in {$duration}s");
}
```

### ✅ Journalisation structurée

```php
// Le logger capture automatiquement les métriques
$kernel->setLogBasePath('/var/log/directive');

// Visualisation
$summary = $kernel->getLogger()->getSummary();
```

### ✅ Tests

```php
// Utiliser DirectiveTestingService pour les tests
$testing = new DirectiveTestingService($container);
$response = $testing->run('deploy staging');
$this->assertSame(ExitCode::SUCCESS, $response->exit_code);
```

---

## Référence des commandes

| Commande | Description |
|----------|-------------|
| `./vendor/bin/directive help` | Affiche l'aide |
| `./vendor/bin/directive list` | Liste toutes les directives |
| `./vendor/bin/directive version` | Affiche la version |
| `./vendor/bin/directive clean-logs [days]` | Nettoie les logs |
| `./vendor/bin/directive {directive} [args]` | Exécute une directive |

### Options globales

| Option | Description |
|--------|-------------|
| `-h`, `--help` | Affiche l'aide |
| `-l`, `--list` | Liste les directives |
| `-v`, `--version` | Affiche la version |

### Codes de sortie

| Code | Label | Description |
|------|-------|-------------|
| `0` | SUCCESS | Exécution réussie |
| `1` | FAILURE | Échec général |
| `2` | INVALID_ARGUMENT | Argument invalide |
| `3` | NOT_FOUND | Directive non trouvée |
| `4` | PERMISSION_DENIED | Permission refusée |
| `5` | RUNTIME_ERROR | Erreur d'exécution |
| `6` | INVALID_SIGNATURE | Signature invalide |
| `7` | CONFLICT | Conflit (circularité) |
| `8` | DEPENDENCY_ERROR | Erreur de dépendance |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
