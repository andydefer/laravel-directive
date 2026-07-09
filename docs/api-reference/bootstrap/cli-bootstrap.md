# CliBootstrap - Référence Technique

## Description

Service de démarrage de l'application Laravel pour l'exécuteur CLI des directives. Gère le chargement de l'environnement, l'enregistrement de l'autoloader, la création de l'application, l'enregistrement des providers et le bootstrap de l'application.

## Hiérarchie / Implémentations

```
CliBootstrap
    ├── Environment (fichier .env)
    ├── Autoloader (Composer)
    ├── Application (Laravel)
    ├── Providers (Service Providers)
    └── Kernel (Console Kernel)
```

## Rôle principal

`CliBootstrap` est le point d'entrée pour l'intégration avec Laravel. Il permet de :

- Charger automatiquement le fichier `.env`
- Charger l'autoloader Composer
- Créer une instance de l'application Laravel
- Enregistrer les service providers (depuis le cache ou la configuration)
- Bootstrapper l'application Laravel
- Exécuter les directives avec le contexte Laravel

## Installation

```bash
composer require andydefer/directive
```

### Dépendances

- `Application` (Laravel) - Conteneur d'application
- `Kernel` (Laravel) - Console Kernel
- `DirectiveKernel` - Noyau des directives
- `LaravelContainerAdapter` - Adaptateur de conteneur
- PHP 8.1+

## API / Méthodes publiques

### `__construct(Application $app)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$app` | `Application` | Instance de l'application Laravel |

**Retourne :** `void`

**Exemple :**
```php
$app = require __DIR__ . '/bootstrap/app.php';
$bootstrap = new CliBootstrap($app);
```

---

### `run(array $arguments): int`

Exécute l'exécuteur CLI avec les arguments donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$arguments` | `array<int, string>` | Arguments de la ligne de commande |

**Retourne :** `int` - Code de sortie

**Exceptions :** Aucune

**Exemple :**
```php
$exitCode = $bootstrap->run($_SERVER['argv']);
exit($exitCode);
```

---

### `static create(): self`

Crée une instance bootstrapée de manière complète.

**Retourne :** `self` - Nouvelle instance avec application bootstrapée

**Exceptions :** `BootstrapException` - Si le bootstrap échoue

**Exemple :**
```php
$bootstrap = CliBootstrap::create();
$exitCode = $bootstrap->run(['directive', 'list']);
```

---

## Cas d'utilisation

### Cas 1 : Utilisation standard dans un script CLI

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Bootstrap complet de Laravel + Directive
$bootstrap = CliBootstrap::create();

// Exécuter avec les arguments de la ligne de commande
$exitCode = $bootstrap->run($argv);
exit($exitCode);
```

### Cas 2 : Utilisation avec une application déjà créée

```php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Application déjà existante
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Utiliser le bootstrap
$bootstrap = new CliBootstrap($app);
$exitCode = $bootstrap->run(['directive', 'help']);
```

### Cas 3 : Structure de répertoires Laravel

```
project/
├── .env                    # Variables d'environnement
├── vendor/
│   └── autoload.php        # Autoloader Composer
├── bootstrap/
│   ├── app.php             # Bootstrap Laravel
│   └── cache/
│       └── packages.php    # Providers compilés
├── config/
│   └── app.php             # Configuration applicative
└── bin/
    └── directive           # Script CLI
```

### Cas 4 : Exécution avec environnement personnalisé

```php
<?php

// .env personnalisé pour le CLI
// .env.cli
APP_ENV=cli
APP_DEBUG=false
LOG_CHANNEL=stderr

// Le bootstrap charge automatiquement .env.cli
// si vous définissez la variable d'environnement APP_ENV
putenv('APP_ENV=cli');
$bootstrap = CliBootstrap::create();
```

---

## Flux d'exécution

```
CliBootstrap::create()
    ↓
loadEnvironment()
    ├── Vérifier .env
    ├── Lire les lignes
    ├── Filtrer les commentaires
    └── putenv() pour chaque variable
    ↓
loadAutoloader()
    ├── Vérifier vendor/autoload.php
    ├── Exception si absent
    ├── Require vendor/autoload.php
    └── Require package autoload (si différent)
    ↓
