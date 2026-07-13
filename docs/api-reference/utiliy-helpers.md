# Classes Utilitaires - Référence Technique

Ce document couvre deux classes utilitaires essentielles du package `laravel-directive` : `EnvironmentDetector` pour la détection de l'environnement d'exécution, et `Paths` pour la résolution centralisée des chemins.

---

# EnvironmentDetector - Référence Technique

## Description

`EnvironmentDetector` est un utilitaire statique qui détermine le contexte d'exécution de l'application. Il analyse le système de fichiers, la configuration Composer et les variables d'environnement pour identifier si le code s'exécute dans une application Laravel web, un package/library, ou un contexte inconnu.

## Rôle principal

- Détecter si l'exécution est dans un **package/library**
- Détecter si l'exécution est dans une **application Laravel web**
- Fournir le **type d'application** sous forme de string ou enum
- Détecter les **environnements de test** et de **développement**

---

## API / Méthodes publiques

### `isPackage(): bool`

Détecte si l'exécution actuelle est dans un contexte package/library.

**Stratégies de détection :**
- Présence d'un dossier `vendor` à la racine du projet
- Type `library` ou `package` dans `composer.json`
- Nom du package contenant un slash (`/`)
- Répertoire courant dans `vendor/`

**Retourne :** `bool` - `true` si dans un contexte package

**Exemple :**
```php
if (EnvironmentDetector::isPackage()) {
    // Exécution dans un package
}
```

---

### `isWebApplication(): bool`

Détecte si l'exécution actuelle est dans une application Laravel web.

**Stratégies de détection :**
- Présence de `config/app.php`, `bootstrap/app.php`, `public/`
- Type `project` dans `composer.json`
- Dépendance `laravel/framework` dans Composer
- Présence du fichier `.env`

**Retourne :** `bool` - `true` si dans une application web

**Exemple :**
```php
if (EnvironmentDetector::isWebApplication()) {
    // Exécution dans une application Laravel
}
```

---

### `isLibrary(): bool`

Alias pour `isPackage()`.

**Retourne :** `bool` - `true` si dans un contexte library

---

### `getApplicationType(): string`

Retourne le type d'application sous forme de chaîne.

**Retourne :** `string` - Une des valeurs : `'web_application'`, `'package'`, `'unknown'`

**Exemple :**
```php
$type = EnvironmentDetector::getApplicationType();
// 'web_application', 'package', ou 'unknown'
```

---

### `getApplicationTypeEnum(): ApplicationType`

Retourne le type d'application sous forme d'énumération.

**Retourne :** `ApplicationType` - `WEB_APPLICATION`, `PACKAGE`, ou `UNKNOWN`

**Exemple :**
```php
$type = EnvironmentDetector::getApplicationTypeEnum();
if ($type === ApplicationType::WEB_APPLICATION) {
    // Application web
}
```

---

### `isTestEnvironment(): bool`

Vérifie si l'exécution actuelle est dans un environnement de test.

**Stratégies de détection :**
- PHPUnit installé (`PHPUNIT_COMPOSER_INSTALL` défini)
- Variable d'environnement `PHPUNIT_RUNNING` = `'true'`
- Variable d'environnement `APP_ENV` = `'testing'`

**Retourne :** `bool` - `true` si dans un environnement de test

**Exemple :**
```php
if (EnvironmentDetector::isTestEnvironment()) {
    // Exécution dans PHPUnit
}
```

---

### `isDevelopmentEnvironment(): bool`

Vérifie si l'exécution actuelle est dans un environnement de développement.

**Stratégies de détection :**
- `APP_ENV` = `'local'` ou `'development'`
- `APP_DEBUG` = `'true'`

**Retourne :** `bool` - `true` si dans un environnement de développement

**Exemple :**
```php
if (EnvironmentDetector::isDevelopmentEnvironment()) {
    // Mode développement actif
}
```

---

## Cas d'utilisation

### Cas 1 : Sélection de la factory appropriée

```php
use AndyDefer\Directive\Bootstrap\EnvironmentDetector;
use AndyDefer\Directive\Factories\InternalApplicationFactory;
use AndyDefer\Directive\Factories\ExternalApplicationFactory;

$app = EnvironmentDetector::isPackage()
    ? InternalApplicationFactory::create()
    : ExternalApplicationFactory::create();
```

---

### Cas 2 : Configuration conditionnelle

```php
use AndyDefer\Directive\Bootstrap\EnvironmentDetector;

if (EnvironmentDetector::isDevelopmentEnvironment()) {
    // Activer le débogage
    config(['app.debug' => true]);
}

if (EnvironmentDetector::isTestEnvironment()) {
    // Utiliser une base de données de test
    config(['database.default' => 'sqlite_testing']);
}
```

---

### Cas 3 : Logique d'initialisation

