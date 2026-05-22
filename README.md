# Laravel Directive

**A flexible CLI command system for Laravel that breaks free from Artisan's constraints. Directives introduces a clean separation between business logic and presentation.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Pourquoi ce package ?

### Les faiblesses d'Artisan (Laravel natif)

| Problème | Explication |
|----------|-------------|
| **Héritage unique** | Impossible d'avoir des commandes sans hériter de `Command` |
| **Configuration monolithique** | Signature, description et logique mélangées dans une seule classe |
| **Couplage fort** | La logique métier est couplée à l'affichage (`$this->info()`, `$this->table()`) |
| **Tests difficiles** | Les commandes Artisan sont complexes à mocker. Impossible de mocker `ask()` ou `confirm()` |
| **Pas d'extensibilité** | Impossible pour un package d'enregistrer ses propres commandes facilement |
| **Arguments non typés** | Les arguments et options arrivent sous forme de tableau brut (`array $arguments`) |
| **Pas de séparation claire** | Le `handle()` contient à la fois la logique métier et l'interface utilisateur |

### Pourquoi un package ne peut pas enregistrer ses commandes facilement avec Artisan ?

Avec Artisan, un package externe doit :
1. Publier ses commandes via `$this->commands([...])` dans le ServiceProvider
2. L'utilisateur final doit exécuter `php artisan vendor:publish`
3. Les commandes sont enregistrées MAIS l'utilisateur ne peut pas les lister sans connaître leur existence

**Avec Laravel Directive, c'est différent :**
- Le package enregistre ses directives programmatiquement
- L'utilisateur les voit immédiatement avec `./vendor/bin/directive --list`
- Aucune action manuelle n'est requise

### La solution : Directives

**Laravel Directive** introduit une architecture propre avec :

- **Séparation des responsabilités** : La logique métier et l'affichage sont découplés
- **Typage fort** : Arguments et options typés via `ParameterCollection` et `ParameterRecord`
- **Testabilité exceptionnelle** : Chaque directive est facile à mocker et tester
- **Extensibilité** : Enregistrez des directives depuis n'importe quel package via `DirectiveRegistrar`
- **Simplicité** : Une classe = une directive, sans configuration complexe

```php
// ✅ Une directive propre et testable
final class UserListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:list {--active} {role?}';
    }

    public function getDescription(): string
    {
        return 'List all users matching criteria';
    }

    public function execute(): ExitCode
    {
        $active = $this->option('active');
        $role = $this->argument('role');
        
        // Votre logique métier ici
        
        $this->info('Users listed successfully!');
        
        return ExitCode::SUCCESS;
    }
}
```

---

## Installation

```bash
composer require andydefer/laravel-directive
```

### Prérequis

- PHP 8.1 ou supérieur
- Laravel 12.x, 13.x, 14.x ou 15.x
- Dépend automatiquement de `andydefer/php-records`

### Publication de la configuration (optionnel)

```bash
php artisan vendor:publish --tag=directive-config
```

---

## Configuration

### Variables d'environnement

```env
DIRECTIVE_PATH=app/CustomDirectives
```

### Fichier de configuration

```php
// config/directive.php
return [
    'path' => env('DIRECTIVE_PATH', app_path('Directives')),
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

### Créer votre première directive avec la commande intégrée

```bash
./vendor/bin/directive make:directive hello
```

Cela génère le fichier `app/Directives/HelloDirective.php`.

### Exécuter votre directive

```bash
./vendor/bin/directive hello
```

---

## Les méthodes de base

### `getSignature()` - La signature

Définit le nom et les paramètres de la directive.

```php
public function getSignature(): string
{
    return 'user:create {name} {email} {--role=admin}';
}
```

| Élément | Syntaxe | Description |
|---------|---------|-------------|
| Argument requis | `{name}` | Paramètre positionnel obligatoire |
| Argument optionnel | `{name?}` | Paramètre positionnel optionnel |
| Option avec valeur | `{--role=}` | Option avec valeur par défaut optionnelle |
| Option flag | `{--force}` | Option sans valeur (true/false) |
| Option avec valeur par défaut | `{--role=admin}` | Option avec valeur par défaut |

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
    // Votre code ici
    
    return ExitCode::SUCCESS;
}
```

### `getAliases()` - Les alias

```php
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

public function getAliases(): StringTypedCollection
{
    $aliases = new StringTypedCollection();
    $aliases->add('user:add');
    $aliases->add('create:user');
    return $aliases;
}
```

