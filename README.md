# Laravel Directive

**A flexible CLI command system for Laravel that breaks free from Artisan's constraints. Directives introduces a clean separation between business logic and presentation.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

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

```bash
./vendor/bin/directive make-directive hello
```

Cela génère le fichier `app/Directives/HelloDirective.php`.

### Exécuter votre directive

```bash
./vendor/bin/directive hello
```

---

## Charger Laravel optionnellement

### Pourquoi ?

Par défaut, les directives s'exécutent **sans charger Laravel** pour des performances optimales. Mais parfois, votre directive a besoin d'accéder à :

- **Eloquent models** (`User::find(1)`)
- **La base de données** (`DB::table('users')`)
- **Le cache Laravel** (`Cache::get('key')`)
- **Les files d'attente** (`Queue::push()`)
- **Tout autre service Laravel**

### Comment faire ?

Il suffit de surcharger la méthode `shouldBootLaravel()` dans votre directive :

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use App\Models\User;

final class UserListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-list';
    }

    public function getDescription(): string
    {
        return 'List all users from database';
    }

    /**
     * Active le chargement de Laravel pour cette directive
     */
    public function shouldBootLaravel(): bool
    {
        return true; // ← Active Laravel
    }

    public function execute(): ExitCode
    {
        // Maintenant Eloquent fonctionne !
        $users = User::all();
        
        foreach ($users as $user) {
            $this->line("{$user->id}: {$user->name} ({$user->email})");
        }
        
        $this->info('Total: ' . $users->count() . ' users');
        
        return ExitCode::SUCCESS;
    }
}
```

### Vérifier si Laravel est disponible

Utilisez `hasLaravel()` pour vérifier si Laravel a été chargé avec succès :

```php
public function execute(): ExitCode
{
    if (!$this->hasLaravel()) {
        $this->error('Laravel is not available!');
        return ExitCode::FAILURE;
    }
    
    // Maintenant vous pouvez utiliser Laravel en toute sécurité
    $this->info('Laravel is available!');
    
    return ExitCode::SUCCESS;
}
```

### Accéder à l'instance de l'application Laravel

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

```php
// Directive rapide - pas de bootstrap Laravel
final class CacheClearDirective extends AbstractDirective
{
    public function shouldBootLaravel(): bool
    {
        return false; // Par défaut, pas besoin de Laravel
    }
    
    public function execute(): ExitCode
    {
        // Manipulation de fichiers uniquement
        array_map('unlink', glob('/tmp/cache/*'));
        return ExitCode::SUCCESS;
    }
}
```

### Exemple concret avec base de données

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        return true; // Besoin de la base de données
    }

    public function execute(): ExitCode
    {
        if (!$this->hasLaravel()) {
            $this->error('Database not available!');
            return ExitCode::FAILURE;
        }

        // Requête Eloquent
        $totalUsers = User::count();
        $this->info("Total users: {$totalUsers}");
        
        // Requête avec condition
        $activeFilter = $this->option('active');
        $query = User::query();
        
        if ($activeFilter) {
            $query->where('is_active', true);
        }
        
        $users = $query->get();
        
        // Affichage en tableau
        $headers = new StringTypedCollection();
        $headers->add('ID', 'Name', 'Email', 'Status');
        
        $rows = new RowCollection();
        foreach ($users as $user) {
            $row = new RowCollection();
            $row->add($user->id, $user->name, $user->email, $user->is_active ? '✓' : '✗');
            $rows->add($row);
        }
        
        $this->table($headers, $rows);
        
        // Requête DB brute
        $postCount = DB::table('posts')->count();
        $this->info("Total posts: {$postCount}");
        
        return ExitCode::SUCCESS;
    }
}
```

### Note importante

Le bootstrap de Laravel se fait **une seule fois** par exécution, même si plusieurs directives le demandent. Pas d'impact sur les performances !

---

## Format des signatures de directives

Les signatures de directives doivent respecter un format strict pour garantir la cohérence et la testabilité du code.

### Règles fondamentales

| Règle | Explication |
|-------|-------------|
| **Délimiteurs autorisés** | Seuls `-` (tiret) est autorisé comme séparateur |
| **Caractères autorisés** | Lettres (a-z, A-Z) et chiffres (0-9) |
| **Premier caractère** | Doit être une lettre (pas un chiffre ni un délimiteur) |
| **Pas de délimiteurs consécutifs** | `user--list` est interdit |
| **Pas de délimiteur final** | `user-` est interdit |
| **Pas de délimiteur initial** | `-list` est interdit |

### Format de signature