```php
use AndyDefer\Directive\Bootstrap\EnvironmentDetector;
use AndyDefer\Directive\Enums\ApplicationType;

$type = EnvironmentDetector::getApplicationTypeEnum();

switch ($type) {
    case ApplicationType::WEB_APPLICATION:
        // Initialisation pour application web
        break;
    case ApplicationType::PACKAGE:
        // Initialisation pour package
        break;
    default:
        // Initialisation par défaut
        break;
}
```

---

## Flux d'exécution

```
isPackage()
    ↓
rootPath = Paths::projectRoot()
    ↓
is_dir(rootPath . '/vendor') ?
    ├── Non → return false
    └── Oui ↓
isComposerPackage(rootPath)
    ├── file_exists(composer.json) ?
    │   └── Non → return false
    ├── json_decode(composer.json)
    ├── type === 'library' ou 'package' → true
    ├── name contient '/' → true
    └── return false
    ↓
str_contains(__DIR__, '/vendor/') ?
    ├── Oui → return true
    └── Non → return false
```

---

# Paths - Référence Technique

## Description

`Paths` est un utilitaire statique qui centralise la résolution de tous les chemins de fichiers utilisés par le package. Il fournit une source unique de vérité pour les chemins relatifs à la racine du projet.

## Rôle principal

- Résoudre la **racine du projet**
- Fournir les chemins vers les **fichiers clés** (`.env`, `composer.json`, `bootstrap/app.php`)
- Vérifier l'**existence** des fichiers
- Mettre en cache la **racine du projet** pour les performances

---

## API / Méthodes publiques

### `projectRoot(PathContextType $context = PathContextType::FILE_DIRECTORY): string`

Retourne le chemin absolu de la racine du projet.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$context` | `PathContextType` | Contexte de résolution (par défaut: `FILE_DIRECTORY`) |

**Retourne :** `string` - Chemin absolu de la racine du projet

**Exceptions :** `RuntimeException` - Si le répertoire courant ne peut être déterminé

**Exemple :**
```php
$root = Paths::projectRoot();
// /home/user/projects/my-app
```

---

### `packageRoot(): string`

Retourne le chemin absolu de la racine du package.

**Retourne :** `string` - Chemin absolu de la racine du package

**Exemple :**
```php
$packageRoot = Paths::packageRoot();
// /home/user/projects/packages/laravel-directive
```

---

### `envFile(): string`

Retourne le chemin absolu du fichier `.env`.

**Retourne :** `string` - Chemin absolu de `.env`

**Exemple :**
```php
$envFile = Paths::envFile();
// /home/user/projects/my-app/.env
```

---

### `projectAutoload(): string`

Retourne le chemin absolu de l'autoloader Composer du projet.

**Retourne :** `string` - Chemin absolu de `vendor/autoload.php`

**Exemple :**
```php
require Paths::projectAutoload();
```

---

### `packageAutoload(): string`

Retourne le chemin absolu de l'autoloader Composer du package.

**Retourne :** `string` - Chemin absolu de `vendor/autoload.php` du package

---

### `laravelBootstrap(): string`

Retourne le chemin absolu du fichier de bootstrap Laravel.

**Retourne :** `string` - Chemin absolu de `bootstrap/app.php`

**Exemple :**
```php
if (Paths::hasLaravelBootstrap()) {
    $app = require Paths::laravelBootstrap();
}
```

---

### `compiledProviders(): string`

Retourne le chemin absolu du fichier des providers compilés.

**Retourne :** `string` - Chemin absolu de `storage/framework/providers.php`

---

### `appConfig(): string`

Retourne le chemin absolu du fichier de configuration de l'application.

**Retourne :** `string` - Chemin absolu de `config/app.php`

---

### `hasEnvFile(): bool`

Vérifie si le fichier `.env` existe.

**Retourne :** `bool` - `true` si le fichier existe

---

### `hasProjectAutoload(): bool`

Vérifie si l'autoloader du projet existe.

**Retourne :** `bool` - `true` si le fichier existe

---

### `hasPackageAutoload(): bool`

Vérifie si l'autoloader du package existe.

**Retourne :** `bool` - `true` si le fichier existe

---

### `hasLaravelBootstrap(): bool`

Vérifie si le fichier de bootstrap Laravel existe.

**Retourne :** `bool` - `true` si le fichier existe

---

### `hasCompiledProviders(): bool`

Vérifie si le fichier des providers compilés existe.

**Retourne :** `bool` - `true` si le fichier existe

---

### `hasAppConfig(): bool`

Vérifie si le fichier de configuration de l'application existe.

**Retourne :** `bool` - `true` si le fichier existe

---

## Cas d'utilisation

### Cas 1 : Résolution de la racine du projet

```php
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\Directive\Enums\PathContextType;

