# Laravel Directive

**Un système de commandes CLI flexible pour Laravel qui se libère des contraintes d'Artisan. Directives introduit une séparation nette entre la logique métier et la présentation.**

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

- [Installation](#installation)
- [Configuration](#configuration)
- [Premiers pas](#premiers-pas)
- [Format des signatures](#format-des-signatures-de-directives)
- [Arguments variadiques](#arguments-variadiques)
- [Méthodes de base](#les-methodes-de-base)
- [Arguments et options](#arguments-et-options)
- [Interaction utilisateur](#interaction-utilisateur)
- [Charger Laravel optionnellement](#charger-laravel-optionnellement)
- [Système de composition (Call System)](#système-de-composition-call-system)
- [Commandes intégrées](#commandes-integrees)
- [Codes de sortie](#codes-de-sortie)
- [Tester vos directives](#tester-vos-directives)
- [Exemples complets](#exemples-complets)
- [Pourquoi ce package](#pourquoi-ce-package)

---

## Installation

```bash
composer require andydefer/laravel-directive
```

### Prérequis

- PHP 8.2 ou supérieur
- Laravel 10.x, 11.x ou 12.x

### Publication de la configuration (optionnel)

```bash
php artisan vendor:publish --tag=directive-config --force
```

---

## Configuration

```php
// config/directive.php
return [
    'path' => getcwd() . '/app/Directives',
];
```

| Variable d'environnement | Description | Défaut |
|--------------------------|-------------|--------|
| `DIRECTIVE_PATH` | Chemin personnalisé des directives | `./app/Directives` |
| `DIRECTIVE_DEBUG` | Mode debug | `false` |

---

## Premiers pas

### Lister les directives

```bash
./vendor/bin/directive --list
```

### Afficher l'aide

```bash
./vendor/bin/directive --help
```

### Créer votre première directive

```php
<?php
// app/Directives/HelloDirective.php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class HelloDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'hello {name?}';
    }

    public function getDescription(): string
    {
        return 'Say hello to someone';
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->line("Hello, {$name}!");
        
        return ExitCode::SUCCESS;
    }
}
```

### Exécuter

```bash
./vendor/bin/directive hello "John Doe"
# Sortie: Hello, John Doe!
```

---

## Format des signatures

### Règles fondamentales

| Règle | Explication |
|-------|-------------|
| **Délimiteur autorisé** | Uniquement `-` (tiret) |
| **Caractères autorisés** | Lettres (a-z, A-Z) et chiffres (0-9) |
| **Premier caractère** | Doit être une lettre |
| **Pas de tirets consécutifs** | `user--list` est interdit |
| **Pas de tiret final** | `user-` est interdit |

### ✅ Exemples valides

| Signature | Classe générée |
|-----------|----------------|
| `user-list` | `UserListDirective` |
| `cache-clear` | `CacheClearDirective` |
| `api-user-profile` | `ApiUserProfileDirective` |

### ❌ Exemples invalides

| Signature | Raison |
|-----------|--------|
| `user:list` | Caractère `:` interdit |
| `create_user` | Underscore `_` interdit |
| `user-` | Tiret final interdit |
| `user--list` | Tirets consécutifs |

### Ordre des paramètres (strict)

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| 1 | Arguments requis | `{name}` | `{name} {email}` |
| 2 | Arguments avec valeur par défaut | `{role=user}` | `{role=admin}` |
| 3 | Arguments optionnels | `{count?}` | `{limit?}` |
| 4 | Arguments variadiques | `{files*}` | `{files*}` |
| 5 | Options | `{--force}` ou `{-v}` | `{--verbose} {-f}` |

```php
// ✅ Ordre correct
public function getSignature(): string
{
    return 'user-create {name} {email} {role=user} {count?} {files*} {--force} {-v}';
}

// ❌ Ordre incorrect
public function getSignature(): string
{
    return 'user-create {name?} {email}'; // Requis après optionnel
}
```

---

## Arguments variadiques

Capture tous les arguments restants sous forme de tableau.

```php
public function getSignature(): string
{
    return 'process {name} {files*}';
}
```

### Syntaxe avec crochets (recommandée)

```bash
./directive process John [file1.txt, file2.txt, file3.txt] --verbose
```

### Exemple

```php
final class ProcessFilesDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {name} {files*} {--verbose}';
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $files = $this->getVariadicArguments();

        $this->info("Processing files for {$name}");

        foreach ($files as $file) {
            $this->line("  - {$file}");
        }

        return ExitCode::SUCCESS;
    }
}
```

| Méthode | Description |
|---------|-------------|
| `getVariadicArguments(): StringTypedCollection` | Retourne tous les arguments variadiques |
| `hasVariadicArguments(): bool` | Vérifie leur présence |

---

## Les méthodes de base

### `getSignature()`

```php
public function getSignature(): string
{
    return 'user-create {name} {email} {--role=admin}';
}
```

| Élément | Syntaxe | Description |
|---------|---------|-------------|
| Argument requis | `{name}` | Paramètre positionnel obligatoire |
| Argument optionnel | `{name?}` | Paramètre positionnel optionnel |
| Argument avec défaut | `{count=10}` | Valeur par défaut |
| Argument variadique | `{files*}` | Capture tous les restants |
| Option avec valeur | `{--role=}` | Option attend une valeur |
| Option flag | `{--force}` | true/false |

### `getDescription()`

```php
public function getDescription(): string
{
    return 'Create a new user account';
}
```

### `execute()`

> ⚠️ **Important :** `execute()` est maintenant **protégée**. Elle ne doit être appelée que via `run()`.

```php
protected function execute(): ExitCode
{
    $this->info('User created!');
    return ExitCode::SUCCESS;
}
```

### `getAliases()`

```php
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

public function getAliases(): StringTypedCollection
{
    $aliases = new StringTypedCollection();
    $aliases->add('user-add');
    $aliases->add('create-user');
    return $aliases;
}
```

### `run()`

Point d'entrée public de la directive. Appelle `execute()` et gère les appels en cascade.

```php
final public function run(): ExitCode
{
    return $this->execute();
}
```

---

## Arguments et options

### Accès aux arguments

```php
protected function execute(): ExitCode
{
    $name = $this->argument('name');   // string ou null
    $email = $this->argument('email');
    
    if ($name === null) {
        $this->error('Name is required');
        return ExitCode::INVALID_ARGUMENT;
    }
    
    $this->line("Name: {$name}");
    return ExitCode::SUCCESS;
}
```

### Accès aux arguments variadiques

```php
protected function execute(): ExitCode
{
    $files = $this->getVariadicArguments();
    
    if ($this->hasVariadicArguments()) {
        foreach ($files as $file) {
            $this->line("Processing: {$file}");
        }
    }
    
    return ExitCode::SUCCESS;
}
```

### Vérifier l'existence

```php
if ($this->hasArgument('count')) {
    $count = $this->argument('count');
}

if ($this->hasOption('verbose')) {
    $this->info('Verbose mode');
}
```

### Accès aux options

```php
protected function execute(): ExitCode
{
    $force = $this->option('force');   // bool
    $role = $this->option('role');     // string ou null
    
    if ($force) {
        $this->warn('Force mode enabled');
    }
    
    return ExitCode::SUCCESS;
}
```

---

## Interaction utilisateur

### Afficher des messages

```php
$this->line('Simple text');     // texte brut
$this->info('Success!');        // vert
$this->error('Error!');         // rouge
$this->warn('Warning!');        // jaune
$this->newLine();               // ligne vide
$this->separator();             // ligne de séparation (---)
```

### Poser une question

```php
$name = $this->ask('What is your name?');
```

### Demander une confirmation

```php
if ($this->confirm('Continue?')) {
    $this->info('Continuing...');
}
```

### Afficher un tableau

```php
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

$headers = new StringTypedCollection();
$headers->add('ID', 'Name', 'Email');

$rows = new RowCollection();
$row = new RowCollection();
$row->add(1, 'John Doe', 'john@example.com');
$rows->add($row);

$this->table($headers, $rows);
```

Sortie :
```
| ID | Name     | Email             |
|----|----------|-------------------|
| 1  | John Doe | john@example.com  |
```

---

## Charger Laravel optionnellement

Par défaut, les directives s'exécutent **sans Laravel** pour des performances optimales.

### Activer Laravel

```php
final class UserListDirective extends AbstractDirective
{
    public function shouldBootLaravel(): bool
    {
        return true;
    }
    
    protected function execute(): ExitCode
    {
        $users = User::all(); // Eloquent fonctionne !
        
        foreach ($users as $user) {
            $this->line("{$user->id}: {$user->name}");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

### Vérifier la disponibilité

```php
protected function execute(): ExitCode
{
    if (!$this->hasLaravel()) {
        $this->error('Laravel not available!');
        return ExitCode::FAILURE;
    }
    
    $this->info('Laravel is available!');
    return ExitCode::SUCCESS;
}
```

### Accéder à l'instance Laravel

```php
$app = $this->getLaravel();
$version = $app->version();
```

> Le bootstrap de Laravel se fait **une seule fois** par exécution.

---

## Système de composition (Call System)

Le système de composition permet à une directive d'appeler d'autres directives, créant ainsi des **directives orchestres** capables de composer des fonctionnalités complexes.

### Principe de fonctionnement

Une directive peut enregistrer des appels vers d'autres directives via la méthode `call()`. Ces appels sont exécutés **après** la fin de la directive parente, dans l'ordre d'enregistrement.

```php
protected function execute(): ExitCode
{
    // Appel avec un tableau (conversion automatique)
    $this->call(['fetch-user', [$userId]]);

    // Appel avec un objet DirectiveExecutionRecord
    $args = new StringTypedCollection();
    $args->add($userId);
    $this->call(new DirectiveExecutionRecord('process-user', $args));

    return ExitCode::SUCCESS;
}
```

### Méthodes du système de call

| Méthode | Description |
|---------|-------------|
| `call(array|DirectiveExecutionRecord $record)` | Enregistre un appel vers une directive (tableau ou objet) |
| `getCalls(): array` | Retourne la liste des appels enregistrés |

### Syntaxe des appels

**Avec un tableau :**
```php
// [signature, [arguments]]
$this->call(['user-list', ['--role=admin']]);
$this->call(['send-email', ['john@example.com', 'welcome']]);
```

**Avec un objet DirectiveExecutionRecord :**
```php
$args = new StringTypedCollection();
$args->add('--role=admin');
$record = new DirectiveExecutionRecord('user-list', $args);
$this->call($record);
```

### Exemple de directive orchestratrice

```php
final class UserOrchestratorDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-orchestrate {user-id}';
    }

    public function getDescription(): string
    {
        return 'Orchestrate multiple user operations';
    }

    protected function execute(): ExitCode
    {
        $userId = $this->argument('user-id');

        $this->info("🚀 Starting orchestration for user {$userId}");

        // Chaîne d'opérations
        $this->call(['fetch-user', [$userId]]);
        $this->call(['process-user', [$userId]]);
        $this->call(['send-notification', [$userId]]);
        $this->call(['log-activity', ['user_processed', $userId]]);

        $this->info("✅ Orchestration completed");

        return ExitCode::SUCCESS;
    }
}
```

### Cycle de vie des appels

```
1. Exécution de la directive parente
   └── execute() s'exécute et enregistre les appels via call()

2. Fin de la directive parente
   └── Tous les appels sont exécutés dans l'ordre

3. Exécution récursive
   └── Chaque appel peut lui-même appeler d'autres directives
```

### Gestion des erreurs

Le système gère les erreurs de manière robuste :

- ✅ Chaque appel est indépendant : un échec n'arrête pas les autres appels
- ✅ L'erreur spécifique de l'enfant est affichée
- ✅ L'erreur globale du parent est également affichée
- ✅ Le code de retour final est `FAILURE` si au moins un appel a échoué

```php
// Exemple de sortie en cas d'erreur
❌ Child directive 'failing' failed
❌ Directive execution failed
```

### Avantages du système de call

| Avantage | Description |
|----------|-------------|
| **Réutilisabilité** | Composer des fonctionnalités sans duplication de code |
| **Orchestration** | Créer des workflows complexes et séquentiels |
| **Modularité** | Chaque directive reste simple et focalisée |
| **Testabilité** | Les appels peuvent être inspectés via `getCalls()` |
| **Lisibilité** | Le flux d'exécution est clair et explicite |
| **Robustesse** | Les erreurs sont gérées proprement |

---

## ⚠️ Important : Constructeur final

Le constructeur de `AbstractDirective` est **final**. Vous ne pouvez pas le surcharger.

```php
final public function __construct(
    protected DirectiveContext $context,
    protected DirectiveInteractionService $interaction
) {}
```

**Pourquoi ?**

- Empêche l'utilisateur de modifier l'injection de dépendances
- Garantit que le contexte et l'interaction sont correctement initialisés
- Force l'utilisation de `getLaravel()` pour accéder aux services

**Comment injecter des dépendances ?**

Utilisez `getLaravel()` pour accéder au conteneur :

```php
protected function execute(): ExitCode
{
    $app = $this->getLaravel();
    $service = $app->make(MyService::class);
    $config = $app->make('config');
    
    // Utiliser le service...
    return ExitCode::SUCCESS;
}
```

---

## Commandes intégrées

| Commande | Alias | Description |
|----------|-------|-------------|
| `./vendor/bin/directive --list` | `-l` | Liste toutes les directives |
| `./vendor/bin/directive --help` | `-h` | Affiche l'aide |
| `./vendor/bin/directive --version` | `-v` | Affiche la version |

---

## Codes de sortie

| Code | Constante | Description |
|------|-----------|-------------|
| 0 | `ExitCode::SUCCESS` | Succès |
| 1 | `ExitCode::FAILURE` | Erreur générale |
| 3 | `ExitCode::NOT_FOUND` | Directive non trouvée |
| 4 | `ExitCode::INVALID_ARGUMENT` | Argument invalide |

```php
protected function execute(): ExitCode
{
    if ($this->argument('name') === null) {
        $this->error('Name is required');
        return ExitCode::INVALID_ARGUMENT;
    }
    
    try {
        // Logique...
        return ExitCode::SUCCESS;
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return ExitCode::FAILURE;
    }
}
```

---

## Tester vos directives

Le package fournit `DirectiveTestingService` pour tester vos directives dans un environnement isolé.

### Test basique

```php
<?php
namespace Tests\Unit\Directives;

use AndyDefer\Directive\Contexts\DirectiveContext;
use AndyDefer\Directive\Contexts\LaravelBootstrapperContext;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
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
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_directive_returns_success(): void
    {
        $context = new DirectiveContext(
            laravelBootstrapper: new LaravelBootstrapperContext(),
            blueprint: new DirectiveBlueprintRecord(HelloDirective::class, 'hello', 'Say hello'),
            aliases: new StringTypedCollection(),
            shouldBootLaravel: false
        );
        
        $directive = new HelloDirective($context, $this->service->getInteraction());
        $this->service->registerDirective($directive);
        
        $response = $this->service->runDirective('hello', ['John']);
        
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }
}
```

### Tester une directive avec appels

```php
public function test_orchestrator_calls_child_directives(): void
{
    $service = new DirectiveTestingService();
    $service->registerDirective(FetchUserDirective::class);
    $service->registerDirective(ProcessUserDirective::class);
    
    $response = $service->runDirective('user-orchestrate', ['123']);
    
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Starting orchestration', $response->output);
    
    $calls = $service->getCalls();
    $this->assertCount(3, $calls);
}
```

### Directive temporaire avec closure

```php
public function test_temporary_directive(): void
{
    $executed = false;
    
    $this->service->createTestDirective('test-closure', function ($d) use (&$executed) {
        $executed = true;
        $d->line('Executed!');
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('test-closure');
    
    $this->assertTrue($executed);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
}
```

### Test avec Laravel

```php
protected function setUp(): void
{
    parent::setUp();
    $config = new DirectiveTestingConfig();
    $context = new DirectiveTestingContext(bootLaravel: true);
    $context->setConfig($config);
    $this->service = new DirectiveTestingService($context);
}
```

### Méthodes du service

| Méthode | Description |
|---------|-------------|
| `registerDirective(AbstractDirective $directive)` | Enregistre une directive |
| `registerDirectives(array $directives)` | Enregistre plusieurs directives |
| `clearRegisteredDirectives()` | Supprime toutes les directives |
| `createTestDirective(string $signature, callable $execute)` | Crée une directive temporaire |
| `runDirective(string $signature, array $arguments = [])` | Exécute une directive |
| `getCalls(): array` | Récupère les appels enregistrés |

> `createTestDirective()` crée automatiquement le `DirectiveContext` nécessaire, vous n'avez pas à le gérer manuellement.

---

## Exemples complets

### Directive de backup avec arguments variadiques

```php
<?php
namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class BackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'backup {source} {destination} {excludes*} {--compress} {--format=zip}';
    }

    public function getDescription(): string
    {
        return 'Backup files and directories';
    }

    protected function execute(): ExitCode
    {
        $source = $this->argument('source');
        $destination = $this->argument('destination');
        $excludes = $this->getVariadicArguments();
        $compress = $this->option('compress');
        $format = $this->option('format') ?? 'zip';

        $this->info("Backup from {$source} to {$destination}");
        
        if ($compress) {
            $this->info("Compression enabled");
        }
        
        $this->info("Format: {$format}");
        
        if ($excludes->isNotEmpty()) {
            $this->info("Excluding: " . implode(', ', $excludes->toArray()));
        }

        return ExitCode::SUCCESS;
    }
}

// Usage: ./directive backup /var/www /backup [node_modules, .git, cache] --compress
```

### Directive avec base de données (Laravel)

```php
<?php
namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
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

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    protected function execute(): ExitCode
    {
        if (!$this->hasLaravel()) {
            return ExitCode::FAILURE;
        }

        $total = User::count();
        $this->info("Total users: {$total}");
        
        $query = User::query();
        if ($this->option('active')) {
            $query->where('is_active', true);
        }
        
        $headers = new StringTypedCollection();
        $headers->add('ID', 'Name', 'Email');
        
        $rows = new RowCollection();
        foreach ($query->get() as $user) {
            $row = new RowCollection();
            $row->add($user->id, $user->name, $user->email);
            $rows->add($row);
        }
        
        $this->table($headers, $rows);
        
        return ExitCode::SUCCESS;
    }
}
```

### Directive orchestratrice (Call System)

```php
<?php
namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class UserOrchestratorDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-orchestrate {user-id} {--notify}';
    }

    public function getDescription(): string
    {
        return 'Orchestrate complete user workflow';
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    protected function execute(): ExitCode
    {
        $userId = $this->argument('user-id');
        $notify = $this->option('notify');

        $this->info("🚀 Starting user orchestration for ID: {$userId}");
        $this->separator('=', 50);

        // Vérifier l'utilisateur via Laravel
        $app = $this->getLaravel();
        $user = $app->make(UserRepository::class)->find($userId);
        
        if (!$user) {
            $this->error("❌ User {$userId} not found");
            return ExitCode::FAILURE;
        }

        $this->info("✅ User found: {$user->name}");

        // Chaîne d'opérations
        $this->call(['fetch-user-details', [$userId]]);
        $this->call(['process-user-data', [$userId]]);
        $this->call(['update-user-stats', [$userId]]);

        if ($notify) {
            $this->call(['send-user-notification', [$userId, 'processed']]);
        }

        $this->call(['log-activity', ['user_orchestrated', $userId]]);

        $this->separator('=', 50);
        $this->info('✅ Orchestration completed successfully!');

        return ExitCode::SUCCESS;
    }
}
```

### Directive interactive

```php
<?php
namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class SetupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'app-setup';
    }

    public function getDescription(): string
    {
        return 'Interactive setup wizard';
    }

    protected function execute(): ExitCode
    {
        $this->info('Welcome to the setup wizard!');
        
        $appName = $this->ask('Application name');
        $env = $this->ask('Environment (local/production)');
        
        if (!$this->confirm("Create config for {$appName} in {$env}?")) {
            $this->warn('Cancelled');
            return ExitCode::SUCCESS;
        }
        
        $this->info("Configuration created!");
        return ExitCode::SUCCESS;
    }
}
```

---

## Pourquoi ce package ?

### Limitations d'Artisan

| Problème | Solution avec Directives |
|----------|--------------------------|
| Héritage unique obligatoire | Pas de contrainte |
| Logique et présentation mélangées | Séparation claire |
| Tests difficiles (`ask()` impossible à mocker) | Services mockables |
| Pas d'extensibilité pour les packages | Découverte automatique |
| Arguments non typés | Accès typé |
| **Pas d'arguments variadiques** | **Support des variadiques** |
| **Pas de composition** | **Système de call pour orchestrer** |
| Couplage fort | Architecture propre |

### Avantages

- ✅ **Séparation des responsabilités** : Logique métier découplée
- ✅ **Testabilité exceptionnelle** : Chaque directive est mockable
- ✅ **Extensibilité** : Découverte automatique dans `vendor/*/src/Directives/`
- ✅ **Laravel à la demande** : Bootstrap optionnel
- ✅ **Validation stricte** : Format et ordre des signatures
- ✅ **Typage fort** : Arguments et options typés
- ✅ **Arguments variadiques** : Capture avec `{files*}`
- ✅ **Système de composition** : Appel d'autres directives via `call()`
- ✅ **Constructeur final** : Injection sécurisée via `getLaravel()`
- ✅ **Découverte automatique** : Aucune configuration requise

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```