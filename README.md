# Laravel Directive

**Un système de commandes CLI flexible pour Laravel qui brise les contraintes d'Artisan. Découverte automatique, signatures avancées, appels internes, tests isolés - avec un simple binaire.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20%7C%2010.x%20%7C%2011.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Pourquoi Laravel Directive ?](#pourquoi-laravel-directive-)
3. [Créer une directive](#créer-une-directive)
4. [Signature des directives](#signature-des-directives)
5. [Arguments et options](#arguments-et-options)
6. [Alias de commandes](#alias-de-commandes)
7. [Appels internes](#appels-internes)
8. [Sorties et interactions](#sorties-et-interactions)
9. [Découverte automatique](#découverte-automatique)
10. [Tester vos directives](#tester-vos-directives)
11. [Référence des commandes](#référence-des-commandes)
12. [Bonnes pratiques](#bonnes-pratiques)

---

## Installation

```bash
composer require andydefer/laravel-directive
```

**Prérequis :** PHP 8.1+ | Laravel 9.x, 10.x ou 11.x

### Publication de la configuration (optionnelle)

```bash
php artisan vendor:publish --tag=directive-config
```

---

## Pourquoi Laravel Directive ?

**Le problème :** Artisan vous force à étendre `Illuminate\Console\Command`, mêle logique métier et présentation, et rend les tests difficiles.

**La solution :** Laravel Directive. Une architecture propre, découplée et testable.

### Comparatif rapide

| Besoin | Artisan | Laravel Directive |
|--------|---------|-------------------|
| Héritage flexible | ❌ Imposé | ✅ Vous choisissez |
| Logique / Présentation séparées | ❌ Mélangées | ✅ Séparées |
| Tests unitaires | ❌ Difficiles | ✅ Faciles |
| Découverte automatique | ❌ Manuelle | ✅ Automatique |
| Bootstrap Laravel | ✅ Toujours | ✅ À la demande |
| API typée | ❌ Tableau brut | ✅ Typage fort |

---

## Créer une directive

### 1. Créer la classe

```php
<?php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

final class GreetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'greet {name}';
    }

    public function getDescription(): string
    {
        return 'Say hello to someone';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['hello', 'salut']);
    }

    protected function execute(): ExitCode
    {
        $name = $this->argument('name');
        $this->info("Hello, {$name}!");
        return ExitCode::SUCCESS;
    }
}
```

### 2. Exécuter la directive

```bash
# Par son nom
./vendor/bin/directive greet John

# Par son alias
./vendor/bin/directive hello Jane

# Avec aide
./vendor/bin/directive greet --help
```

---

## Signature des directives

### Syntaxe

```
commande {argument} {argument?} {argument=default} {--flag} {--flag=value} {arguments*}
```

### Éléments supportés

| Élément | Syntaxe | Exemple |
|---------|---------|---------|
| Arguments requis | `{nom}` | `{name}` |
| Arguments optionnels | `{nom?}` | `{name?}` |
| Arguments par défaut | `{nom=default}` | `{name=John}` |
| Flags booléens | `{--nom}` | `{--force}` |
| Flags avec valeur | `{--nom=valeur}` | `{--format=gzip}` |
| Arguments variadiques | `{nom*}` | `{files*}` |

### Exemples

```php
// Directive simple
public function getSignature(): string
{
    return 'list';
}

// Avec arguments
public function getSignature(): string
{
    return 'user:create {name} {email}';
}

// Avec options
public function getSignature(): string
{
    return 'backup {file?} {--force} {--compression=gzip}';
}

// Avec arguments variadiques
public function getSignature(): string
{
    return 'copy {source*} {--recursive}';
}
```

---

## Arguments et options

### Accès aux arguments

```php
protected function execute(): ExitCode
{
    // Argument requis
    $name = $this->argument('name');
    
    // Argument optionnel avec valeur par défaut
    $limit = $this->argument('limit') ?? 10;
    
    // Vérifier l'existence
    if ($this->hasArgument('email')) {
        $email = $this->argument('email');
    }
    
    // Arguments variadiques
    $files = $this->getVariadicArguments();
    foreach ($files as $file) {
        $this->line("Processing: {$file}");
    }
    
    return ExitCode::SUCCESS;
}
```

### Accès aux options (flags)

```php
protected function execute(): ExitCode
{
    // Flag booléen
    if ($this->flag('force')) {
        $this->line('Mode forcé activé');
    }
    
    // Flag avec valeur
    $format = $this->flag('format') ?? 'json';
    
    // Vérifier l'existence d'un flag
    if ($this->hasFlag('verbose')) {
        $this->line('Mode verbeux');
    }
    
    // Vérifier si un flag est actif
    if ($this->isFlagActive('admin')) {
        // Exécuter en mode admin
    }
    
    return ExitCode::SUCCESS;
}
```

### Méthodes utilitaires

```php
// Tous les arguments requis
$required = $this->getRequiredArguments();

// Tous les arguments par défaut
$defaults = $this->getDefaultArguments();

// Tous les flags
$flags = $this->getFlags();

// Les flags actifs
$activeFlags = $this->getActiveFlags();

// Vérifications
$hasRequired = $this->hasRequireds();
$hasDefaults = $this->hasDefaults();
$hasFlags = $this->hasFlags();
```

---

## Alias de commandes

### Définir des alias

```php
public function getAliases(): StringTypedCollection
{
    return StringTypedCollection::from(['ls', '-l', '--list']);
}
```

### Utilisation

```bash
# Toutes ces commandes sont équivalentes
./vendor/bin/directive list
./vendor/bin/directive ls
./vendor/bin/directive -l
./vendor/bin/directive --list
```

---

## Appels internes

### Appeler une autre directive

```php
protected function execute(): ExitCode
{
    $this->info('Déploiement en cours...');
    
    // Appeler d'autres directives
    $this->call('cache:clear');
    $this->call('config:cache');
    
    if ($this->flag('migrate')) {
        $this->call('db:migrate --force');
    }
    
    $this->info('Déploiement terminé');
    return ExitCode::SUCCESS;
}
```

### Détection des appels circulaires

Le système détecte automatiquement les appels circulaires :

```php
// Directive A
protected function execute(): ExitCode
{
    $this->call('b'); // Appelle B
    return ExitCode::SUCCESS;
}

// Directive B
protected function execute(): ExitCode
{
    $this->call('a'); // Appelle A → Détection de cycle !
    return ExitCode::SUCCESS;
}
```

**Résultat :** `Circular call detected: b` → `ExitCode::CONFLICT`

---

## Sorties et interactions

### Méthodes de sortie

```php
// Texte brut
$this->line('Message simple');

// Information (vert)
$this->info('Succès !');

// Erreur (rouge)
$this->error('Une erreur est survenue');

// Avertissement (jaune)
$this->warn('Attention');

// Ligne vide
$this->newLine();

// Séparateur
$this->separator('=', 50);

// Tableau
$this->table(
    ['ID', 'Nom', 'Email'],
    [
        [1, 'John Doe', 'john@example.com'],
        [2, 'Jane Doe', 'jane@example.com'],
    ]
);
```

### Interactions utilisateur

```php
// Question simple
$name = $this->ask('Quel est votre nom ?');

// Confirmation
if ($this->confirm('Continuer ?')) {
    $this->info('Continuité...');
}

// Choix (via askUserChoice)
$this->line('1. Option A');
$this->line('2. Option B');
$this->line('3. Quitter');

$choice = $this->askUserChoice('Sélectionnez une option', 3);
```

### Hooks d'exécution

```php
// Avant l'exécution
protected function beforeExecute(): void
{
    $this->line('=== DÉBUT ===');
}

// Après l'exécution
protected function afterExecute(ExitCode $exitCode): void
{
    if ($exitCode->isSuccess()) {
        $this->info('✅ Succès');
    } else {
        $this->error('❌ Échec');
    }
}
```

---

## Découverte automatique

Les directives sont découvertes automatiquement dans :

| Source | Dossier | Priorité |
|--------|---------|----------|
| **Built-in** | (intégrées) | 1 (la plus haute) |
| **Workspace** | `app/Directives/` | 2 |
| **Workspace** | `src/Directives/` | 3 |
| **Vendors** | `vendor/*/src/Directives/` | 4 |
| **Custom** | Configuration | 5 |

### Ajouter des sources personnalisées

```php
// Dans un ServiceProvider
$discovery = $this->app->make(DirectiveDiscoveryService::class);
$discovery->addSources([
    base_path('modules/Admin/Directives'),
    base_path('packages/Acme/Directives'),
]);
```

### Configuration personnalisée

```php
// config/directive.php
return [
    'directories' => [
        'app/Directives',
        'app/Console/Directives',
        'modules/*/Directives',
    ],
    'custom_sources' => [
        'app/CustomDirectives',
    ],
    'max_depth' => 3,
    'debug' => env('DIRECTIVE_DEBUG', false),
];
```

---

## Tester vos directives

### Installation des dépendances de test

```bash
composer require --dev phpunit/phpunit orchestra/testbench
```

### Test unitaire simple

```php
<?php

namespace Tests\Unit\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use PHPUnit\Framework\TestCase;
use App\Directives\GreetDirective;

final class GreetDirectiveTest extends TestCase
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

    public function test_greet_directive(): void
    {
        $response = $this->service->run('greet John');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, John!', $response->output);
    }

    public function test_greet_directive_with_alias(): void
    {
        $response = $this->service->run('hello Jane');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Hello, Jane!', $response->output);
    }
}
```

### Test d'intégration avec Laravel

```php
<?php

namespace Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use Tests\IntegrationTestCase;
use App\Models\User;
use App\Directives\UserCreateDirective;

final class UserCreateDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveTestingService($this->app);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    public function test_create_user(): void
    {
        $response = $this->service->run('user:create John john@example.com --role=admin');
        
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }
}
```

### Environnement isolé

`DirectiveTestingService` crée automatiquement un environnement isolé :

```php
$service = new DirectiveTestingService();

// Les fichiers sont créés dans /tmp/directive_test_xxx/
// Le répertoire est automatiquement supprimé par destroy()
```

---

## Référence des commandes

### Commandes intégrées

| Commande | Description | Aliases |
|----------|-------------|---------|
| `help` | Affiche l'aide | `-h`, `--help` |
| `list` | Liste les directives disponibles | `ls`, `-l`, `--list` |
| `version` | Affiche la version | `-v`, `--version` |

### Exécution

```bash
# Aide
./vendor/bin/directive --help
./vendor/bin/directive -h

# Liste des directives
./vendor/bin/directive --list
./vendor/bin/directive -l

# Version
./vendor/bin/directive --version
./vendor/bin/directive -v

# Directive personnalisée
./vendor/bin/directive ma:commande --option valeur

# Avec arguments variadiques
./vendor/bin/directive copy file1.txt file2.txt file3.txt --recursive
```

### Codes de sortie

| Code | Constante | Signification |
|------|-----------|---------------|
| 0 | `SUCCESS` | Succès |
| 1 | `FAILURE` | Erreur générale |
| 2 | `INVALID_ARGUMENT` | Argument invalide |
| 3 | `NOT_FOUND` | Directive non trouvée |
| 4 | `PERMISSION_DENIED` | Permission refusée |
| 5 | `RUNTIME_ERROR` | Erreur d'exécution |
| 6 | `INVALID_SIGNATURE` | Signature invalide |
| 7 | `CONFLICT` | Conflit (appel circulaire) |
| 8 | `DEPENDENCY_ERROR` | Erreur de dépendance |

---

## Bonnes pratiques

### ✅ Organisation des directives

```
app/
├── Directives/
│   ├── User/
│   │   ├── CreateDirective.php
│   │   ├── ListDirective.php
│   │   └── DeleteDirective.php
│   ├── System/
│   │   ├── CacheClearDirective.php
│   │   └── ConfigCacheDirective.php
│   └── Maintenance/
│       └── BackupDirective.php
```

### ✅ Nommage des signatures

```php
// ✅ Bon
'user:create {name} {email}'
'cache:clear --force'
'backup {--compression=gzip}'

// ❌ À éviter
'user_create {name}'          // Utilisez ':' pour les catégories
'cache clear'                 // Pas d'espaces
'backup {--compression gzip}' // Syntaxe incorrecte
```

### ✅ Injection de dépendances

```php
final class UserCreateDirective extends AbstractDirective
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Mailer $mailer,
    ) {
        parent::__construct(...func_get_args());
    }
    
    protected function execute(): ExitCode
    {
        // Utiliser les dépendances injectées
        $user = $this->users->create($this->argument('name'));
        $this->mailer->sendWelcome($user);
        
        return ExitCode::SUCCESS;
    }
}
```

### ✅ Gestion des erreurs

```php
protected function execute(): ExitCode
{
    try {
        $this->performAction();
        $this->info('Succès');
        return ExitCode::SUCCESS;
    } catch (ValidationException $e) {
        $this->error($e->getMessage());
        return ExitCode::INVALID_ARGUMENT;
    } catch (Exception $e) {
        $this->error('Erreur inattendue: ' . $e->getMessage());
        return ExitCode::RUNTIME_ERROR;
    }
}
```

### ✅ Tests

```php
public function test_directive_success(): void
{
    $response = $this->service->run('my:command --option value');
    
    $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
    $this->assertStringContainsString('Expected output', $response->output);
}

public function test_directive_failure(): void
{
    $response = $this->service->run('my:command invalid');
    
    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
    $this->assertStringContainsString('Error message', $response->output);
}
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
