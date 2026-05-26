Voici la documentation complète enrichie avec la section sur le bootstrapping Laravel dans les tests :

```markdown
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

### Ordre des paramètres

Le parser impose un ordre strict dans la définition des signatures :

| Ordre | Type | Syntaxe | Exemple |
|-------|------|---------|---------|
| 1 | Arguments requis | `{name}` | `{name} {email}` |
| 2 | Arguments avec valeur par défaut | `{role=user}` | `{role=admin}` |
| 3 | Arguments optionnels | `{count?}` | `{limit?}` |
| 4 | Options | `{--force}` ou `{-v}` | `{--verbose} {-f}` |

```php
// ✅ Ordre correct
public function getSignature(): string
{
    return 'user:create {name} {email} {role=user} {count?} {--force} {-v}';
}

// ❌ Ordre incorrect - Argument requis après optionnel
public function getSignature(): string
{
    return 'user:create {name?} {email}'; // Exception!
}

// ❌ Ordre incorrect - Option avant argument
public function getSignature(): string
{
    return 'user:create {--force} {name}'; // Exception!
}
```

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
| Argument avec valeur par défaut | `{count=10}` | Valeur par défaut si non fourni |
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

### Vérifier l'existence d'un argument

```php
// Signature: user-create {name} {count?}
public function execute(): ExitCode
{
    // Vérifier si l'argument a été fourni (même vide)
    if ($this->hasArgument('count')) {
        $count = $this->argument('count'); // Peut être '5' ou ''
        $this->info("Count provided: {$count}");
    } else {
        $this->info("Count not provided, using default");
    }
    
    return ExitCode::SUCCESS;
}
```

### Valeurs par défaut pour les arguments

```php
// Signature: user-list {limit=10}
public function execute(): ExitCode
{
    $limit = $this->argument('limit'); // '10' si non fourni
    $this->info("Limit: {$limit}");
    
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

## Commandes intégrées

### `make-directive` - Créer une nouvelle directive

```bash
# Créer une directive simple
./vendor/bin/directive make-directive user-list

# Créer une directive dans un sous-dossier
./vendor/bin/directive make-directive user/domain/hello-directive
```

**Génère :** `app/Directives/User/Domain/HelloDirective.php`

```php
<?php

declare(strict_types=1);

namespace App\Directives\User\Domain;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class HelloDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'hello-directive';
    }

    public function getDescription(): string
    {
        return 'Description for hello-directive';
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection();
    }

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    public function execute(): ExitCode
    {
        $this->info('Directive executed successfully!');
        
        return ExitCode::SUCCESS;
    }
}
```

**Sous-dossiers supportés :**
- `user/hello-directive` → `App\Directives\User\HelloDirective`
- `admin/user-list` → `App\Directives\Admin\UserListDirective`
- `api/v2/users` → `App\Directives\Api\V2\UsersDirective`

### `--version` - Afficher la version

```bash
./vendor/bin/directive --version
```

Sortie :
```
═══════════════════════════════════════════════════════════════════════════
📦 Laravel Directive
═══════════════════════════════════════════════════════════════════════════

Version: 2.2.0
PHP Version: 8.2.0
Laravel Version: 13.0.0

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

## Tester vos directives

Le package fournit un trait `InteractsWithDirectives` qui permet de tester vos directives dans un environnement isolé, sans avoir à créer de fichiers réels ou à dépendre du système de fichiers.

### Installation des dépendances de test

```bash
composer require --dev orchestra/testbench phpunit/phpunit
```

### Configuration du test

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use App\Directives\UserListDirective;
use PHPUnit\Framework\TestCase;

final class UserListDirectiveTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        // Initialise l'environnement de test
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        // Nettoie l'environnement de test
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_directive_returns_success(): void
    {
        // Enregistre la directive à tester
        $this->registerDirectiveClass(UserListDirective::class);
        
        // Exécute la directive
        $response = $this->runDirective('user-list');
        
        // Vérifie le résultat
        $response->assertSuccess();
        $this->assertStringContainsString('Total users:', $response->getOutput());
    }
}
```

### Tester des directives qui nécessitent Laravel

Pour les directives qui utilisent `shouldBootLaravel()`, vous pouvez activer l'environnement Laravel complet dans vos tests :

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use App\Directives\UserStatsDirective;
use PHPUnit\Framework\TestCase;

final class UserStatsDirectiveTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        // Active l'environnement Laravel complet
        $this->initDirectiveTesting(bootLaravel: true);
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_directive_with_laravel_bootstrapped(): void
    {
        $this->registerDirectiveClass(UserStatsDirective::class);
        
        $response = $this->runDirective('user-stats', ['--active']);
        
        $response->assertSuccess();
        $this->assertStringContainsString('Total users:', $response->getOutput());
    }
}
```

### Que fait `bootLaravel: true` ?

Lorsque vous activez cette option, le trait crée dynamiquement :

1. **La structure complète d'une application Laravel** :
   - `bootstrap/app.php` - Le fichier de démarrage de l'application
   - `config/app.php` - La configuration minimale
   - `storage/` - Les dossiers de stockage (framework, logs, cache)
   - `app/Http/` et `app/Models/` - Les dossiers de base

2. **Une instance de l'application Laravel** prête à être utilisée

3. **Le `LaravelBootstrapper` configuré** avec le chemin personnalisé vers l'application

Tout cela est fait **sans utiliser de réflexion** et de manière totalement isolée dans un répertoire temporaire.

### Méthodes du trait `InteractsWithDirectives`

| Méthode | Description |
|---------|-------------|
| `initDirectiveTesting(bool $bootLaravel = false)` | Initialise l'environnement de test (répertoire temporaire, conteneur, kernel). Optionnellement bootstrap Laravel |
| `destroyDirectiveTesting()` | Nettoie l'environnement de test |
| `registerDirective(AbstractDirective $directive)` | Enregistre une instance de directive |
| `registerDirectiveClass(string $className, array $constructorArgs = [])` | Enregistre une directive par nom de classe |
| `registerDirectives(array $directives)` | Enregistre plusieurs directives |
| `clearRegisteredDirectives()` | Supprime toutes les directives enregistrées |
| `createTestDirective(string $signature, callable $execute)` | Crée une directive temporaire avec une closure |
| `runDirective(string $signature, array $arguments = [])` | Exécute une directive et retourne un objet `DirectiveResponse` |
| `runAndAssert(string $signature, array $arguments = [])` | Exécute une directive et vérifie automatiquement le succès |

### L'objet `DirectiveResponse`

La méthode `runDirective()` retourne un objet `DirectiveResponse` avec les méthodes suivantes :

| Méthode | Description |
|---------|-------------|
| `getExitCode(): ExitCode` | Retourne le code de sortie |
| `getOutput(): string` | Retourne la sortie console |
| `getArguments(): array` | Retourne les arguments passés |
| `isSuccess(): bool` | Vérifie si l'exécution a réussi |
| `isFailure(): bool` | Vérifie si l'exécution a échoué |
| `getExitCodeValue(): int` | Retourne la valeur numérique du code de sortie |
| `assertSuccess(): self` | Vérifie que la directive a réussi |
| `assertFailure(?int $expectedExitCode = null): self` | Vérifie que la directive a échoué |
| `assertOutputContains(string $expected): self` | Vérifie que la sortie contient une chaîne |
| `assertOutputNotContains(string $expected): self` | Vérifie que la sortie ne contient pas une chaîne |
| `assertOutputMatches(string $pattern): self` | Vérifie que la sortie correspond à une expression régulière |
| `assertOutputEquals(string $expected): self` | Vérifie que la sortie est exactement égale |
| `assertOutputEmpty(): self` | Vérifie que la sortie est vide |

### Exemple : Tester la directive `MakeDirective`

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use AndyDefer\Directive\Directives\MakeDirective;
use PHPUnit\Framework\TestCase;

final class MakeDirectiveTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_get_signature_returns_make_directive(): void
    {
        $directive = $this->registerDirectiveClass(MakeDirective::class);
        
        $this->assertSame('make-directive {name}', $directive->getSignature());
    }

    public function test_execute_creates_file_with_correct_replacements(): void
    {
        $this->registerDirectiveClass(MakeDirective::class);
        
        $response = $this->runDirective('make-directive', ['user-create']);
        
        $response->assertSuccess();
        $this->assertStringContainsString('✅ Directive created successfully!', $response->getOutput());
        
        // Vérifier le contenu du fichier créé
        $expectedPath = $this->directiveTempDir . '/app/Directives/UserCreateDirective.php';
        $this->assertFileExists($expectedPath);
        
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class UserCreateDirective', $content);
        $this->assertStringContainsString("return 'user-create'", $content);
        $this->assertStringContainsString('namespace App\\Directives;', $content);
    }

    public function test_execute_creates_file_in_subdirectory(): void
    {
        $this->registerDirectiveClass(MakeDirective::class);
        
        $response = $this->runDirective('make-directive', ['user/domain/hello-directive']);
        
        $response->assertSuccess();
        
        $expectedPath = $this->directiveTempDir . '/app/Directives/User/Domain/HelloDirective.php';
        $this->assertFileExists($expectedPath);
        
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Directives\\User\\Domain;', $content);
        $this->assertStringContainsString('class HelloDirective', $content);
        $this->assertStringContainsString("return 'hello-directive'", $content);
    }

    public function test_execute_rejects_invalid_directive_name(): void
    {
        $this->registerDirectiveClass(MakeDirective::class);
        
        $response = $this->runDirective('make-directive', ['user@create']);
        
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Invalid directive name', $response->getOutput());
    }

    public function test_execute_returns_error_when_name_missing(): void
    {
        $this->registerDirectiveClass(MakeDirective::class);
        
        $response = $this->runDirective('make-directive');
        
        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->getExitCode());
        $this->assertStringContainsString('Not enough arguments', $response->getOutput());
    }
}
```