// Résolution depuis le fichier actuel (par défaut)
$rootFromFile = Paths::projectRoot();

// Résolution depuis le répertoire de travail
$rootFromCwd = Paths::projectRoot(PathContextType::WORKING_DIRECTORY);
```

---

### Cas 2 : Vérification de l'existence des fichiers

```php
use AndyDefer\Directive\Helpers\Paths;

if (Paths::hasEnvFile()) {
    $env = parse_ini_file(Paths::envFile());
    // Traitement du .env
}

if (Paths::hasLaravelBootstrap()) {
    $app = require Paths::laravelBootstrap();
}
```

---

### Cas 3 : Chargement de l'autoloader

```php
use AndyDefer\Directive\Helpers\Paths;

if (Paths::hasProjectAutoload()) {
    require_once Paths::projectAutoload();
}

if (Paths::hasPackageAutoload() && 
    Paths::packageAutoload() !== Paths::projectAutoload()) {
    require_once Paths::packageAutoload();
}
```

---

### Cas 4 : Dans une factory d'application

```php
use AndyDefer\Directive\Helpers\Paths;
use Illuminate\Foundation\Application;

public static function create(): Application
{
    $cachePath = Paths::projectRoot() . '/bootstrap/cache';
    if (!is_dir($cachePath)) {
        mkdir($cachePath, 0755, true);
    }

    $app = Application::configure(basePath: Paths::projectRoot())
        ->create();

    return $app;
}
```

---

## Flux d'exécution

```
projectRoot()
    ↓
Cache existant ?
    ├── Oui → return cached
    └── Non ↓
$base = match ($context)
    ├── FILE_DIRECTORY → __DIR__
    └── WORKING_DIRECTORY → getcwd()
    ↓
$directory = realpath($base)
    ↓
while ($directory !== false && $directory !== '')
    ├── $composer = $directory . '/composer.json'
    ├── $vendor = $directory . '/vendor'
    ├── if (is_file($composer) && is_dir($vendor))
    │   └── cache = $directory → return $directory
    ├── $parent = dirname($directory)
    ├── if ($parent === $directory) break
    └── $directory = $parent
    ↓
return $base (fallback)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| getcwd() échoue | `RuntimeException` | `Unable to determine base directory` |
| realpath() échoue | `RuntimeException` | `Unable to resolve real path for: {path}` |

---

## Performance

- **Cache** : La racine du projet est mise en cache après la première résolution
- **Complexité** : O(n) où n est la profondeur de l'arborescence (limitée par la structure du projet)
- **Mémoire** : Minimale, un seul chemin en cache

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |

---

# TestHelper - Référence Technique

## Description

`TestHelper` est une classe utilitaire statique conçue pour faciliter les tests unitaires et d'intégration. Elle fournit des générateurs de contenu pour créer rapidement des fichiers de test, des directives factices et des structures de projet minimales.

## Rôle principal

- Générer du contenu pour les **fichiers de test**
- Créer des **directives factices** pour les tests
- Fournir des **structures de projet minimales**
- Générer des fichiers de **configuration** et **bootstrap**

---

## API / Méthodes publiques

### `getDirectories(): array`

Retourne une liste des répertoires standards d'un projet Laravel.

**Retourne :** `array` - Liste des répertoires

**Exemple :**
```php
$dirs = TestHelper::getDirectories();
// ['/app/Directives', '/bootstrap', '/config', ...]
```

---

### `createComposerJsonContent(): string`

Retourne le contenu d'un fichier `composer.json` minimal pour les tests.

**Retourne :** `string` - Contenu JSON du composer.json

**Exemple :**
```php
file_put_contents('/tmp/test/composer.json', TestHelper::createComposerJsonContent());
```

---

### `createAutoloadContent(): string`

Retourne le contenu d'un autoloader PHP minimal.

**Retourne :** `string` - Contenu PHP de l'autoloader

---

### `createBootstrapAppContent(): string`

Retourne le contenu d'un fichier `bootstrap/app.php` minimal.

**Retourne :** `string` - Contenu PHP du bootstrap

---

### `createConfigAppContent(): string`

Retourne le contenu d'un fichier `config/app.php` minimal.

**Retourne :** `string` - Contenu PHP du fichier de configuration

---

### `createAdminDirectiveContent(): string`

Retourne le contenu d'une directive `AdminDirective`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createDirective(string $className, string $signature, string $description, string $executeContent, array $aliases = []): string`

