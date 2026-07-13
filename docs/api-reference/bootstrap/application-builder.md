# ApplicationBuilder - Référence Technique

## Description

`ApplicationBuilder` est un builder fluent pour créer et configurer des applications Laravel dans le contexte du package Directive. Il offre une interface fluide pour enregistrer des providers, définir des configurations et forcer le type d'application à utiliser.

## Rôle principal

- Créer des applications **internes** (Laravel) ou **externes** (standalone)
- Enregistrer des **Service Providers** de manière fluide
- Charger des **fichiers de configuration** personnalisés
- Forcer le **type d'application** indépendamment de la détection automatique
- Fournir des **méthodes statiques** pour les cas d'usage courants

---

## API / Méthodes publiques

### `init(?ApplicationType $type = null, array $providers = []): self`

Crée une nouvelle instance du builder.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `ApplicationType|null` | Type d'application à forcer |
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder = ApplicationBuilder::init(ApplicationType::INTERNAL, [
    DirectiveServiceProvider::class
]);
```

---

### `internal(array $providers = []): self`

Crée un builder pour une application interne (Laravel).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
]);
```

---

### `external(array $providers = []): self`

Crée un builder pour une application externe (standalone).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder = ApplicationBuilder::external([
    DirectiveServiceProvider::class
]);
```

---

### `web(array $providers = []): self`

Crée un builder pour une application web (Laravel).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `self` - Instance du builder (fluent)

---

### `package(array $providers = []): self`

Crée un builder pour un contexte package/library.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `self` - Instance du builder (fluent)

---

### `create(array $providers = [], ?ApplicationType $type = null): Application`

Crée une application directement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |
| `$type` | `ApplicationType|null` | Type d'application à forcer |

**Retourne :** `Application` - Instance de l'application

**Exemple :**
```php
$app = ApplicationBuilder::create(
    [DirectiveServiceProvider::class],
    ApplicationType::INTERNAL
);
```

---

### `createInternal(array $providers = []): Application`

Crée une application interne directement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `Application` - Instance de l'application

**Exemple :**
```php
$app = ApplicationBuilder::createInternal([
    DirectiveServiceProvider::class
]);
```

---

### `createExternal(array $providers = []): Application`

Crée une application externe directement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Providers à enregistrer |

**Retourne :** `Application` - Instance de l'application

**Exemple :**
```php
$app = ApplicationBuilder::createExternal([
    DirectiveServiceProvider::class
]);
```

---

### `forceType(ApplicationType $type): self`

Force le type d'application à utiliser.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `ApplicationType` | Type d'application à forcer |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->forceType(ApplicationType::EXTERNAL);
```

---

### `withProvider(string $provider): self`

Ajoute un Service Provider.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$provider` | `class-string<ServiceProvider>` | Classe du provider |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->withProvider(DirectiveServiceProvider::class);
```

---

### `withProviders(array $providers): self`

Ajoute plusieurs Service Providers.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$providers` | `array<class-string<ServiceProvider>>` | Classes des providers |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->withProviders([
    DirectiveServiceProvider::class,
    NemesisServiceProvider::class,
]);
```

---

### `withConfig(array $config): self`

Définit la configuration de l'application.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `array<string, mixed>` | Configuration à appliquer |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->withConfig([
    'app.debug' => true,
    'app.env' => 'development',
]);
```

---

### `withConfigValue(string $key, mixed $value): self`

Définit une valeur de configuration unique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de configuration (dot notation) |
| `$value` | `mixed` | Valeur à définir |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->withConfigValue('app.debug', true);
$builder->withConfigValue('database.default', 'sqlite');
```

---

### `withConfigPath(string $path, ?string $key = null): self`

Ajoute un fichier de configuration.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Chemin absolu du fichier |
| `$key` | `string|null` | Clé sous laquelle charger la config |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->withConfigPath('/config/directive.php');
$builder->withConfigPath('/config/nemesis.php', 'nemesis');
```

---

### `withConfigPaths(array $paths): self`

Ajoute plusieurs fichiers de configuration.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$paths` | `array<string, string|null>` | Chemins avec clés optionnelles |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->withConfigPaths([
    '/config/directive.php',              // key = 'directive'
    '/config/nemesis.php' => 'nemesis',   // key = 'nemesis'
]);
```

---

### `build(): Application`

Construit et retourne l'application configurée.

**Retourne :** `Application` - Instance de l'application

**Exceptions :** `InvalidArgumentException` - Si un fichier de config est introuvable ou invalide

**Exemple :**
```php
$app = ApplicationBuilder::internal()
    ->withProvider(DirectiveServiceProvider::class)
    ->withConfig(['app.debug' => true])
    ->build();
```

---

## Cas d'utilisation

### Cas 1 : Application interne avec providers

```php
<?php

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;

$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class,
    NemesisServiceProvider::class,
])->build();
```

---

### Cas 2 : Application externe avec configuration

```php
<?php

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;

$app = ApplicationBuilder::external([
    DirectiveServiceProvider::class
])
    ->withConfig(['app.debug' => true])
    ->withConfigValue('app.env', 'testing')
    ->build();
```

---

### Cas 3 : Chargement de fichiers de configuration

```php
<?php

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;