createApplication()
    ├── Vérifier bootstrap/app.php
    ├── Exception si absent
    ├── Require bootstrap/app.php
    ├── Vérifier le type de retour
    └── Retourner l'application
    ↓
registerProviders($app)
    ├── resolveProvidersFromStorage()
    │   └── bootstrap/cache/packages.php
    ├── resolveProvidersFromConfig()
    │   └── config/app.php
    ├── Fusionner les providers
    ├── Filtrer les classes valides
    └── $app->register() pour chaque provider
    ↓
bootApplication($app)
    └── $app->make(Kernel::class)->bootstrap()
    ↓
new CliBootstrap($app)
    ↓
run($arguments)
    ├── new LaravelContainerAdapter($app)
    ├── DirectiveKernel::init($adapter)
    └── $kernel->run($arguments)->value
```

### Détail du chargement des providers

```
registerProviders($app)
    ↓
resolveProvidersFromStorage()
    ├── bootstrap/cache/packages.php
    ├── Extraire 'providers'
    └── Extraire 'andydefer/laravel-directive.providers'
    ↓
resolveProvidersFromConfig()
    ├── config/app.php
    └── Extraire 'providers'
    ↓
array_merge($storage, $config)
    ↓
filterValidProviders()
    ├── Filtrer les chaînes
    └── Vérifier class_exists()
    ↓
Pour chaque provider
    └── $app->register($provider)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Autoloader introuvable | `BootstrapException` | `Autoloader not found at [vendor/autoload.php]. Run 'composer install' first.` |
| Bootstrap Laravel introuvable | `BootstrapException` | `Laravel bootstrap file not found at [bootstrap/app.php].` |
| Bootstrap ne retourne pas Application | `BootstrapException` | `Bootstrap file must return an instance of Application` |
| Erreur de chargement .env | Ignorée | Aucune exception (optionnel) |

---

## Intégration

### Avec le script bin/directive

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Path helpers
$projectRoot = dirname(__DIR__);

// Configuration de l'environnement
putenv("PROJECT_ROOT=$projectRoot");

// Bootstrap
try {
    $bootstrap = CliBootstrap::create();
    $exitCode = $bootstrap->run($argv);
    exit($exitCode);
} catch (BootstrapException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(255);
}
```

### Avec Laravel Service Provider

```php
<?php

namespace App\Providers;

use AndyDefer\Directive\Bootstrap\CliBootstrap;
use Illuminate\Support\ServiceProvider;

class DirectiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CliBootstrap::class, function ($app) {
            return new CliBootstrap($app);
        });
    }
}
```

### Avec des paths personnalisés

```php
<?php

// Paths personnalisés
class CustomPaths
{
    public static function root(): string
    {
        return '/custom/project/root';
    }
    
    public static function envFile(): string
    {
        return self::root() . '/.env.cli';
    }
    
    public static function autoload(): string
    {
        return self::root() . '/vendor/autoload.php';
    }
    
    public static function bootstrap(): string
    {
        return self::root() . '/bootstrap/app.php';
    }
}