### Exemple : Créer une directive temporaire avec une closure

```php
public function test_temporary_directive_with_closure(): void
{
    $executed = false;
    
    $this->createTestDirective('test-closure', function ($directive) use (&$executed) {
        $executed = true;
        $directive->line('Closure executed');
        return ExitCode::SUCCESS;
    });
    
    $response = $this->runDirective('test-closure');
    
    $response->assertSuccess();
    $this->assertTrue($executed);
    $this->assertStringContainsString('Closure executed', $response->getOutput());
}
```

### Exemple : Tester une directive avec des options

```php
public function test_directive_with_options(): void
{
    $this->registerDirectiveClass(UserListDirective::class);
    
    $response = $this->runDirective('user-list', ['--verbose', '--limit=10']);
    
    $response->assertSuccess();
    $this->assertStringContainsString('Verbose mode', $response->getOutput());
}
```

### Exemple : Chaîner les assertions

```php
public function test_chained_assertions(): void
{
    $this->registerDirectiveClass(CalculatorDirective::class);
    
    $this->runDirective('calculator', ['add', '15', '25'])
        ->assertSuccess()
        ->assertOutputContains('40')
        ->assertOutputNotContains('0')
        ->assertOutputMatches('/\d+/');
}
```

### Exemple : Nettoyer les directives enregistrées

