# Factories d'Application - Référence Technique

## Description

Les factories `InternalApplicationFactory` et `ExternalApplicationFactory` sont responsables de la création et du bootstrap des applications Laravel dans différents contextes d'exécution. Elles fournissent une abstraction pour initialiser l'application avec la configuration appropriée selon l'environnement.

---

# InternalApplicationFactory - Référence Technique

## Description

`InternalApplicationFactory` crée une application Laravel légère pour une exécution en mode **interne/package**. Elle configure l'application avec un minimum de dépendances et enregistre uniquement les providers nécessaires au fonctionnement de base.

## Rôle principal

- Créer une application Laravel **sans configuration de base de données** par défaut
- Configurer le cache de bootstrap
- Définir l'application Facade root
- Enregistrer uniquement `DirectiveServiceProvider`

---

## API / Méthodes publiques

### `create(): Application`

Crée et configure une application Laravel interne.

**Retourne :** `Application` - Instance de l'application Laravel

**Étapes d'exécution :**
1. Création du dossier cache (`bootstrap/cache`)
2. Configuration de l'application avec `Application::configure()`
3. Définition du Facade root
4. Enregistrement de `DirectiveServiceProvider`

**Exemple :**
```php
$app = InternalApplicationFactory::create();
```

---

## Flux d'exécution

```
create()
    ↓
Créer dossier cache
    └── Paths::projectRoot() . '/bootstrap/cache'
    ↓
Application::configure()
    ├── basePath = Paths::projectRoot()
    ├── withExceptions()
    └── create()
    ↓
Facade::setFacadeApplication($app)
    ↓
$app->register(DirectiveServiceProvider::class)
    ↓
Retourne Application
```

---

## Exemple complet

```php
<?php

use AndyDefer\Directive\Factories\InternalApplicationFactory;
use AndyDefer\Directive\DirectiveKernel;

// Création de l'application interne
$app = InternalApplicationFactory::create();

// Utilisation avec le kernel
$kernel = DirectiveKernel::init($app);
$exitCode = $kernel->run(['directive', 'help']);
```

---

# ExternalApplicationFactory - Référence Technique

## Description

`ExternalApplicationFactory` crée une application Laravel complète pour une exécution en mode **externe/application**. Elle charge l'environnement, l'autoloader, le bootstrap Laravel, et enregistre tous les providers nécessaires.

## Rôle principal

- Charger les variables d'environnement depuis `.env`
- Charger l'autoloader Composer
- Créer l'application depuis le fichier `bootstrap/app.php`
- Enregistrer les providers depuis le cache et la configuration
- Bootstrapper l'application

---

## API / Méthodes publiques

### `create(): Application`

Crée et bootstrappe une application Laravel externe complète.

**Retourne :** `Application` - Instance bootstrappée de l'application Laravel

**Étapes d'exécution :**
1. Chargement de l'environnement `.env`
2. Chargement de l'autoloader Composer
3. Création de l'application depuis `bootstrap/app.php`
4. Enregistrement des providers
5. Bootstrapping de l'application

**Exceptions :** `BootstrapException` - Si une étape échoue

**Exemple :**
```php
$app = ExternalApplicationFactory::create();
```

---

## Flux d'exécution

```
create()
    ↓
loadEnvironment()
    └── Parcourt .env et appelle putenv()
    ↓
loadAutoloader()
    ├── Charge projet autoloader
    └── Charge package autoloader (si différent)
    ↓
createApplication()
    ├── Vérifie existence bootstrap/app.php
    ├── require() le fichier
    └── Vérifie que l'instance est une Application
    ↓
registerProviders($app)
    ├── resolveProvidersFromStorage() (compiled providers)
    ├── resolveProvidersFromConfig() (config/app.php)
    ├── filterValidProviders()
    └── $app->register() pour chaque provider
    ↓
bootApplication($app)
    └── $app->make(Kernel::class)->bootstrap()
    ↓
Retourne Application bootstrappée
```

---