---

## Arguments et options

### Accès aux arguments

```php
// Signature: user:create {name} {email?}
public function execute(): ExitCode
{
    $name = $this->argument('name');      // Requis
    $email = $this->argument('email');    // Optionnel (null si absent)
    
    if ($name === null) {
        $this->error('Name is required');
        return ExitCode::INVALID_ARGUMENT;
    }
    
    return ExitCode::SUCCESS;
}
```

### Accès aux options

```php
// Signature: cache:clear {--force} {--ttl=3600}
public function execute(): ExitCode
{
    $force = $this->option('force');  // bool (true si présent)
    $ttl = $this->option('ttl');      // string ou null
    
    if ($ttl !== null) {
        $ttl = (int) $ttl;
    }
    
    return ExitCode::SUCCESS;
}
```

### Vérifier l'existence d'une option

```php
public function execute(): ExitCode
{
    if ($this->hasOption('verbose')) {
        $this->info('Verbose mode enabled');
    }
    
    return ExitCode::SUCCESS;
}
```

---

## Interaction utilisateur

### Afficher un message simple (`line`)

```php
$this->line('Simple text without formatting');
```

### Afficher une information (`info`)

```php
$this->info('Task completed successfully');
// Sortie en vert
```

### Afficher une erreur (`error`)

```php
$this->error('Something went wrong');
// Sortie en rouge
```

### Afficher un avertissement (`warn`)

```php
$this->warn('This operation may take a while');
// Sortie en jaune
```

### Poser une question (`ask`)

```php
$name = $this->ask('What is your name?');
// Affiche: What is your name? _
// Retourne la saisie utilisateur
```

### Demander une confirmation (`confirm`)

```php
if ($this->confirm('Do you want to continue?')) {
    $this->info('Continuing...');
} else {
    $this->info('Aborted');
    return ExitCode::SUCCESS;
}
// Affiche: Do you want to continue? (y/n)
// Retourne true pour y/yes, false pour n/no
```

### Afficher un tableau (`table`)

```php
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

$headers = new StringTypedCollection();
$headers->add('ID', 'Name', 'Email');

$rows = new RowCollection();

$row1 = new RowCollection();
$row1->add(1, 'John Doe', 'john@example.com');
$rows->add($row1);

$row2 = new RowCollection();
$row2->add(2, 'Jane Smith', 'jane@example.com');
$rows->add($row2);

$this->table($headers, $rows);
```

**Sortie :**

```
| ID | Name        | Email             |
|----|-------------|-------------------|
| 1  | John Doe    | john@example.com  |
| 2  | Jane Smith  | jane@example.com  |
```

---

## Enregistrement de directives depuis un package tiers

### Pourquoi c'est important ?

Avec Artisan, un package externe ne peut pas enregistrer ses commandes sans action de l'utilisateur final.

**Avec Laravel Directive, c'est automatique :**

### Pour les développeurs de packages

```php
<?php

namespace Vendor\MyPackage;

use AndyDefer\Directive\Contracts\DirectiveRegistrarInterface;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Support\ServiceProvider;

class MyPackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Récupérer le registrar
        $registrar = app(DirectiveRegistrarInterface::class);
        
        // Créer la collection des classes de directives
        $classes = new StringTypedCollection();
        $classes->add(MyDirective::class);
        $classes->add(AnotherDirective::class);
        
        // Enregistrer
        $registrar->register($classes);
    }
}
```

### Comment ça fonctionne ?

```
┌─────────────────────────────────────────────────────────────┐
│                      PACKAGE TIERS                          │
│                                                             │
│  1. Appelle DirectiveRegistrar::register()                 │
│     ↓                                                       │
│  2. Le registrar stocke les classes                        │
│     ↓                                                       │
│  3. DirectiveDiscoveryService fusionne :                    │
│     - Directives du filesystem (app/Directives/)           │
│     - Directives enregistrées par les packages             │
│     ↓                                                       │
│  4. Le kernel exécute la directive trouvée                 │
└─────────────────────────────────────────────────────────────┘
```

### Exemple concret

```bash
# Après installation du package, la commande est directement disponible
./vendor/bin/directive my-package:command
```

### Enregistrement des directives built-in

Le package enregistre automatiquement ses propres directives :

```php
// Dans DirectiveServiceProvider
$classes = new StringTypedCollection();
$classes->add(MakeDirective::class);
$registrar->register($classes);
```