```php
public function test_clear_registered_directives(): void
{
    $this->createTestDirective('temp', fn($d) => ExitCode::SUCCESS);
    
    // La directive existe
    $response = $this->runDirective('temp');
    $response->assertSuccess();
    
    // Nettoyer
    $this->clearRegisteredDirectives();
    
    // La directive n'existe plus
    $response = $this->runDirective('temp');
    $this->assertSame(ExitCode::NOT_FOUND, $response->getExitCode());
}
```

### Comment ça fonctionne ?

Le trait `InteractsWithDirectives` :

1. **Crée un environnement isolé** : Un répertoire temporaire est créé pour simuler l'application
2. **Initialise un conteneur** : Un conteneur Illuminate est configuré avec tous les services nécessaires
3. **Optionnellement bootstrap Laravel** : Si `bootLaravel: true` est passé, crée une structure Laravel complète
4. **Enregistre les directives** : Les directives sont stockées dans un registry au lieu d'être lues depuis le filesystem
5. **Exécute les directives** : Le kernel normal est utilisé, mais avec un loader personnalisé
6. **Nettoie automatiquement** : Le répertoire temporaire est supprimé après les tests

### Points importants

- Le trait doit être utilisé dans une classe qui étend `PHPUnit\Framework\TestCase`
- Appelez `initDirectiveTesting()` dans `setUp()` et `destroyDirectiveTesting()` dans `tearDown()`
- Les directives doivent être enregistrées AVANT de les exécuter
- Le répertoire temporaire est accessible via `$this->directiveTempDir` pour vérifier les fichiers créés
- Pour les directives qui nécessitent Laravel, utilisez `initDirectiveTesting(bootLaravel: true)`

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

### La solution : Directives

**Laravel Directive** introduit une architecture propre avec :

- **Séparation des responsabilités** : La logique métier et l'affichage sont découplés
- **Typage fort** : Arguments et options typés via `ParameterCollection` et `ParameterRecord`
- **Testabilité exceptionnelle** : Chaque directive est facile à mocker et tester
- **Extensibilité** : Les packages peuvent enregistrer leurs directives automatiquement via le dossier `src/Directives/`
- **Simplicité** : Une classe = une directive, sans configuration complexe
- **Laravel à la demande** : Bootstrap optionnel uniquement quand nécessaire
- **Validation stricte** : Format et ordre des signatures validés automatiquement
- **Messages d'erreur clairs** : Erreurs de parsing capturées et affichées proprement

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
| **Test de Laravel** | Nécessite un environnement complet | Possible avec `shouldBootLaravel()` mocké ou `bootLaravel: true` |
| **Isolement** | La commande s'exécute réellement | La logique métier est isolée |