// Utilisation avec CliBootstrap
// Note: CliBootstrap utilise Paths, qui peut être étendu
```

---

## Performance

| Opération | Complexité | Détails |
|-----------|------------|---------|
| `create()` | O(n) | n = nombre de providers |
| `run()` | O(1) + exécution | Dépend de la directive |
| `loadEnvironment()` | O(n) | n = lignes du .env |
| `registerProviders()` | O(n) | n = nombre de providers |

**Optimisations :**
- Utilisation du cache des providers compilés (packages.php)
- Les providers sont résolus une seule fois
- Pas de rechargement du .env à chaque exécution

---

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.4 | ✅ Complet | Support total |
| PHP 8.3 | ✅ Complet | Support total |
| PHP 8.2 | ✅ Complet | Support total |
| PHP 8.1 | ✅ Complet | Support total |

| Version Laravel | Support | Notes |
|-----------------|---------|-------|
| Laravel 12.x | ✅ Complet | Support total |
| Laravel 13.x | ✅ Complet | Support total |
| Laravel 14.x | ✅ Complet | Support total |
| Laravel 15.x | ✅ Complet | Support total |

---

## Exemple complet

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\CliBootstrap;
use AndyDefer\Directive\Exceptions\BootstrapException;

// 1. Définition des chemins
$projectRoot = dirname(__DIR__);

// 2. Configuration de l'environnement
putenv("PROJECT_ROOT=$projectRoot");
putenv('APP_ENV=cli');

// 3. Vérification préalable
$envFile = $projectRoot . '/.env';
if (file_exists($envFile)) {
    putenv("ENV_FILE=$envFile");
}

// 4. Bootstrap
echo "=== Bootstrap Laravel + Directive ===\n";

try {
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    // Création du bootstrap
    echo "📦 Création de l'application...\n";
    $bootstrap = CliBootstrap::create();
    
    $bootstrapTime = microtime(true) - $startTime;
    $bootstrapMemory = memory_get_usage() - $startMemory;
    
    echo "✅ Application créée en " . round($bootstrapTime * 1000, 2) . " ms\n";
    echo "   Mémoire utilisée: " . round($bootstrapMemory / 1024, 2) . " KB\n\n";
    
    // 5. Affichage des informations
    $app = $bootstrap->app;
    echo "=== Informations applicatives ===\n";
    echo "Environnement: " . $app->environment() . "\n";
    echo "Version Laravel: " . $app->version() . "\n";
    echo "Base path: " . $app->basePath() . "\n";
    echo "Storage path: " . $app->storagePath() . "\n\n";
    
    // 6. Exécution des commandes
    echo "=== Exécution des commandes ===\n\n";
    
    // 6a. Help
    echo "--- Help ---\n";
    $exitCode = $bootstrap->run(['directive', 'help']);
    echo "Exit code: $exitCode\n\n";
    
    // 6b. Version
    echo "--- Version ---\n";
    $exitCode = $bootstrap->run(['directive', 'version']);
    echo "Exit code: $exitCode\n\n";
    
    // 6c. List
    echo "--- List (short) ---\n";
    $exitCode = $bootstrap->run(['directive', 'list', '--short']);
    echo "Exit code: $exitCode\n\n";
    
    // 6d. Clean logs (dry-run)
    echo "--- Clean Logs (dry-run) ---\n";
    $exitCode = $bootstrap->run(['directive', 'clean-logs', '--dry-run']);
    echo "Exit code: $exitCode\n\n";
    
    // 7. Exécution avec arguments personnalisés
    if (isset($argv[1]) && $argv[1] === '--custom') {
        echo "--- Exécution personnalisée ---\n";
        $exitCode = $bootstrap->run(array_slice($argv, 2));
        echo "Exit code: $exitCode\n\n";
    }
    
    // 8. Statistiques finales
    $totalTime = microtime(true) - $startTime;
    $totalMemory = memory_get_usage() - $startMemory;
    
    echo "=== Statistiques globales ===\n";
    echo "Temps total: " . round($totalTime * 1000, 2) . " ms\n";
    echo "Mémoire utilisée: " . round($totalMemory / 1024, 2) . " KB\n";
    echo "Mémoire pic: " . round(memory_get_peak_usage() / 1024, 2) . " KB\n";
    
} catch (BootstrapException $e) {
    fwrite(STDERR, "\n❌ Bootstrap error: " . $e->getMessage() . "\n");
    
    // Suggestions
    if (str_contains($e->getMessage(), 'Autoloader')) {
        fwrite(STDERR, "\n💡 Run 'composer install' to install dependencies.\n");
    }
    if (str_contains($e->getMessage(), 'bootstrap/app.php')) {
        fwrite(STDERR, "\n💡 Laravel application not found. Are you in a Laravel project?\n");
    }
    
    exit(255);
} catch (Throwable $e) {
    fwrite(STDERR, "\n❌ Fatal error: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(255);
}
```

## Voir aussi

- `Paths` - Gestion des chemins du projet
- `BootstrapException` - Exception de bootstrap
- `LaravelContainerAdapter` - Adaptateur de conteneur
- `DirectiveKernel` - Noyau des directives
- `Application` (Laravel) - Interface de l'application