## Cas d'utilisation

### Cas 1 : Application Laravel standard

```php
<?php

use AndyDefer\Directive\Factories\ExternalApplicationFactory;

// Dans une application Laravel existante
$app = ExternalApplicationFactory::create();

// L'application est complètement bootstrappée
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
```

---

### Cas 2 : CLI avec ExternalApplicationFactory

```php
#!/usr/bin/env php
<?php

use AndyDefer\Directive\Factories\ExternalApplicationFactory;
use AndyDefer\Directive\DirectiveKernel;

$app = ExternalApplicationFactory::create();
$kernel = DirectiveKernel::init($app);
$exitCode = $kernel->run($argv);
exit($exitCode->value);
```

---

### Cas 3 : Environnement sans fichier .env

```php
<?php

use AndyDefer\Directive\Factories\ExternalApplicationFactory;

// Si .env n'existe pas, loadEnvironment() ne fait rien
$app = ExternalApplicationFactory::create();
// L'application utilise les valeurs par défaut
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Autoloader manquant | `BootstrapException` | `Autoloader not found at [path/vendor/autoload.php]. Run 'composer install' first.` |
| Bootstrap Laravel manquant | `BootstrapException` | `Laravel bootstrap file not found at [path/bootstrap/app.php].` |
| Bootstrap invalide | `BootstrapException` | `Bootstrap file must return an instance of Illuminate\Contracts\Foundation\Application` |

---

## Comparaison des Factories

| Aspect | InternalApplicationFactory | ExternalApplicationFactory |
|--------|---------------------------|---------------------------|
| **Utilisation** | Mode package/library | Mode application Laravel |
| **Bootstrap** | Créé via `Application::configure()` | Requiert `bootstrap/app.php` |
| **Providers** | Seulement `DirectiveServiceProvider` | Providers depuis cache et config |
| **Environnement** | Pas de chargement `.env` | Chargement `.env` |
| **Autoloader** | Non chargé | Chargé automatiquement |
| **Cache** | Crée dossier `bootstrap/cache` | Utilise le cache existant |
| **Facades** | Définit Facade root | Dépend du bootstrap |
| **Performance** | Léger, rapide | Plus lourd, complet |

---

## Dépendances

### InternalApplicationFactory
- `Paths` - Résolution des chemins
- `DirectiveServiceProvider` - Provider principal
- `Facade` - Pour définir l'application Facade root
- `Illuminate\Foundation\Application` - Laravel

### ExternalApplicationFactory
- `Paths` - Résolution des chemins
- `BootstrapException` - Gestion des erreurs
- `Illuminate\Contracts\Foundation\Application` - Interface
- `Illuminate\Contracts\Console\Kernel` - Kernel console

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\Enums\ApplicationType;
use AndyDefer\Directive\Factories\InternalApplicationFactory;
use AndyDefer\Directive\Factories\ExternalApplicationFactory;

// ✅ Utilisation directe avec détection
if (EnvironmentDetector::isPackage()) {
    $app = InternalApplicationFactory::create();
} else {
    $app = ExternalApplicationFactory::create();
}

// ✅ Utilisation via ApplicationBuilder (recommandé)
$app = ApplicationBuilder::init()
    ->build();

// ✅ Forcer le type interne
$app = ApplicationBuilder::init(ApplicationType::INTERNAL)
    ->build();

// ✅ Forcer le type externe
$app = ApplicationBuilder::init(ApplicationType::EXTERNAL)
    ->build();

// ✅ Avec providers
$app = ApplicationBuilder::init(ApplicationType::INTERNAL)
    ->withProvider(DirectiveServiceProvider::class)
    ->withConfig(['app.debug' => true])
    ->build();
```

---

## Voir aussi

- `ApplicationBuilder` - Builder pour la création d'application
- `ApplicationType` - Énumération des types d'application
- `EnvironmentDetector` - Détection de l'environnement
- `Paths` - Résolution des chemins
- `DirectiveServiceProvider` - Provider principal