### Exemple : Tester une directive avec Laravel (sans bootstrapping)

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
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
        $this->assertTrue($this->directive->shouldBootLaravel());
    }

    public function test_execute_returns_success(): void
    {
        $arguments = new ParameterCollection();
        $this->directive->setArguments($arguments);
        
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

### Exemple : Tester une directive avec Laravel (avec bootstrapping réel)

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use App\Directives\UserStatsDirective;
use PHPUnit\Framework\TestCase;

final class UserStatsDirectiveTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        // Active un environnement Laravel complet
        $this->initDirectiveTesting(bootLaravel: true);
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_execute_with_laravel_available(): void
    {
        $this->registerDirectiveClass(UserStatsDirective::class);
        
        $response = $this->runDirective('user-stats', ['--active']);
        
        $response->assertSuccess();
        $this->assertStringContainsString('Total users:', $response->getOutput());
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
| 4 | `ExitCode::INVALID_ARGUMENT` | Argument invalide ou signature invalide |

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
        return 'user-create {name} {email} {role=user} {--notify}';
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
        
        $role = $this->argument('role');
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
./vendor/bin/directive user-create "John Doe" john@example.com --notify
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
│  ┌─────────────────────────────────────────────────────┐   │
│  │                 DISCOVERY SERVICE                    │   │
│  │  - app/Directives/*.php                             │   │
│  │  - vendor/*/src/Directives/*.php (découverte auto)  │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                               │
│                           ▼                               │
│  ┌────────────────────────────────────────────────────┐   │
│  │           EXECUTION SERVICE                         │   │
│  │     (parsing, hydration, execution)                │   │
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

### Découverte automatique des packages

Les directives sont automatiquement découvertes dans :
- `app/Directives/*.php` (directives de l'application)
- `vendor/*/src/Directives/*.php` (directives des packages)

**Aucune configuration requise pour les packages !** Il suffit de placer les directives dans `src/Directives/`.

### Composants

| Composant | Rôle |
|-----------|------|
| `DirectiveKernel` | Point d'entrée, parsing des arguments bruts, validation des signatures |
| `SignatureValidationService` | Valide le format des signatures de directives |
| `DirectiveParserService` | Parse les signatures et les arguments avec validation d'ordre |
| `DirectiveHydratorService` | Hydrate les directives avec les arguments typés |
| `DirectiveDiscoveryService` | Découvre les directives (filesystem + packages) |
| `DirectiveExecutionService` | Exécute la directive demandée avec capture d'erreurs |
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

### 4. Garder les directives testables

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

### 5. Activer Laravel uniquement si nécessaire

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

### 6. Respecter le format de signature

```php
// ✅ BON - Signatures valides
public function getSignature(): string
{
    return 'user-list';           // Délimiteur '-'
    return 'cache-clear';         // Délimiteur '-'
    return 'api-user-profile';    // Délimiteurs multiples
}

// ✅ BON - Ordre correct
public function getSignature(): string
{
    return 'user-create {name} {email} {role=user} {count?} {--force}';
}

// ❌ MAUVAIS - Signatures invalides
public function getSignature(): string
{
    return 'user:list';           // Caractère ':' interdit
    return 'user@list';           // Caractère '@' interdit
    return 'create_user';         // Underscore '_' interdit
    return 'user-';               // Délimiteur final interdit
}

// ❌ MAUVAIS - Ordre incorrect
public function getSignature(): string
{
    return 'user-create {name?} {email}';  // Requis après optionnel
    return 'user-create {--force} {name}'; // Option avant argument
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
| `setLaravelBootstrapper()` | `self` | Injecte le bootstrapper |
| `execute()` | `ExitCode` | Logique métier |
| `argument(string $key)` | `?string` | Valeur d'un argument (null si non fourni ou vide) |
| `hasArgument(string $key)` | `bool` | Argument existe et a une valeur non vide ? |
| `option(string $key)` | `bool\|string\|null` | Valeur d'une option (null si non fournie) |
| `hasOption(string $key)` | `bool` | Option existe et a une valeur non vide ? |
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
| `./vendor/bin/directive make-directive {name}` | Crée une nouvelle directive (supporte les sous-dossiers) |
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