```
partie-partie
```

Chaque `[partie]` doit :
- Commencer par une lettre
- Ne contenir que des lettres et des chiffres

### ✅ Exemples valides

| Signature | Explication |
|-----------|-------------|
| `user-list` | Utilisation du délimiteur `-` |
| `cache-clear` | Utilisation du délimiteur `-` |
| `api-user-profile` | Plusieurs délimiteurs `-` |
| `user-v2` | Les chiffres sont autorisés dans une partie |

### ❌ Exemples invalides

| Signature | Raison |
|-----------|--------|
| `user:list` | Caractère `:` interdit |
| `create@user` | Caractère `@` interdit |
| `create_user` | Underscore `_` interdit |
| `user-` | Délimiteur final interdit |
| `-list` | Délimiteur initial interdit |
| `user--list` | Délimiteurs consécutifs interdits |
| `123-user` | Premier caractère est un chiffre |
| `user-123-list` | Partie commençant par un chiffre |
| `User-List` | Les majuscules sont autorisées mais déconseillées (préférer minuscules) |

### Pourquoi ce format strict ?

1. **Génération automatique des noms de classes** : Le service `DirectiveNamingService` convertit `user-list` en `UserListDirective`. Les caractères interdits empêcheraient cette conversion propre.

2. **Compatibilité cross-platform** : Les caractères comme `@` ou `_` peuvent avoir des significations spéciales selon les shells.

3. **Cohérence du code** : Toutes les directives suivent le même pattern, ce qui facilite la maintenance et la découverte.

4. **Validation automatique** : Le kernel valide la signature avant l'exécution. Une signature invalide retourne immédiatement un code d'erreur `INVALID_ARGUMENT`.

### Transformation signature → nom de classe

Le package convertit automatiquement la signature en nom de classe PascalCase :

| Signature | Nom de classe généré |
|-----------|---------------------|
| `user-list` | `UserListDirective` |
| `cache-clear` | `CacheClearDirective` |
| `api-user-profile` | `ApiUserProfileDirective` |
| `admin-user-create` | `AdminUserCreateDirective` |
| `user-v2-profile` | `UserV2ProfileDirective` |

---

## Les méthodes de base

### `getSignature()` - La signature

Définit le nom et les paramètres de la directive.

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
    $aliases->add('user-add');
    $aliases->add('create-user');
    return $aliases;
}
```

---

## Arguments et options

### Accès aux arguments

```php
// Signature: user-create {name} {email?}
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
// Signature: cache-clear {--force} {--ttl=3600}
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
        $registrar = app(DirectiveRegistrarInterface::class);
        
        $classes = new StringTypedCollection();
        $classes->add(MyDirective::class);
        $classes->add(AnotherDirective::class);
        
        $registrar->register($classes);
    }
}
```

### Comment ça fonctionne ?

```
┌─────────────────────────────────────────────────────────────┐
│                      PACKAGE TIERS                          │
│                                                             │
│  1. Appelle DirectiveRegistrar::register()                  │
│     ↓                                                       │
│  2. Le registrar stocke les classes                         │
│     ↓                                                       │
│  3. DirectiveDiscoveryService fusionne :                    │
│     - Directives du filesystem (app/Directives/)            │
│     - Directives enregistrées par les packages              │
│     ↓                                                       │
│  4. Le kernel exécute la directive trouvée                  │
└─────────────────────────────────────────────────────────────┘
```

### Exemple concret

```bash
# Après installation du package, la commande est directement disponible
./vendor/bin/directive my-package-command
```

---

## Commandes intégrées

### `make-directive` - Créer une nouvelle directive

```bash
# Créer une directive simple
./vendor/bin/directive make-directive user-list
```

**Génère :** `app/Directives/UserListDirective.php`

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

/**
 * Generated directive for user-list
 * Created at: 2026-05-23 10:30:00
 */
final class UserListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-list {--option}';
    }

    public function getDescription(): string
    {
        return 'Generated directive for user-list';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        // $aliases->add('your-alias');
        return $aliases;
    }

    /**
     * Override this method to enable Laravel bootstrapping.
     * Set to true if you need Eloquent, DB, Cache, etc.
     */
    public function shouldBootLaravel(): bool
    {
        return false;
    }

    public function execute(): ExitCode
    {
        // TODO: Implement your directive logic here

        // Check if Laravel is available (if you enabled it)
        if ($this->hasLaravel()) {
            $this->info('Laravel is available!');
        }

        $this->info('Directive executed successfully!');

        return ExitCode::SUCCESS;
    }
}
```

