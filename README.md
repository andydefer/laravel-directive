# Laravel Directive

**Un framework CLI pour Laravel. Orchestration de pipelines, contexte partagé, découverte automatique, appels internes - avec ou sans Laravel.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Philosophie : Créez votre propre CLI](#philosophie--créez-votre-propre-cli)
3. [Première directive](#première-directive)
4. [Signature des commandes](#signature-des-commandes)
   - [Structure de la signature](#structure-de-la-signature)
   - [Arguments requis](#arguments-requis)
   - [Arguments par défaut](#arguments-par-défaut)
   - [Arguments nullables](#arguments-nullables)
   - [Arguments variadiques](#arguments-variadiques)
   - [Flags](#flags)
   - [Énumérations](#énumérations)
   - [Commentaires inline](#commentaires-inline)
   - [Ordre strict des éléments](#ordre-strict-des-éléments)
   - [Formatage des espaces avec `^`](#formatage-des-espaces-avec-)
   - [Tokens spéciaux](#tokens-spéciaux)
   - [Tags personnalisés](#tags-personnalisés)
5. [Accès aux arguments et options](#accès-aux-arguments-et-options)
6. [Contexte partagé](#contexte-partagé)
7. [Appels internes (call)](#appels-internes-call)
8. [Découverte automatique](#découverte-automatique)
9. [Journalisation JSONL](#journalisation-jsonl)
10. [Suggestions de commandes](#suggestions-de-commandes)
11. [Mode verbose et débogage](#mode-verbose-et-débogage)
12. [Directives intégrées](#directives-intégrées)
13. [Exécution hors CLI](#exécution-hors-cli)
14. [Mode autonome (sans Laravel)](#mode-autonome-sans-laravel)
15. [Tests des directives](#tests-des-directives)
16. [Cas d'usage concrets](#cas-dusage-concrets)
17. [Bonnes pratiques](#bonnes-pratiques)
18. [Référence des codes de sortie](#référence-des-codes-de-sortie)

---

## Installation

```bash
composer require andydefer/laravel-directive
```

**Prérequis :** PHP 8.1+ | Laravel 12.x, 13.x, 14.x ou 15.x

---

## Philosophie : Créez votre propre CLI

**Ce package n'est pas un simple binaire préfabriqué. C'est un framework pour construire votre propre système CLI adapté à vos besoins métier.**

Le binaire fourni (`vendor/bin/directive`) est un **exemple de démonstration**, pas une solution finale.

### Pourquoi créer votre propre CLI ?

✅ **Contrôle total** - Vous décidez des commandes, des providers, des sources
✅ **Logique métier** - Intégration parfaite avec votre domaine applicatif
✅ **Providers personnalisés** - Enregistrez vos propres services et dépendances
✅ **Performance** - Chargez uniquement ce dont vous avez besoin
✅ **Sécurité** - Contrôle d'accès granulaire
✅ **Évolutivité** - Facile à étendre avec votre code

### Créer votre point d'entrée

```php
#!/usr/bin/env php
<?php

// bin/my-app
require_once __DIR__ . '/vendor/autoload.php';

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use App\Providers\MyAppServiceProvider;

// ✅ Personnalisation complète
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class,
    MyAppServiceProvider::class,      // Vos providers
])->withConfig([
    'app.name' => 'My Application CLI',
    'app.debug' => getenv('APP_DEBUG') === 'true',
])->build();

$kernel = $app->make(DirectiveKernel::class);

// ✅ Ajouter vos sources
$kernel->addSource(__DIR__ . '/src/Directives');
$kernel->addSource(__DIR__ . '/modules/*/Directives');

// ✅ Configuration
$kernel->verbose(getenv('VERBOSE') === 'true')
    ->setLogBasePath('/var/log/my-app');

$exitCode = $kernel->run($argv);
exit($exitCode->value);
```

```bash
chmod +x bin/my-app
./bin/my-app deploy staging
```

---

## Première directive

### 1. Créer la classe

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

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
        if ($this->getFlag('formal')) {
            $this->info('Formal mode enabled');
        }
    }

    protected function execute(): ExitCode
    {
        $name = $this->getArgument('name');
        $formal = $this->getFlag('formal');

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

### 2. Exécuter avec votre CLI

```bash
# Avec votre propre CLI
./bin/my-app greet John
# Hello, John

# Avec option
./bin/my-app greet John --formal
# Formal mode enabled
# Good day, John

# Avec alias
./bin/my-app hello John
# Hello, John
```

---

## Signature des commandes

La signature est la clé de voûte du système. Elle définit la structure complète d'une commande : son nom, ses arguments, ses flags, ses énumérations et plus encore.

Le parseur de signature est basé sur `php-signature-parser` et supporte une syntaxe riche et expressive.

### Structure de la signature

```php
public function getSignature(): string
{
    return 'backup {source} {destination} {format=zip} {env=?} ::level->[low,medium,high]=medium {excludes*} {purpose*} {--force} {--verbose}';
}
```

### Arguments requis

Les arguments requis sont obligatoires. La commande échoue si l'utilisateur ne les fournit pas.

```php
public function getSignature(): string
{
    return 'backup {source} {destination}';
}
```

### Arguments par défaut

Les arguments par défaut fournissent une valeur si l'utilisateur n'en spécifie pas.

```php
public function getSignature(): string
{
    return 'backup {source} {destination} {format=zip} {compression=9} {output=dist}';
}
```

### Arguments nullables

Les arguments nullables peuvent recevoir la valeur `null` explicitement.

```php
public function getSignature(): string
{
    return 'deploy {environment} {version=?} {config=?}';
}
```

### Arguments variadiques

Les arguments variadiques capturent zéro, une ou plusieurs valeurs.

```php
public function getSignature(): string
{
    return 'delete {files*} {directories*} {--force}';
}
```

### Flags

Les flags sont des options booléennes. Présents = `true`, absents = `false`.

```php
public function getSignature(): string
{
    return 'deploy {environment} {--force} {--verbose} {--dry-run}';
}
```

### Énumérations

Les énumérations restreignent les valeurs autorisées.

#### Syntaxe

```php
::name->[value1,value2,value3]=state
```

#### États possibles

| État | Syntaxe | Description |
|------|---------|-------------|
| **Requis** | `=*` | Doit être fourni |
| **Optionnel** | `=?` | Peut être `~` (skip) |
| **Défaut** | `=default` | Valeur par défaut |

#### Exemples

```php
// Avec valeur par défaut
$signature = 'set-level ::level->[beginner,middle,master]=medium';

// Requis
$signature = 'set-level ::level->[beginner,middle,master]=*';

// Optionnel
$signature = 'set-level ::level->[beginner,middle,master]=?';

// Avec commentaire
$signature = 'set-level ::level->[beginner,middle,master]=medium#"The skill level"';
```

#### Accès aux énumérations

```php
// Dans une directive
$level = $this->getEnum('level');

// Vérifications
$allowed = $this->getEnumAllowedValues('level');
$isRequired = $this->isEnumRequired('level');
$isAllowed = $this->isEnumValueAllowed('level', 'master');
```

### Commentaires inline

Les commentaires documentent chaque argument directement dans la signature.

```php
public function getSignature(): string
{
    return 'backup {source}#"Source directory" {destination}#"Destination" {format=zip}#"Archive format" {--force}#"Force overwrite"';
}
```

### Ordre strict des éléments

⚠️ **L'ordre des éléments dans la signature est STRICT et IMPÉRATIF.**

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| **1** | **Source** | `command` | `backup` |
| **2** | **Requis** | `{name}` | `{source}` `{destination}` |
| **3** | **Par défaut** | `{name=value}` | `{format=zip}` `{output=dist}` |
| **4** | **Nullable** | `{name=?}` | `{env=?}` `{port=?}` |
| **5** | **Enum** | `::name->[values]=state` | `::level->[low,high]=medium` |
| **6** | **Variadique** | `{name*}` | `{excludes*}` `{purpose*}` |
| **7** | **Flags** | `{--flag}` | `{--force}` `{--verbose}` |
| **8** | **Tags personnalisés** | `<key="value">` | `<user="admin">` |

#### Exemples d'ordre valide

```php
// ✅ Ordre correct avec tous les types
'backup {source} {destination} {format=zip} {env=?} ::level->[low,high]=medium {excludes*} {--force}'

// ✅ Commentaires à n'importe quelle position
'backup {source}#"Source" {destination} {--force}#"Force"'
```

#### Exemples d'ordre invalide

```php
// ❌ Enum après variadic
'backup {source} {excludes*} ::level->[low,high]=medium'

// ❌ Required après default
'backup {format=zip} {source}'
```

### Formatage des espaces avec `^`

Le parseur remplace automatiquement les caractères `^` par des espaces.

| Saisie utilisateur | Valeur réelle |
|-------------------|---------------|
| `John^Doe` | `John Doe` |
| `Hello^World!` | `Hello World!` |
| `C:/Program^Files` | `C:/Program Files` |

```bash
./bin/my-app user:create John^Doe john@example.com
# name = "John Doe"
```

### Tokens spéciaux

#### Le token `?` (null explicite)

Permet de passer explicitement `null` comme valeur.

```bash
# env = null
./bin/my-app deploy staging ?
```

#### Le token `~` (skip)

Permet de sauter un argument.

| Cas | Comportement |
|-----|--------------|
| **Argument requis** | `~` → `null` |
| **Argument par défaut** | `~` → utilise la valeur par défaut |
| **Argument nullable** | `~` → `null` |
| **Enum avec défaut** | `~` → utilise la valeur par défaut |
| **Enum optionnel** | `~` → `null` |

```bash
# format utilise la valeur par défaut (zip)
./bin/my-app backup /var/www ~

# level utilise la valeur par défaut (medium)
./bin/my-app set-level ~
```

### Tags personnalisés

Les tags permettent d'ajouter des données supplémentaires sans modifier la signature.

```bash
./bin/my-app send John --verbose <greeting="Hello World"> <later="goodby">
```

```php
// Dans la directive
$customData = $this->getCustomData();
$greeting = $customData['greeting'] ?? 'Default greeting';
```

---

## Accès aux arguments et options

### Méthodes principales

```php
protected function execute(): ExitCode
{
    // Arguments requis
    $name = $this->getRequired('name');
    $email = $this->getRequired('email');

    // Tous les arguments requis
    $requireds = $this->getRequireds();

    // Arguments par défaut
    $format = $this->getDefault('format');

    // Tous les arguments par défaut
    $defaults = $this->getDefaults();

    // Arguments nullables
    $version = $this->getArgument('version');

    // Arguments variadiques
    $files = $this->getVariadic('files');

    // Tous les arguments variadiques
    $variadics = $this->getVariadics();

    // Collection plate de toutes les valeurs variadiques
    $allValues = $this->getVariadicArguments();

    // Flags
    $force = $this->getFlag('force');
    $verbose = $this->getFlag('verbose');

    // Tous les flags
    $flags = $this->getFlags();

    // Flags actifs
    $active = $this->getActiveFlags();

    // Énumérations
    $level = $this->getEnum('level');

    // Toutes les énumérations
    $enums = $this->getEnums();

    // Valeurs autorisées pour une enum
    $allowed = $this->getEnumAllowedValues('level');

    // Vérifications
    if ($this->hasArgument('email')) { /* ... */ }
    if ($this->hasFlag('force')) { /* ... */ }
    if ($this->hasRequireds()) { /* ... */ }
    if ($this->hasDefaults()) { /* ... */ }
    if ($this->hasEnums()) { /* ... */ }
    if ($this->hasFlags()) { /* ... */ }
    if ($this->hasVariadicArguments()) { /* ... */ }

    return ExitCode::SUCCESS;
}
```

### Recherche d'arguments avec `getArgument()`

`getArgument()` recherche dans l'ordre de priorité :

1. Arguments requis
2. Arguments par défaut
3. Énumérations
4. Arguments variadiques (retourne un tableau)
5. Flags (retourne un booléen)

```php
// Peut retourner un string, un booléen ou un array
$value = $this->getArgument('key');

if (is_array($value)) {
    // C'est un variadic
} elseif (is_bool($value)) {
    // C'est un flag
} else {
    // C'est un argument (requis, default, nullable, enum)
}
```

---

## Contexte partagé

Le contexte est un `MapCollection` mutable accessible par toutes les directives.

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
        $file = $this->getArgument('file');

        $this->contextSet('file', $file);
        $this->call('data:load');
        $this->call('data:clean');
        $this->call('data:transform');

        $stats = $this->contextGet('processing_stats');
        $this->info("📊 Processed {$stats['total']} records");

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
$env = $this->getArgument('environment');
$this->call("deploy:validate $env");

// Multiple appels
$this->call('task:one');
$this->call('task:two');
$this->call('task:three');

// Avec parsing du contexte
$this->call("greet {$name} --formal");
```

### Détection de circularité

Laravel Directive détecte automatiquement les appels circulaires :

```php
class CircularDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'circular';
    }

    protected function execute(): ExitCode
    {
        $this->call('circular'); // ⚠️ Détecté automatiquement
        return ExitCode::SUCCESS;
    }
}
```

### Pipeline de déploiement

```php
class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--skip-tests} {--force}';
    }

    protected function execute(): ExitCode
    {
        $env = $this->getArgument('environment');
        $skipTests = $this->getFlag('skip-tests');
        $force = $this->getFlag('force');

        $this->call("deploy:validate $env");
        $this->call("deploy:backup $env");

        if (!$skipTests) {
            $this->call('deploy:build --with-tests');
        } else {
            $this->call('deploy:build');
        }

        $forceFlag = $force ? '--force' : '';
        $this->call("deploy:migrate $env $forceFlag");

        $this->call("deploy:activate $env");
        $this->call('deploy:post --notify');
        $this->call('deploy:cleanup --keep-last=3');

        return ExitCode::SUCCESS;
    }
}
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

### Filtrer la découverte

```php
$kernel = $app->make(DirectiveKernel::class);

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
```

### Système de problèmes

Le système de découverte collecte automatiquement les problèmes :

```php
$kernel->discover();
$problems = $kernel->getProblems();

foreach ($problems as $problem) {
    echo $problem->get('key') . ': ' . $problem->get('message');
}
```

---

## Journalisation JSONL

Chaque exécution est automatiquement journalisée au format JSONL.

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
      "deployment_start": 1700000000
    }
  }
}
```

### Configuration

```php
$kernel->setLogBasePath('/var/log/directive');
```

---

## Suggestions de commandes

Laravel Directive utilise un BK-tree (distance de Levenshtein) pour suggérer des commandes similaires.

```bash
$ ./bin/my-app depoy
Directive not found: depoy

💡 Did you mean:
  • deploy
  • deploy:validate
  • deploy:backup
```

Les alias sont également pris en compte.

---

## Mode verbose et débogage

Le mode verbose affiche automatiquement les problèmes rencontrés.

### Activation

```bash
./bin/my-app deploy staging --verbose
```

```php
$kernel->verbose(true);
```

### Audit du noyau

```bash
./bin/my-app kernel:audit
./bin/my-app kernel:audit --verbose
```

---

## Directives intégrées

| Directive | Description | Alias |
|-----------|-------------|-------|
| `help` | Affiche l'aide | `-h`, `--help` |
| `list` | Liste toutes les directives | `ls`, `-l`, `--list` |
| `version` | Affiche la version | `-v`, `--version` |
| `clean-logs [days]` | Nettoie les logs | - |
| `kernel:audit` | Audit du noyau | `audit` |

---

## Exécution hors CLI

Les directives peuvent être exécutées dans n'importe quel contexte PHP.

### Dans un contrôleur Laravel

```php
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

class TaskController extends Controller
{
    public function run(DirectiveKernel $kernel)
    {
        $exitCode = $kernel->runSignature('process:data --format=json');

        if ($exitCode === ExitCode::SUCCESS) {
            return response()->json(['message' => 'Task completed']);
        }

        return response()->json(['error' => 'Task failed'], 500);
    }
}
```

### Dans un job Laravel

```php
class ProcessDataJob implements ShouldQueue
{
    public function handle(DirectiveKernel $kernel): void
    {
        $kernel->runSignature('process:batch --limit=1000');
    }
}
```

### Dans un service métier

```php
class ReportService
{
    public function __construct(private DirectiveKernel $kernel) {}

    public function generateDailyReport(): array
    {
        $this->kernel->runSignature('report:fetch');
        $this->kernel->runSignature('report:process --format=pdf');
        $this->kernel->runSignature('report:send --email=admin@example.com');

        return ['status' => 'completed'];
    }
}
```

---

## Mode autonome (sans Laravel)

### Script d'entrée

```php
#!/usr/bin/env php
<?php

// bin/standalone
require_once __DIR__ . '/vendor/autoload.php';

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;

$app = ApplicationBuilder::external([
    DirectiveServiceProvider::class,
])->withConfig([
    'app.name' => 'Standalone CLI',
])->build();

$kernel = $app->make(DirectiveKernel::class);
$kernel->addSource(__DIR__ . '/src/Directives');

exit($kernel->run($argv)->value);
```

---

## Tests des directives

`DirectiveTestingService` permet de tester les directives en isolation.

```php
<?php

namespace Tests\Directives;

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Enums\ExitCode;
use PHPUnit\Framework\TestCase;

class GreetDirectiveTest extends TestCase
{
    private DirectiveTestingService $testing;

    protected function setUp(): void
    {
        parent::setUp();

        $app = ApplicationBuilder::internal([
            DirectiveServiceProvider::class,
        ])->build();

        $this->testing = new DirectiveTestingService(
            $app,
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

    public function test_unknown_command_returns_problems(): void
    {
        $response = $this->testing->run('unknown-command');

        $this->assertSame(ExitCode::NOT_FOUND, $response->exit_code);
        $this->assertFalse($response->problems->isEmpty());
    }
}
```

---

## Cas d'usage concrets

### Pipeline de déploiement

```php
class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--skip-tests} {--force}';
    }

    protected function execute(): ExitCode
    {
        $env = $this->getArgument('environment');

        $this->call("deploy:validate $env");
        $this->call("deploy:backup $env");
        $this->call('deploy:build' . ($this->getFlag('skip-tests') ? ' --skip-tests' : ''));
        $this->call("deploy:migrate $env" . ($this->getFlag('force') ? ' --force' : ''));
        $this->call("deploy:activate $env");
        $this->call('deploy:post --notify');

        return ExitCode::SUCCESS;
    }
}
```

### Data processing pipeline

```php
class ProcessDataDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'data:process {file} {--format=csv} {--dry-run}';
    }

    protected function execute(): ExitCode
    {
        $file = $this->getArgument('file');

        $this->call("data:load $file");
        $this->call('data:clean');
        $this->call('data:transform --aggressive');
        $this->call('data:validate');

        if (!$this->getFlag('dry-run')) {
            $format = $this->getArgument('format');
            $this->call("data:export --format=$format");
        }

        $stats = $this->contextGet('processing_stats');
        $this->info("📊 Processed {$stats['total']} records");

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
    $name = $this->getArgument('name');

    if (empty($name) || strlen($name) < 3) {
        $this->error('Name must be at least 3 characters');
        throw new \RuntimeException('Invalid name');
    }
}
```

### ✅ Utiliser le contexte pour les données partagées

```php
// BON
$this->contextSet('user_id', $user->id);
$userId = $this->contextGet('user_id');

// ÉVITER
global $userId;
$userId = 42;
```

### ✅ Hooks before/after

```php
protected function beforeExecute(): void
{
    $this->contextSet('start_time', microtime(true));
    $this->info('Starting...');
}

protected function afterExecute(ExitCode $exitCode): void
{
    $duration = microtime(true) - $this->contextGet('start_time');
    $this->info("Completed in {$duration}s");
}
```

### ✅ Créer son propre CLI

```php
#!/usr/bin/env php
<?php

// bin/my-app
require_once __DIR__ . '/vendor/autoload.php';

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use App\Providers\AppServiceProvider;

$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class,
    AppServiceProvider::class,
])->build();

$kernel = $app->make(DirectiveKernel::class);
$kernel->addSource(__DIR__ . '/src/Directives');

exit($kernel->run($argv)->value);
```

---

## Référence des codes de sortie

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