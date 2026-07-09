# CliBootstrap - Référence Technique

## Description

Classe responsable du démarrage complet de l'application Laravel pour l'exécution des commandes Directive en CLI. Elle gère le chargement de l'environnement, l'autoloader Composer, la création de l'application, l'enregistrement des providers et le bootstrapping.

## Hiérarchie / Implémentations

```
final readonly class CliBootstrap
```

- **Final** : Ne peut pas être étendue
- **Readonly** : Toutes les propriétés sont en lecture seule

## Rôle principal

Servir de point d'entrée unique pour le lancement de l'application Laravel en mode CLI. Elle encapsule toute la logique de démarrage nécessaire pour que les directives puissent s'exécuter dans un contexte Laravel complet, même sans serveur web.

## Installation

Cette classe est utilisée automatiquement par le point d'entrée CLI du package.

```bash
# Le package est installé via Composer
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `run(array $arguments): int`

Exécute le runner CLI avec les arguments fournis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$arguments` | `array<int, string>` | Les arguments de la ligne de commande (ex: `['directive', 'list']`) |

**Retourne :** `int` - Le code de sortie (0 = succès, >0 = erreur)

**Exceptions :** Aucune exception directe, mais la méthode peut propager les exceptions du `CliRunner`

**Exemple :**
```php
<?php

$bootstrap = CliBootstrap::create();
$exitCode = $bootstrap->run(['directive', 'list']);
```

---

### `create(): self`

Crée une instance de `CliBootstrap` avec une application Laravel entièrement bootstrappée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `self` - Une nouvelle instance configurée

**Exceptions :** `BootstrapException` - Si une étape du bootstrap échoue (autoloader manquant, fichier bootstrap introuvable, etc.)

**Exemple :**
```php
<?php

$bootstrap = CliBootstrap::create();
// L'application est maintenant prête à exécuter des directives
```

## Cas d'utilisation

### Cas 1 : Lancement d'une directive simple

```php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Créer le bootstrap avec l'application chargée
$bootstrap = CliBootstrap::create();

// Exécuter la directive "list"
$exitCode = $bootstrap->run(['directive', 'list']);

// Vérifier le résultat
if ($exitCode === 0) {
    echo "Commande exécutée avec succès";
}
```

### Cas 2 : Intégration dans un script personnalisé

```php
#!/usr/bin/env php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

try {
    $bootstrap = CliBootstrap::create();
    $exitCode = $bootstrap->run($argv);
    exit($exitCode);
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
    exit(1);
}
```

### Cas 3 : Exécution avec arguments complexes

```php
<?php

$bootstrap = CliBootstrap::create();

// Arguments avec option et valeur
$args = [
    'directive',
    'db:backup',
    '--force',
    '--compression=gzip',
    '--format=sql'
];

$exitCode = $bootstrap->run($args);
```

## Flux d'exécution

```
CliBootstrap::create()
    │
    ├── loadEnvironment()
    │   └── .env → putenv()
    │
    ├── loadAutoloader()
    │   ├── vendor/autoload.php → require_once
    │   └── vendor/autoload.php (package) → require_once
    │
    ├── createApplication()
    │   ├── bootstrap/app.php → require
    │   └── Vérifie instanceof Application
    │
    ├── registerProviders()
    │   ├── resolveProvidersFromStorage()
    │   │   └── storage/framework/providers.php
    │   ├── resolveProvidersFromConfig()
    │   │   └── config/app.php → providers[]
    │   └── filterValidProviders()
    │
    ├── bootApplication()
    │   └── Kernel::bootstrap()
    │
    └── new self($app)

CliBootstrap::run($argv)
    │
    ├── $this->app->make(CliRunner::class)
    └── $runner->run($arguments)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Autoloader non trouvé | `BootstrapException` | `Autoloader not found at [PATH]. Run 'composer install' first.` |
| Fichier bootstrap Laravel manquant | `BootstrapException` | `Laravel bootstrap file not found at [PATH].` |
| Bootstrap ne retourne pas une instance de `Application` | `BootstrapException` | `Bootstrap file must return an instance of Illuminate\Contracts\Foundation\Application` |
| Répertoire courant non déterminable | `RuntimeException` | `Unable to determine current working directory` |

## Intégration

La classe `CliBootstrap` s'intègre avec les composants suivants :

- **`Paths`** : Utilisée pour résoudre tous les chemins de fichiers
- **`CliRunner`** : Construite via le conteneur et exécutée avec les arguments
- **Application Laravel** : Créée et bootstrappée via le conteneur
- **Service Providers** : Chargés depuis le stockage et la configuration

## Performance

- **Temps de chargement** : ~200-500ms (dépend du nombre de providers)
- **Mémoire** : ~4-8 MB (application Laravel chargée)
- **Cache** : Utilise le fichier `storage/framework/providers.php` pour accélérer le chargement des providers
- **Optimisation** : Le bootstrap est effectué une seule fois par instance

### Points d'attention

- Le chargement de l'environnement `.env` utilise `putenv()` qui peut être désactivé sur certains environnements
- Les providers sont chargés depuis deux sources (storage + config) ce qui peut causer des doublons si mal configuré

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| Laravel 9.x | ✅ Testé |
| Laravel 10.x | ✅ Testé |
| Laravel 11.x | ✅ Testé |

## Exemple complet

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\CliBootstrap;
use AndyDefer\Directive\Exceptions\BootstrapException;

try {
    // Créer le bootstrap
    $bootstrap = CliBootstrap::create();
    
    // Exécuter avec les arguments reçus
    $exitCode = $bootstrap->run($argv);
    
    // Terminer avec le code approprié
    exit($exitCode);
    
} catch (BootstrapException $e) {
    // Erreur de bootstrap
    fwrite(STDERR, "❌ Bootstrap error: " . $e->getMessage() . PHP_EOL);
    exit(1);
    
} catch (Throwable $e) {
    // Erreur inattendue
    fwrite(STDERR, "💥 Unexpected error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
```

## Notes de sécurité

⚠️ **Important** : Cette classe utilise `putenv()` pour charger les variables d'environnement. Assurez-vous que cette fonction n'est pas désactivée dans votre environnement (souvent le cas dans les environnements de production avec `disable_functions`).

⚠️ **Provider Storage** : Le fichier `storage/framework/providers.php` doit être accessible en lecture. Si le fichier n'existe pas, les providers seront chargés depuis la configuration `config/app.php`.
---