### `--version` - Afficher la version

```bash
./vendor/bin/directive --version
```

Sortie :
```
═══════════════════════════════════════════════════════════════════════════
📦 Laravel Directive
═══════════════════════════════════════════════════════════════════════════

Version: 1.0.0
PHP Version: 8.2.0
Laravel: Bootstrapped ✓

═══════════════════════════════════════════════════════════════════════════
```

### Alias disponibles

| Commande | Alias |
|----------|-------|
| `make-directive` | `create-directive`, `make-cmd` |
| `--list` | `-l` |
| `--help` | `-h` |
| `--version` | `-v` |

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
- **Laravel à la demande** : Bootstrap optionnel uniquement quand nécessaire

```php
// ✅ Une directive propre et testable
final class UserListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-list {--active} {role?}';
    }

    public function getDescription(): string
    {
        return 'List all users matching criteria';
    }

    // Active Laravel uniquement si besoin
    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $active = $this->option('active');
        $role = $this->argument('role');
        
        // Votre logique métier avec Eloquent ici
        $users = User::query()
            ->when($active, fn($q) => $q->where('active', true))
            ->when($role, fn($q) => $q->where('role', $role))
            ->get();
        
        $this->info('Users listed successfully!');
        
        return ExitCode::SUCCESS;
    }
}
```

---

## Testabilité

### Comparaison avec Artisan

| Aspect | Artisan natif | Laravel Directive |
|--------|---------------|-------------------|
| **Mock des dépendances** | Difficile (appel à `$this->call()`) | Facile (injection de dépendances) |
| **Test des arguments** | Simulation complexe via `$this->artisan()` | Injection directe dans `ParameterCollection` |
| **Test des options** | Doit passer par la ligne de commande | Accès direct via `option()` mocké |
| **Test des sorties** | Capture via `$this->expectsOutput()` | Mock des services d'affichage |
| **Test des interactions** | Impossible de mocker `ask()` et `confirm()` | Mock du service d'interaction |
| **Test de Laravel** | Nécessite un environnement complet | Possible avec `shouldBootLaravel()` mocké |
| **Isolement** | La commande s'exécute réellement | La logique métier est isolée |

### Exemple : Tester une directive avec Laravel

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use App\Directives\UserListDirective;
use PHPUnit\Framework\TestCase;

final class UserListDirectiveTest extends TestCase
{
    private UserListDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();
        