---

## Commandes intégrées

### `make:directive` - Créer une nouvelle directive

```bash
# Créer une directive simple
./vendor/bin/directive make:directive user:list
```

**Génère :** `app/Directives/UserListDirective.php`

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class UserListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:list {--option}';
    }

    public function getDescription(): string
    {
        return 'Generated directive for user:list';
    }

    public function execute(): ExitCode
    {
        $this->info('Directive executed successfully!');
        return ExitCode::SUCCESS;
    }
}
```

### `list:directives` - Lister toutes les directives

```bash
# Liste simple
./vendor/bin/directive --list

# Ou avec l'alias
./vendor/bin/directive -l
```

### Alias disponibles

| Commande | Alias |
|----------|-------|
| `make:directive` | `create:directive`, `make:cmd` |
| `--list` | `-l` |
| `--help` | `-h` |

---

## Testabilité

### Comparaison avec Artisan

| Aspect | Artisan natif | Laravel Directive |
|--------|---------------|-------------------|
| **Mock des dépendances** | Difficile (appel à `$this->call()`) | Facile (injection de dépendances) |
| **Test des arguments** | Simulation complexe via `$this->artisan()` | Injection directe dans `ParameterCollection` |
| **Test des options** | Doit passer par la ligne de commande | Accès direct via `option()` mocké |
| **Test des sorties** | Capture via `$this->expectsOutput()` | Mock des `DisplayMessageTask` |
| **Test des interactions** | Impossible de mocker `ask()` et `confirm()` | Injection de flux personnalisé |
| **Isolement** | La commande s'exécute réellement | La logique métier est isolée |

### Exemple : Tester une directive complète

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Tests\TestCase;
use App\Directives\UserCreateDirective;

final class UserCreateDirectiveTest extends TestCase
{
    private UserCreateDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directive = $this->app->make(UserCreateDirective::class);
    }

    public function test_execute_creates_user_and_returns_success(): void
    {
        // Arrange
        $arguments = new ParameterCollection();
        $arguments->add(
            new ParameterRecord(name: 'name', value: 'John Doe'),
            new ParameterRecord(name: 'email', value: 'john@example.com'),
        );
        $this->directive->setArguments($arguments);

        // Act
        $result = $this->directive->execute();

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_error_when_name_missing(): void
    {
        // Arrange
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: 'email', value: 'john@example.com'));
        $this->directive->setArguments($arguments);

        // Act
        $result = $this->directive->execute();

        // Assert
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $result);
    }
}
```

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

## Exemples complets

