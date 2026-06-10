# CliRunner - Référence Technique

## Description

Point d'entrée principal pour l'exécution des directives en ligne de commande. Initialise le conteneur de services, découvre les directives dans le système de fichiers, et exécute la commande demandée.

## Hiérarchie

```
CliRunner (final)
    ├── Container (Laravel/Illuminate Container)
    ├── DirectiveKernel (noyau d'exécution)
    └── Services : RenderDispatcher, InputDispatcher, DiscoveryService, ExecutionService
```

## Rôle principal

Servir de point d'entrée unique pour l'application CLI. Configure automatiquement tous les services nécessaires (découverte, parsing, hydratation, exécution) et exécute la directive demandée avec les arguments fournis.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(): void`

Constructeur du runner. Initialise le conteneur et détecte automatiquement le chemin des directives.

**Détection du chemin des directives :**
1. `getcwd() . '/app/Directives'`
2. `getcwd() . '/directives'`
3. `getcwd() . '/src/Directives'`

**Exemple :**
```php
$runner = new CliRunner();
```

### `run(array $argv): int`

Exécute une directive à partir des arguments de ligne de commande.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$argv` | `array<string>` | Arguments de la ligne de commande (le premier élément est généralement le nom du script) |

**Retourne :** `int` - Code de sortie (`0` = succès, `>0` = erreur)

**Exemple :**
```php
// Dans un script CLI (ex: bin/directive)
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AndyDefer\Directive\Cli\CliRunner;

$runner = new CliRunner();
exit($runner->run($argv));
```

## Cas d'utilisation

### Cas 1 : Script CLI standard

```php
#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AndyDefer\Directive\Cli\CliRunner;

$runner = new CliRunner();
exit($runner->run($argv));
```

### Cas 2 : Exécution d'une directive

```bash
# Afficher l'aide
./directive --help

# Lister les directives disponibles
./directive --list

# Afficher la version
./directive --version

# Exécuter une directive
./directive user-create "John Doe" john@example.com

# Exécuter une directive avec option
./directive user-create "Jane Doe" jane@example.com --admin

# Exécuter une directive avec option courte
./directive cache-clear -f

# Exécuter une directive via son alias
./directive users  # si 'users' est alias de 'user-list'
```

### Cas 3 : Structure de projet recommandée

```
project/
├── bin/
│   └── directive          # Script d'entrée CLI
├── app/
│   └── Directives/        # Vos directives
│       ├── UserCreateDirective.php
│       ├── CacheClearDirective.php
│       └── ...
├── bootstrap/
│   └── app.php            # Optionnel (pour Laravel)
├── config/
│   └── app.php            # Optionnel (pour Laravel)
└── vendor/
```

## Flux d'exécution

```
run($argv)
    │
    ├── 1. Enregistrement des services de base
    │       ├── RenderDispatcher (singleton)
    │       ├── InputDispatcher (singleton)
    │       ├── LaravelBootstrapperContext (singleton)
    │       ├── DirectiveDiscoveryContext (singleton)
    │       ├── SignatureValidationService (singleton)
    │       └── DirectiveInteractionService (singleton)
    │
    ├── 2. Construction du noyau (buildKernel)
    │       ├── EnvDirectiveConfig (configuration)
    │       ├── DirectiveParserService (parser)
    │       ├── DirectiveHydratorService (hydrateur)
    │       ├── DirectiveDiscoveryService (découverte)
    │       ├── DirectiveRendererService (rendu)
    │       └── DirectiveExecutionService (exécution)
    │
    ├── 3. Exécution du noyau
    │       └── DirectiveKernel::run($argv)
    │
    └── 4. Retour du code de sortie
```

## Détection des directives

Le runner recherche les directives dans l'ordre suivant :

| Chemin | Description |
|--------|-------------|
| `./app/Directives` | Structure Laravel standard (recommandée) |
| `./directives` | Structure alternative simple |
| `./src/Directives` | Structure pour packages |

Les directives doivent :
- Être dans le namespace `App\Directives`
- Étendre `AbstractDirective`
- Implémenter `getSignature()`, `getDescription()` et `execute()`

## Gestion des erreurs

| Situation | Code retour | Message |
|-----------|-------------|---------|
| Directive trouvée et exécutée avec succès | `0` | Sortie de la directive |
| Directive trouvée mais échoue | `1` | Message d'erreur |
| Directive non trouvée | `3` | `Directive not found: {name}` |
| Arguments invalides | `4` | Message d'erreur du parser |
| Commande d'aide | `0` | Liste des directives |
| Commande de version | `0` | Version du système |
| Commande de liste | `0` | Liste des directives |

## Intégration

`CliRunner` s'intègre avec :

- **`DirectiveKernel`** : Noyau d'exécution
- **`Container`** : Conteneur de services (Illuminate Container)
- **`EnvDirectiveConfig`** : Configuration basée sur les variables d'environnement
- **`LaravelBootstrapperContext`** : Contexte pour le bootstrap Laravel
- **`DirectiveDiscoveryService`** : Découverte automatique des directives

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Première exécution | Initialisation complète des services (~20-30ms) |
| Exécution suivante | Nouvelle instance = nouvelle initialisation |
| Découverte | Parcours du répertoire des directives (O(n) fichiers) |
| Mémoire | Conteneur + services + directive exécutée |

## Configuration d'environnement

| Variable | Description | Défaut |
|----------|-------------|--------|
| `DIRECTIVE_PATH` | Chemin personnalisé des directives | Détection automatique |
| `DIRECTIVE_DEBUG` | Mode debug (affiche plus d'informations) | `false` |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| Laravel 10+ | ✅ Intégration optionnelle |

## Exemple complet

### 1. Créer une directive

```php
<?php
// app/Directives/GreetDirective.php

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class GreetDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'greet {name} {--formal}';
    }

    public function getDescription(): string
    {
        return 'Greet someone';
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $formal = $this->option('formal');
        
        if ($formal) {
            $this->info("Good day to you, {$name}!");
        } else {
            $this->line("Hello, {$name}!");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

### 2. Créer le script CLI

```php
#!/usr/bin/env php
<?php
// bin/directive

use AndyDefer\Directive\Cli\CliRunner;

require_once __DIR__ . '/../vendor/autoload.php';

$runner = new CliRunner();
exit($runner->run($argv));
```

### 3. Rendre exécutable

```bash
chmod +x bin/directive
```

### 4. Utilisation

```bash
# Aide
./bin/directive --help

# Liste des directives
./bin/directive --list

# Exécution simple
./bin/directive greet John

# Exécution avec option
./bin/directive greet Jane --formal

# Version
./bin/directive --version
```

## Voir aussi

- [`DirectiveKernel`](directive-kernel.md) - Noyau d'exécution
- [`DirectiveDiscoveryService`](directive-discovery-service.md) - Découverte des directives
- [`DirectiveExecutionService`](directive-execution-service.md) - Service d'exécution
- [`DirectiveHydratorService`](directive-hydrator-service.md) - Hydratation des directives
- [`DirectiveParserService`](directive-parser-service.md) - Parsing des signatures
---