        $interaction = $this->createMock(DirectiveInteractionService::class);
        $this->directive = new UserListDirective($interaction);
    }

    public function test_directive_declares_laravel_needed(): void
    {
        // Vérifie que la directive demande Laravel
        $this->assertTrue($this->directive->shouldBootLaravel());
    }

    public function test_execute_returns_success(): void
    {
        // Mock des arguments
        $arguments = new ParameterCollection();
        $arguments->add(new ParameterRecord(name: '--active', value: 'true'));
        $this->directive->setOptions($arguments);
        
        // Mock de hasLaravel pour simuler Laravel disponible
        $reflection = new \ReflectionClass($this->directive);
        $property = $reflection->getProperty('laravelBootstrapper');
        $mockBootstrapper = $this->createMock(LaravelBootstrapper::class);
        $mockBootstrapper->method('isBootstrapped')->willReturn(true);
        $property->setValue($this->directive, $mockBootstrapper);
        
        $result = $this->directive->execute();
        
        $this->assertSame(ExitCode::SUCCESS, $result);
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
        return 'user-create {name} {email} {--role=user} {--notify}';
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
./vendor/bin/directive user-create "John Doe" john@example.com --role=admin --notify
```

### Directive avec Laravel et base de données

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use App\Models\User;
use App\Models\Post;

final class DashboardDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'dashboard {--user-id=}';
    }

    public function getDescription(): string
    {
        return 'Show dashboard statistics';
    }

    public function shouldBootLaravel(): bool
    {
        return true; // Besoin d'Eloquent
    }

    public function execute(): ExitCode
    {
        if (!$this->hasLaravel()) {
            $this->error('Laravel is not available!');
            return ExitCode::FAILURE;
        }

        $userId = $this->option('user-id');
        
        // Statistiques globales
        $totalUsers = User::count();
        $totalPosts = Post::count();
        
        $this->info("=== STATISTIQUES ===");
        $this->info("Total users: {$totalUsers}");
        $this->info("Total posts: {$totalPosts}");
        
        // Détails d'un utilisateur spécifique
        if ($userId !== null) {
            $user = User::with('posts')->find($userId);
            
            if ($user) {
                $this->info("\n=== UTILISATEUR ===");
                $this->info("Name: {$user->name}");
                $this->info("Email: {$user->email}");
                $this->info("Posts: " . $user->posts->count());
                
                // Afficher les posts
                $headers = new StringTypedCollection();
                $headers->add('Post ID', 'Title', 'Published');
                
                $rows = new RowCollection();
                foreach ($user->posts as $post) {
                    $row = new RowCollection();
                    $row->add($post->id, $post->title, $post->is_published ? '✓' : '✗');
                    $rows->add($row);
                }
                
                $this->table($headers, $rows);
            } else {
                $this->warn("User #{$userId} not found");
            }
        }
        
        return ExitCode::SUCCESS;
    }
}
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
        return 'app-setup';
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
│  │              LARAVEL BOOTSTRAPPER                   │   │
│  │    (charge Laravel à la demande si besoin)         │   │
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
| `DirectiveKernel` | Point d'entrée, parsing des arguments bruts, validation des signatures |
| `SignatureValidationService` | Valide le format des signatures de directives |
| `DirectiveParserService` | Parse les signatures et les arguments |
| `DirectiveHydratorService` | Hydrate les directives avec les arguments typés |
| `DirectiveDiscoveryService` | Découvre les directives (filesystem + packages) |
| `DirectiveRegistrar` | Enregistre les directives des packages |
| `DirectiveExecutionService` | Exécute la directive demandée |
| `DirectiveInteractionService` | Gère les interactions utilisateur (messages, questions, tables) |
| `DirectiveNamingService` | Génère les noms de classes et signatures |
| `LaravelBootstrapper` | Charge Laravel à la demande pour les directives qui en ont besoin |
| `AbstractDirective` | Classe de base pour toutes les directives |
| `RenderTask` | Centralise le rendu des templates (Strategy Pattern) |
| `InputTask` | Centralise les entrées utilisateur (Strategy Pattern) |

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
getSignature(): 'user-create'
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
        DirectiveInteractionService $interaction,
        private readonly UserService $userService,
    ) {
        parent::__construct($interaction);
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

### 6. Activer Laravel uniquement si nécessaire

```php
// ✅ BON - Active seulement quand besoin
final class DatabaseDirective extends AbstractDirective
{
    public function shouldBootLaravel(): bool
    {
        return true; // Besoin de la base de données
    }
}

// ✅ BON - Pas de bootstrap inutile
final class FileDirective extends AbstractDirective
{
    public function shouldBootLaravel(): bool
    {
        return false; // Par défaut, pas besoin
    }
}
```

### 7. Respecter le format de signature

```php
// ✅ BON - Signatures valides
public function getSignature(): string
{
    return 'user-list';           // Délimiteur '-'
    return 'cache-clear';         // Délimiteur '-'
    return 'api-user-profile';    // Délimiteurs multiples
}

// ❌ MAUVAIS - Signatures invalides
public function getSignature(): string
{
    return 'user:list';           // Caractère ':' interdit
    return 'user@list';           // Caractère '@' interdit
    return 'create_user';         // Underscore '_' interdit
    return 'user-';               // Délimiteur final interdit
}
```

---

## Gestion des conflits d'alias

Lorsque plusieurs directives partagent le même alias, le système demande à l'utilisateur de choisir :

```bash
$ ./vendor/bin/directive my-alias

⚠️ Multiple directives match 'my-alias':
1. FirstDirective (signature: first-command)
   Description of first directive
2. SecondDirective (signature: second-command)
   Description of second directive

Which one do you want to use? [1-2]: 
```

---

## API Reference

### AbstractDirective

| Méthode | Retour | Description |
|---------|--------|-------------|
| `getSignature()` | `string` | Signature de la directive |
| `getDescription()` | `string` | Description |
| `getAliases()` | `StringTypedCollection` | Alias |
| `shouldBootLaravel()` | `bool` | Active le bootstrap Laravel |
| `hasLaravel()` | `bool` | Vérifie si Laravel est disponible |
| `getLaravel()` | `?object` | Instance de l'application Laravel |
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
| `./vendor/bin/directive make-directive {name}` | Crée une nouvelle directive |
| `./vendor/bin/directive --list` | Liste toutes les directives |
| `./vendor/bin/directive --help` | Affiche l'aide |
| `./vendor/bin/directive --version` | Affiche la version |

### Alias disponibles

| Commande | Alias |
|----------|-------|
| `make-directive` | `create-directive`, `make-cmd` |
| `--list` | `-l` |
| `--help` | `-h` |
| `--version` | `-v` |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```