$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])
    ->withConfigPath('/project/config/directive.php')
    ->withConfigPath('/project/config/nemesis.php', 'nemesis')
    ->withConfigPaths([
        '/project/config/app.php',
        '/project/config/services.php' => 'services',
    ])
    ->build();
```

---

### Cas 4 : Détection automatique

```php
<?php

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;

// La détection est automatique
$app = ApplicationBuilder::create([
    DirectiveServiceProvider::class
]);
```

---

### Cas 5 : Forcer un type spécifique

```php
<?php

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ApplicationType;

// Forcer le type INTERNAL
$app = ApplicationBuilder::init(ApplicationType::INTERNAL)
    ->withProvider(DirectiveServiceProvider::class)
    ->build();

// Forcer le type EXTERNAL
$app = ApplicationBuilder::init(ApplicationType::EXTERNAL)
    ->withProvider(DirectiveServiceProvider::class)
    ->build();
```

---

### Cas 6 : Dans le fichier `bin/directive`

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require './vendor/autoload.php';

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;

// Création simple avec détection automatique
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])->build();

$kernel = $app->make(DirectiveKernel::class);
$exitCode = $kernel->run($argv);
exit($exitCode->value);
```

---

## Flux d'exécution

```
build()
    ↓
createBaseApplication()
    ├── forcedType !== null ?
    │   ├── INTERNAL → InternalApplicationFactory::create()
    │   ├── EXTERNAL → ExternalApplicationFactory::create()
    │   ├── WEB_APPLICATION → InternalApplicationFactory::create()
    │   ├── PACKAGE → ExternalApplicationFactory::create()
    │   └── default → detectApplication()
    └── detectApplication()
        ├── EnvironmentDetector::isWebApplication() → InternalApplicationFactory::create()
        ├── EnvironmentDetector::isPackage() → ExternalApplicationFactory::create()
        └── default → ExternalApplicationFactory::create()
    ↓
loadConfigFiles($app)
    ├── Pour chaque configPath
    │   ├── file_exists() → Sinon InvalidArgumentException
    │   ├── require() → Doit retourner array
    │   └── $app->config->set($key, array_merge(current, config))
    ↓
applyConfig($app)
    ├── Pour chaque config
    │   └── $app->config->set($key, $value)
    ↓
registerProviders($app)
    ├── Pour chaque provider
    │   ├── is_subclass_of(ServiceProvider::class) → Sinon InvalidArgumentException
    │   ├── new $providerClass($app)
    │   └── $provider->register()
    ↓
bootProviders($app)
    ├── Pour chaque provider
    │   ├── new $providerClass($app)
    │   └── if method_exists('boot') → $provider->boot()
    ↓
Retourne Application
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Fichier de configuration introuvable | `InvalidArgumentException` | `Configuration file not found: {path}` |
| Fichier de configuration invalide | `InvalidArgumentException` | `Configuration file must return an array: {path}` |
| Provider invalide | `InvalidArgumentException` | `Class "{class}" must extend Illuminate\Support\ServiceProvider` |

---

## Performance

- **Création** : Minimal, dépend des factories sous-jacentes
- **Configuration** : O(n) où n est le nombre de providers et configurations
- **Mémoire** : Stocke les providers et configs en mémoire jusqu'au build

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Enums\ApplicationType;
use AndyDefer\Directive\DirectiveKernel;

// 1. Création avec tous les paramètres
$app = ApplicationBuilder::init(ApplicationType::INTERNAL)
    ->withProvider(DirectiveServiceProvider::class)
    ->withProvider(NemesisServiceProvider::class)
    ->withConfig([
        'app.debug' => true,
        'app.env' => 'development',
        'database.default' => 'sqlite',
    ])
    ->withConfigValue('app.timezone', 'Europe/Paris')
    ->withConfigPath('/project/config/custom.php')
    ->withConfigPaths([
        '/project/config/directive.php' => 'directive',
        '/project/config/nemesis.php' => 'nemesis',
    ])
    ->build();

// 2. Utilisation
$kernel = $app->make(DirectiveKernel::class);
$kernel->addSource('/project/directives');

$exitCode = $kernel->run(['directive', 'help']);
exit($exitCode->value);
```

---

## Méthodes de création rapide

| Méthode | Type forcé | Equivalent |
|---------|------------|------------|
| `internal($providers)` | `INTERNAL` | `init(INTERNAL, $providers)` |
| `external($providers)` | `EXTERNAL` | `init(EXTERNAL, $providers)` |
| `web($providers)` | `WEB_APPLICATION` | `init(WEB_APPLICATION, $providers)` |
| `package($providers)` | `PACKAGE` | `init(PACKAGE, $providers)` |
| `create($providers, $type)` | Optionnel | `init($type, $providers)->build()` |
| `createInternal($providers)` | `INTERNAL` | `internal($providers)->build()` |
| `createExternal($providers)` | `EXTERNAL` | `external($providers)->build()` |

---

## Voir aussi

- `InternalApplicationFactory` - Factory pour applications internes
- `ExternalApplicationFactory` - Factory pour applications externes
- `ApplicationType` - Énumération des types d'application
- `EnvironmentDetector` - Détection de l'environnement
- `DirectiveServiceProvider` - Provider principal du package