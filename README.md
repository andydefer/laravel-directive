# Laravel Directive

**Un système de commandes CLI flexible pour Laravel qui se libère des contraintes d'Artisan. Directives introduit une séparation nette entre la logique métier et la présentation.**

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Installation

```bash
composer require andydefer/laravel-directive
```

### Prérequis

- PHP 8.2 ou supérieur
- Laravel 10.x, 11.x ou 12.x
- Dépend automatiquement de `andydefer/php-records`

### Publication de la configuration (optionnel)

```bash
php artisan vendor:publish --tag=directive-config --force
```

---

## Configuration

### Fichier de configuration

```php
// config/directive.php
return [
    'path' => getcwd() . '/app/Directives',
];
```

---

## Premiers pas

### Lister les directives disponibles

```bash
./vendor/bin/directive --list
```

### Afficher l'aide

```bash
./vendor/bin/directive --help
```

### Afficher la version

```bash
./vendor/bin/directive --version
```

### Créer votre première directive

Créez manuellement le fichier `app/Directives/HelloDirective.php` :

```php
<?php

declare(strict_types=1);

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

    public function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->line("Hello, {$name}!");
        
        return ExitCode::SUCCESS;
    }
}
```

### Exécuter votre directive

```bash
./vendor/bin/directive hello "John Doe"
```

Sortie :
```
Hello, John Doe!
```

---

## Format des signatures de directives

### Règles fondamentales

| Règle | Explication |
|-------|-------------|
| **Délimiteur autorisé** | Uniquement `-` (tiret) |
| **Caractères autorisés** | Lettres (a-z, A-Z) et chiffres (0-9) |
| **Premier caractère** | Doit être une lettre |
| **Pas de tirets consécutifs** | `user--list` est interdit |
| **Pas de tiret final** | `user-` est interdit |

### ✅ Exemples valides

| Signature | Nom de classe généré |
|-----------|---------------------|
| `user-list` | `UserListDirective` |
| `cache-clear` | `CacheClearDirective` |
| `api-user-profile` | `ApiUserProfileDirective` |
| `api-v2` | `ApiV2Directive` |

### ❌ Exemples invalides

| Signature | Raison |
|-----------|--------|
| `user:list` | Caractère `:` interdit |
| `user@list` | Caractère `@` interdit |
| `create_user` | Underscore `_` interdit |
| `user-` | Tiret final interdit |
| `user--list` | Tirets consécutifs |

### Ordre des paramètres

Le parser impose un ordre strict :

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| 1 | Arguments requis | `{name}` | `{name} {email}` |
| 2 | Arguments avec valeur par défaut | `{role=user}` | `{role=admin}` |
| 3 | Arguments optionnels | `{count?}` | `{limit?}` |
| 4 | Arguments variadiques | `{files*}` | `{files*} {tags*}` |
| 5 | Options | `{--force}` ou `{-v}` | `{--verbose} {-f}` |

```php
// ✅ Ordre correct
public function getSignature(): string
{
    return 'user:create {name} {email} {role=user} {count?} {files*} {--force} {-v}';
}

// ❌ Ordre incorrect
public function getSignature(): string
{
    return 'user:create {name?} {email}'; // Requis après optionnel
}
```

---

## Arguments variadiques (Nouveauté !)

> **⚠️ Nouveauté depuis la version 3.8.0** - Support des arguments variadiques avec syntaxe `{files*}`.

### Syntaxe

Les arguments variadiques capturent **tous les arguments restants** sous forme de tableau.

```php
public function getSignature(): string
{
    return 'process {name} {files*}';
}
```

### Syntaxe recommandée avec crochets

Pour une meilleure lisibilité, utilisez la syntaxe avec crochets :

```bash
# Sans crochets (mais moins lisible)
./directive process John file1.txt file2.txt file3.txt

# Avec crochets (recommandé)
./directive process John [file1.txt, file2.txt, file3.txt]
```

### Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class ProcessFilesDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {name} {files*} {--verbose}';
    }

    public function getDescription(): string
    {
        return 'Process multiple files';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $files = $this->getVariadicArguments(); // StringTypedCollection

        $this->info("Processing files for {$name}");

        foreach ($files as $file) {
            $this->line("  - {$file}");
            if ($this->option('verbose')) {
                $this->info("    Done");
            }
        }

        return ExitCode::SUCCESS;
    }
}
```

### Méthodes pour les arguments variadiques

| Méthode | Description |
|---------|-------------|
| `getVariadicArguments(): StringTypedCollection` | Retourne tous les arguments variadiques |
| `hasVariadicArguments(): bool` | Vérifie si des arguments variadiques sont présents |

---

## Les méthodes de base

### `getSignature()` - La signature

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
| Argument avec valeur par défaut | `{count=10}` | Valeur par défaut |
| Argument variadique | `{files*}` | Capture tous les arguments restants (Nouveau !) |
| Option avec valeur | `{--role=}` | Option avec valeur |
| Option flag | `{--force}` | Option sans valeur (true/false) |

### `getDescription()` - La description

```php
public function getDescription(): string
{
    return 'Create a new user account';
}
```

### `execute()` - La logique métier

```php
public function execute(): ExitCode
{
    $this->info('User created successfully!');
    return ExitCode::SUCCESS;
}
```

### `getAliases()` - Les alias

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

---

## Arguments et options

### Accès aux arguments

```php
public function execute(): ExitCode
{
    $name = $this->argument('name');   // string ou null
    $email = $this->argument('email'); // null si absent
    
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
public function execute(): ExitCode
{
    $files = $this->getVariadicArguments(); // StringTypedCollection
    
    if ($this->hasVariadicArguments()) {
        foreach ($files as $file) {
            $this->line("Processing: {$file}");
        }
    }
    
    return ExitCode::SUCCESS;
}
```

### Vérifier l'existence d'un argument

```php
if ($this->hasArgument('count')) {
    $count = $this->argument('count');
    $this->info("Count: {$count}");
}
```

### Accès aux options

```php
public function execute(): ExitCode
{
    $force = $this->option('force'); // bool (true si présent)
    $role = $this->option('role');   // string ou null
    
    if ($force) {
        $this->warn('Force mode enabled');
    }
    
    if ($role !== null) {
        $this->info("Role: {$role}");
    }
    
    return ExitCode::SUCCESS;
}
```

### Vérifier l'existence d'une option

```php
if ($this->hasOption('verbose')) {
    $this->info('Verbose mode enabled');
}
```

---

## Interaction utilisateur

### Afficher un message

```php
$this->line('Simple text');           // texte brut
$this->info('Success message');       // vert
$this->error('Error message');        // rouge
$this->warn('Warning message');       // jaune
```

### Poser une question

```php
$name = $this->ask('What is your name?');
```

### Demander une confirmation

```php
if ($this->confirm('Do you want to continue?')) {
    $this->info('Continuing...');
} else {
    $this->info('Aborted');
}
```

### Afficher un tableau

```php
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

$headers = new StringTypedCollection();
$headers->add('ID', 'Name', 'Email');

$rows = new RowCollection();

$row1 = new RowCollection();
$row1->add(1, 'John Doe', 'john@example.com');
$rows->add($row1);

$this->table($headers, $rows);
```

Sortie :
```
| ID | Name        | Email             |
|----|-------------|-------------------|
| 1  | John Doe    | john@example.com  |
```

---

## Charger Laravel optionnellement

### Pourquoi ?

Par défaut, les directives s'exécutent **sans charger Laravel** pour des performances optimales. Pour accéder à Eloquent, à la base de données ou au cache, activez Laravel :

```php
final class UserListDirective extends AbstractDirective
{
    public function shouldBootLaravel(): bool
    {
        return true; // Active Laravel pour cette directive
    }
    
    public function execute(): ExitCode
    {
        // Eloquent fonctionne !
        $users = User::all();
        
        foreach ($users as $user) {
            $this->line("{$user->id}: {$user->name}");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

### Vérifier si Laravel est disponible

```php
public function execute(): ExitCode
{
    if (!$this->hasLaravel()) {
        $this->error('Laravel is not available!');
        return ExitCode::FAILURE;
    }
    
    $this->info('Laravel is available!');
    return ExitCode::SUCCESS;
}
```

### Accéder à l'instance Laravel

```php
public function execute(): ExitCode
{
    $app = $this->getLaravel();
    
    if ($app !== null) {
        $version = $app->version();
        $this->info("Laravel version: {$version}");
    }
    
    return ExitCode::SUCCESS;
}
```

### Performance

Seules les directives qui demandent explicitement Laravel via `shouldBootLaravel()` déclenchent le bootstrap. Les autres directives restent ultra-rapides !

Le bootstrap de Laravel se fait **une seule fois** par exécution, même si plusieurs directives le demandent.

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
| 0 | `ExitCode::SUCCESS` | Exécution réussie |
| 1 | `ExitCode::FAILURE` | Erreur générale |
| 3 | `ExitCode::NOT_FOUND` | Directive non trouvée |
| 4 | `ExitCode::INVALID_ARGUMENT` | Argument invalide |

```php
public function execute(): ExitCode
{
    if ($this->argument('name') === null) {
        $this->error('Name is required');
        return ExitCode::INVALID_ARGUMENT;
    }
    
    try {
        // Logique métier
        return ExitCode::SUCCESS;
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return ExitCode::FAILURE;
    }
}
```

---

## Tester vos directives

Le package fournit un service `DirectiveTestingService` pour tester vos directives dans un environnement isolé.

### Installation des dépendances de test

```bash
composer require --dev orchestra/testbench phpunit/phpunit
```

### Configuration du test

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\Directive\Tests\UnitTestCase;
use App\Directives\HelloDirective;

final class HelloDirectiveTest extends UnitTestCase
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
        // Arrange
        $directive = new HelloDirective($this->service->getInteraction());
        $this->service->registerDirective($directive);
        
        // Act
        $response = $this->service->runDirective('hello', ['John']);
        
        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }
}
```

### Tester avec environnement Laravel

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

### Méthodes du service `DirectiveTestingService`

| Méthode | Description |
|---------|-------------|
| `registerDirective(AbstractDirective $directive)` | Enregistre une directive |
| `registerDirectives(array $directives)` | Enregistre plusieurs directives |
| `clearRegisteredDirectives()` | Supprime toutes les directives |
| `createTestDirective(string $signature, callable $execute)` | Crée une directive temporaire |
| `runDirective(string $signature, array $arguments = [])` | Exécute une directive |

### Exemple : Créer une directive temporaire avec closure

```php
public function test_temporary_directive(): void
{
    $executed = false;
    
    $directive = $this->service->createTestDirective('test-closure', function ($d) use (&$executed) {
        $executed = true;
        $d->line('Closure executed');
        return ExitCode::SUCCESS;
    });
    
    $response = $this->service->runDirective('test-closure');
    
    $this->assertTrue($executed);
    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Closure executed', $response->output);
}
```

### Exemple : Tester une directive avec arguments variadiques

```php
public function test_directive_with_variadic_arguments(): void
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

---

## Exemples complets

### Directive avec arguments variadiques

```php
<?php

declare(strict_types=1);

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

    public function execute(): ExitCode
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

// Utilisation: ./directive backup /var/www /backup node_modules .git cache --compress --format=tar
// Avec crochets: ./directive backup /var/www /backup [node_modules, .git, cache] --compress --format=tar
```

### Directive avec base de données (Laravel)

```php
<?php

declare(strict_types=1);

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
        return 'Display user statistics from database';
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        if (!$this->hasLaravel()) {
            $this->error('Database not available!');
            return ExitCode::FAILURE;
        }

        $totalUsers = User::count();
        $this->info("Total users: {$totalUsers}");
        
        $query = User::query();
        if ($this->option('active')) {
            $query->where('is_active', true);
        }
        
        $users = $query->get();
        
        $headers = new StringTypedCollection();
        $headers->add('ID', 'Name', 'Email');
        
        $rows = new RowCollection();
        foreach ($users as $user) {
            $row = new RowCollection();
            $row->add($user->id, $user->name, $user->email);
            $rows->add($row);
        }
        
        $this->table($headers, $rows);
        
        return ExitCode::SUCCESS;
    }
}
```

### Directive interactive

```php
<?php

declare(strict_types=1);

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
        return 'Interactive application setup';
    }

    public function execute(): ExitCode
    {
        $this->info('Welcome to the setup wizard!');
        
        $appName = $this->ask('Application name');
        $environment = $this->ask('Environment (local/production)');
        
        if (!$this->confirm("Create configuration for {$appName} in {$environment}?")) {
            $this->warn('Setup cancelled');
            return ExitCode::SUCCESS;
        }
        
        $this->info("Configuration created for {$appName} in {$environment}");
        
        return ExitCode::SUCCESS;
    }
}
```

---

## Pourquoi ce package ?

### Les faiblesses d'Artisan (Laravel natif)

| Problème | Solution avec Directives |
|----------|--------------------------|
| Héritage unique obligatoire | Pas de contrainte d'héritage |
| Signature, description et logique mélangées | Séparation claire via `getSignature()`, `getDescription()`, `execute()` |
| Couplage fort entre logique et affichage | Injection de `DirectiveInteractionService` |
| Tests difficiles (`ask()` et `confirm()` impossible à mocker) | Services mockables |
| Pas d'extensibilité pour les packages | Découverte automatique dans `vendor/*/src/Directives/` |
| Arguments non typés | Accès typé via `argument()` et `option()` |
| **Pas d'arguments variadiques** | **Support des arguments variadiques avec `{files*}`** |
| Pas de séparation des responsabilités | Architecture propre et testable |

### La solution : Directives

- ✅ **Séparation des responsabilités** : Logique métier et présentation découplées
- ✅ **Testabilité exceptionnelle** : Chaque directive est facile à mocker
- ✅ **Extensibilité** : Les packages peuvent enregistrer leurs directives automatiquement
- ✅ **Laravel à la demande** : Bootstrap optionnel uniquement quand nécessaire
- ✅ **Validation stricte** : Format et ordre des signatures validés
- ✅ **Typage fort** : Arguments et options typés
- ✅ **Arguments variadiques** : Capturez tous les arguments restants avec `{files*}`
- ✅ **Découverte automatique** : Aucune configuration requise pour les packages

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```