### Directive avec arguments et options

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:create {name} {email} {--role=user} {--notify}';
    }

    public function getDescription(): string
    {
        return 'Create a new user account';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        
        if ($name === null || $email === null) {
            $this->error('Name and email are required');
            return ExitCode::INVALID_ARGUMENT;
        }
        
        $role = $this->option('role');
        $notify = $this->option('notify');
        
        $this->info("User {$name} created with role {$role}");
        
        if ($notify) {
            $this->info("Notification sent to {$email}");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

**Utilisation :**

```bash
./vendor/bin/directive user:create "John Doe" john@example.com --role=admin --notify
```

### Directive interactive complète

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class SetupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'app:setup';
    }

    public function getDescription(): string
    {
        return 'Interactive application setup';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('setup');
        return $aliases;
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
        
        $headers = new StringTypedCollection();
        $headers->add('Setting', 'Value');
        
        $rows = new RowCollection();
        $row = new RowCollection();
        $row->add('App Name', $appName);
        $row->add('Environment', $environment);
        $rows->add($row);
        
        $this->table($headers, $rows);
        
        $this->info('Setup completed successfully!');
        
        return ExitCode::SUCCESS;
    }
}
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      DIRECTIVE KERNEL                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────┐     ┌─────────────────────────────┐    │
│  │  DISCOVERY    │────▶│        REGISTRAR            │    │
│  │ (filesystem)  │     │  (package registration)     │    │
│  └───────────────┘     └─────────────────────────────┘    │
│         │                           │                      │
│         ▼                           ▼                      │
│  ┌────────────────────────────────────────────────────┐   │
│  │           EXECUTION SERVICE                         │   │
│  │     (fusion, parsing, hydration, execution)        │   │
│  └────────────────────────────────────────────────────┘   │
│                           │                               │
│                           ▼                               │
│  ┌────────────────────────────────────────────────────┐   │
│  │                 YOUR DIRECTIVES                     │   │
│  │          (app/Directives/*.php)                    │   │
│  └────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Composants

| Composant | Rôle |
|-----------|------|
| `DirectiveKernel` | Point d'entrée, parsing des arguments bruts |
| `DirectiveParserService` | Parse les signatures et les arguments |
| `DirectiveHydratorService` | Hydrate les directives avec les arguments typés |
| `DirectiveDiscoveryService` | Découvre les directives (filesystem + packages) |
| `DirectiveRegistrar` | Enregistre les directives des packages |
| `DirectiveExecutionService` | Exécute la directive demandée |
| `AbstractDirective` | Classe de base pour toutes les directives |

### Tasks d'affichage (découplage)

| Task | Rôle |
|------|------|
| `DisplayMessageTask` | Affiche des messages colorés |
| `DisplayTableTask` | Affiche des tableaux formatés |
| `AskQuestionTask` | Gère les questions interactives |
| `ConfirmQuestionTask` | Gère les confirmations |
| `DisplayErrorTask` | Affiche les erreurs formatées |

---

## Bonnes pratiques

### 1. Une directive = une responsabilité

```php
// ✅ BON
final class UserCreateDirective extends AbstractDirective { }
final class UserDeleteDirective extends AbstractDirective { }

// ❌ MAUVAIS
final class UserDirective extends AbstractDirective { }
```

### 2. Nommage cohérent

```php
// ✅ BON
getSignature(): 'user:create'
getDescription(): 'Create a new user'

// ❌ MAUVAIS
getSignature(): 'createUser'
getDescription(): 'Does stuff'
```

### 3. Gérer les erreurs

```php
public function execute(): ExitCode
{
    $name = $this->argument('name');
    
    if ($name === null) {
        $this->error('Name is required');
        return ExitCode::INVALID_ARGUMENT;
    }
    
    try {
        // Logique métier
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return ExitCode::FAILURE;
    }
    
    return ExitCode::SUCCESS;
}
```

### 4. Enregistrer les directives depuis un package

```php
// Dans le ServiceProvider du package
public function boot(): void
{
    $classes = new StringTypedCollection();
    $classes->add(MyDirective::class);
    
    app(DirectiveRegistrarInterface::class)->register($classes);
}
```

### 5. Garder les directives testables

```php
// ✅ BON - Injection de dépendances
final class MyDirective extends AbstractDirective
{
    public function __construct(
        private readonly UserService $userService,
        ...$parents
    ) {
        parent::__construct(...$parents);
    }
}

// ❌ MAUVAIS - Appel statique (difficile à tester)
final class MyDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        UserService::create(); // Difficile à mocker
    }
}
```

---

## API Reference

### AbstractDirective

| Méthode | Retour | Description |
|---------|--------|-------------|
| `getSignature()` | `string` | Signature de la directive |
| `getDescription()` | `string` | Description |
| `getAliases()` | `StringTypedCollection` | Alias |
| `execute()` | `ExitCode` | Logique métier |
| `argument(string $key)` | `?string` | Valeur d'un argument |
| `option(string $key)` | `bool\|string\|null` | Valeur d'une option |
| `hasOption(string $key)` | `bool` | Option présente ? |
| `line(string $message)` | `void` | Affiche un message |
| `info(string $message)` | `void` | Affiche en vert |
| `error(string $message)` | `void` | Affiche en rouge |
| `warn(string $message)` | `void` | Affiche en jaune |
| `ask(string $question)` | `string` | Demande utilisateur |
| `confirm(string $question)` | `bool` | Confirmation |
| `table(StringTypedCollection $headers, RowCollection $rows)` | `void` | Affiche un tableau |

### ExitCode

| Valeur | Constante |
|--------|-----------|
| 0 | `ExitCode::SUCCESS` |
| 1 | `ExitCode::FAILURE` |
| 3 | `ExitCode::NOT_FOUND` |
| 4 | `ExitCode::INVALID_ARGUMENT` |

### Commandes intégrées

| Commande | Description |
|----------|-------------|
| `./vendor/bin/directive make:directive {name}` | Crée une nouvelle directive |
| `./vendor/bin/directive --list` | Liste toutes les directives |
| `./vendor/bin/directive --help` | Affiche l'aide |

### Alias disponibles

| Commande | Alias |
|----------|-------|
| `make:directive` | `create:directive`, `make:cmd` |
| `--list` | `-l` |
| `--help` | `-h` |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```