Crée une directive sur mesure pour les tests.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$className` | `string` | Nom de la classe |
| `$signature` | `string` | Signature de la directive |
| `$description` | `string` | Description |
| `$executeContent` | `string` | Contenu de la méthode `execute()` |
| `$aliases` | `array` | Alias optionnels |

**Retourne :** `string` - Contenu PHP complet de la directive

**Exemple :**
```php
$content = TestHelper::createDirective(
    'HelloDirective',
    'hello {name}',
    'Say hello',
    '$this->info("Hello " . $this->getArgument("name")); return ExitCode::SUCCESS;',
    ['hi', 'bonjour']
);
```

---

### `createUserCreateDirective(): string`

Retourne le contenu d'une directive `UserCreateDirective`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createCacheClearDirective(): string`

Retourne le contenu d'une directive `CacheClearDirective`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createAliasTestDirective(): string`

Retourne le contenu d'une directive avec alias pour les tests.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createEchoDirective(): string`

Retourne le contenu d'une directive `EchoDirective`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createGreetingDirective(): string`

Retourne le contenu d'une directive `GreetingDirective`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createCalculatorDirective(): string`

Retourne le contenu d'une directive calculatrice.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createVariadicDirective(): string`

Retourne le contenu d'une directive avec arguments variadiques.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createHelperFileContent(): string`

Retourne le contenu d'un fichier helper (sans classe).

**Retourne :** `string` - Contenu PHP du helper

---

### `createNoNamespaceDirectiveContent(): string`

Retourne le contenu d'une directive sans namespace.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createAbstractDirectiveContent(): string`

Retourne le contenu d'une directive abstraite.

**Retourne :** `string` - Contenu PHP de la directive abstraite

---

### `createNonDirectiveClassContent(): string`

Retourne le contenu d'une classe qui n'est pas une directive.

**Retourne :** `string` - Contenu PHP de la classe

---

### `createDeepDirectiveContent(): string`

Retourne le contenu d'une directive dans un namespace profond.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createBeforeAfterDirective(): string`

Retourne le contenu d'une directive avec hooks before/after.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createBeforeFailingDirective(): string`

Retourne le contenu d'une directive qui échoue dans `beforeExecute()`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createAfterFailingDirective(): string`

Retourne le contenu d'une directive qui échoue dans `afterExecute()`.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createNestedBeforeAfterDirective(): string`

Retourne le contenu d'une directive avec hooks before/after imbriqués.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createParentDirective(): string`

Retourne le contenu d'une directive parent qui appelle des enfants.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createCircularDirective(): string`

Retourne le contenu d'une directive avec appel circulaire.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createTestCallDirective(): string`

Retourne le contenu d'une directive de test avec appels.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createTestConcreteDirective(): string`

Retourne le contenu d'une directive concrète pour les tests.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createTestEchoDirective(): string`

Retourne le contenu d'une directive echo pour les tests.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createTestGreetingDirective(): string`

Retourne le contenu d'une directive greeting pour les tests.

**Retourne :** `string` - Contenu PHP de la directive

---

### `createTestDirective(): string`

Retourne le contenu d'une directive de test générique.

**Retourne :** `string` - Contenu PHP de la directive

---

## Cas d'utilisation

### Cas 1 : Création d'un environnement de test

```php
use AndyDefer\Directive\Helpers\TestHelper;
use AndyDefer\Directive\Helpers\Paths;

// Créer la structure de test
$testDir = '/tmp/test-project';
mkdir($testDir . '/app/Directives', 0777, true);

// Fichiers nécessaires
file_put_contents($testDir . '/composer.json', TestHelper::createComposerJsonContent());
file_put_contents($testDir . '/bootstrap/app.php', TestHelper::createBootstrapAppContent());
file_put_contents($testDir . '/config/app.php', TestHelper::createConfigAppContent());
```

---

### Cas 2 : Création de directives pour les tests

```php
use AndyDefer\Directive\Helpers\TestHelper;

// Créer une directive de test
$content = TestHelper::createTestDirective();
file_put_contents('/tmp/test/TestDirective.php', $content);

// Ou utiliser une directive spécifique
$content = TestHelper::createCalculatorDirective();
file_put_contents('/tmp/test/CalculatorDirective.php', $content);
```

---

### Cas 3 : Tests de découverte

```php
use AndyDefer\Directive\Helpers\TestHelper;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;

// Créer plusieurs directives pour tester la découverte
$directives = [
    TestHelper::createGreetingDirective(),
    TestHelper::createEchoDirective(),
    TestHelper::createVariadicDirective(),
];

foreach ($directives as $name => $content) {
    file_put_contents("/tmp/test/Directives/{$name}.php", $content);
}

// Découvrir les directives
$discovery = DirectiveDiscoveryService::init($app);
$discovery->addSource('/tmp/test/Directives');
$collection = $discovery->discover();
```

---

## Voir aussi

- `EnvironmentDetector` - Détection de l'environnement
- `Paths` - Résolution des chemins
- `PathContextType` - Énumération des contextes de chemin
- `ApplicationType` - Énumération des types d'application