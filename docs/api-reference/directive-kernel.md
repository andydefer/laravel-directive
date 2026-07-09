# DirectiveKernel - Référence Technique

## Description

Le noyau central qui orchestre l'exécution des directives. Il est responsable de la découverte des directives, de la résolution de la directive appropriée pour une commande donnée, et de son exécution.

## Hiérarchie / Implémentations

```
DirectiveKernel (final)
```

## Rôle principal

Agir comme point d'entrée principal du système de directives. Le kernel :
1. Reçoit les arguments de la ligne de commande
2. Découvre toutes les directives disponibles
3. Identifie la directive correspondant à la commande
4. Instancie et exécute la directive
5. Retourne le code de sortie approprié

## Installation

### Utilisation automatique

Le kernel est automatiquement instancié par le conteneur via le service provider :

```php
// Dans DirectiveServiceProvider
$this->app->singleton(DirectiveKernel::class, function ($app) {
    return new DirectiveKernel(
        $app,
        $app->make(DirectiveDiscoveryService::class)
    );
});
```

### Utilisation manuelle

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

$kernel = new DirectiveKernel($app, $discovery);
$exitCode = $kernel->run(['directive', 'list']);
```

## API / Méthodes publiques

### `run(array $argv): ExitCode`

Exécute le kernel avec les arguments de la ligne de commande.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$argv` | `array<int, string>` | Les arguments de la ligne de commande |

**Retourne :** `ExitCode` - Le code de sortie de l'exécution

**Exceptions :** Aucune (les erreurs sont gérées par les directives)

**Exemple :**
```php
<?php

// Exécution d'une directive
$exitCode = $kernel->run(['directive', 'list']);
// Ou
$exitCode = $kernel->run(['directive', 'user:create', 'John', '--admin']);
```

## Cas d'utilisation

### Cas 1 : Exécution d'une directive simple

```php
<?php

use AndyDefer\Directive\DirectiveKernel;

// Arguments: directive list
$argv = ['directive', 'list'];
$exitCode = $kernel->run($argv);

if ($exitCode->isSuccess()) {
    echo "Commande exécutée avec succès\n";
} else {
    echo "Erreur: " . $exitCode->getLabel() . "\n";
}
```

### Cas 2 : Exécution avec arguments et flags

```php
<?php

// Arguments: directive user:create John --admin --role=editor
$argv = ['directive', 'user:create', 'John', '--admin', '--role=editor'];
$exitCode = $kernel->run($argv);

// La directive user:create sera exécutée avec ces arguments
```

### Cas 3 : Exécution avec alias

```php
<?php

// Utilisation d'un alias: directive ls (alias de list)
$argv = ['directive', 'ls'];
$exitCode = $kernel->run($argv);

// La directive list sera exécutée
```

### Cas 4 : Intégration dans un script CLI

```php
#!/usr/bin/env php
<?php

use AndyDefer\Directive\Bootstrap\CliBootstrap;

// Le bootstrap crée automatiquement le kernel
$bootstrap = CliBootstrap::create();
$exitCode = $bootstrap->run($argv);

exit($exitCode);
```

## Flux d'exécution

```
DirectiveKernel::run($argv)
    │
    ├── isMissingCommand($argv)
    │   ├── count($argv) < 2 → true
    │   └── executeHelpDirective()
    │       └── executeDirective('help', 'help')
    │
    └── parseArguments($argv)
        ├── $query = implode(' ', array_slice($argv, 1))
        ├── $parts = explode(' ', $query)
        └── $commandName = $parts[0]
    │
    └── executeDirective($commandName, $query)
        │
        ├── $directives = $this->discovery->discover()
        │
        ├── findDirective($directives, $commandName)
        │   ├── matchesCommandName()
        │   │   └── Comparaison avec la première partie de la signature
        │   └── matchesAlias()
        │       └── Comparaison avec les alias
        │
        ├── if ($directive === null) → ExitCode::NOT_FOUND
        │
        └── instantiateAndRun($directive, $query)
            ├── $this->app->make($directive->class, ['query' => $query])
            └── $instance->run()
```

## Exemples de résolution

### Résolution par nom de commande

```php
// Directive avec signature: 'user:create {name}'
// Commande: directive user:create
// Résultat: Directive trouvée par nom de commande 'user:create'
```

### Résolution par alias

```php
// Directive avec alias: '-l' pour 'list'
// Commande: directive -l
// Résultat: Directive trouvée par alias '-l' → 'list'
```

### Résolution par nom court

```php
// Directive avec signature: 'list'
// Commande: directive list
// Résultat: Directive trouvée par nom de commande 'list'
```

## Gestion des erreurs

| Situation | Comportement | Code de sortie |
|-----------|--------------|----------------|
| Aucune commande fournie | Exécute `help` | `ExitCode::SUCCESS` |
| Directive non trouvée | Retourne `NOT_FOUND` | `ExitCode::NOT_FOUND` |
| Erreur d'exécution | Gérée par la directive | Variable |

### Scénarios d'erreur

```php
// Pas de commande
$kernel->run(['directive']);
// → Exécute help

// Commande inexistante
$kernel->run(['directive', 'nonexistent']);
// → Retourne ExitCode::NOT_FOUND

// Commande avec erreur interne
$kernel->run(['directive', 'failing:command']);
// → Retourne ExitCode::RUNTIME_ERROR (si géré par la directive)
```

## Intégration

Le `DirectiveKernel` s'intègre avec :

| Composant | Utilisation |
|-----------|-------------|
| `DirectiveDiscoveryService` | Découverte des directives |
| `DirectiveMetadataCollection` | Collection des directives découvertes |
| `DirectiveMetadataRecord` | Métadonnées des directives |
| `ExitCode` | Codes de retour |
| `Application` | Conteneur Laravel pour l'instanciation |

### Utilisation avec CliBootstrap

```php
// CliBootstrap utilise le kernel via CliRunner
class CliRunner
{
    public function run(array $argv): int
    {
        $kernel = $this->buildKernel();
        return $kernel->run($argv)->value;
    }
}
```

## Performance

| Métrique | Valeur | Description |
|----------|--------|-------------|
| Temps de découverte | 200-800ms | Première exécution |
| Temps de résolution | < 1ms | Recherche dans la collection |
| Temps d'instanciation | 1-5ms | Création de la directive |
| Mémoire | 2-5 MB | Collection des directives |

### Optimisations

```php
class DirectiveKernel
{
    private ?DirectiveMetadataCollection $cachedDirectives = null;
    
    private function executeDirective(string $commandName, string $query): ExitCode
    {
        if ($this->cachedDirectives === null) {
            $this->cachedDirectives = $this->discovery->discover();
        }
        
        $directive = $this->findDirective($this->cachedDirectives, $commandName);
        // ...
    }
}
```

## Compatibilité

| Version | Support | Notes |
|---------|---------|-------|
| PHP 8.1+ | ✅ Complet | - |
| PHP 8.2+ | ✅ Complet | - |
| Laravel 9.x | ✅ Complet | - |
| Laravel 10.x | ✅ Complet | - |
| Laravel 11.x | ✅ Complet | - |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Services\DirectiveDiscoveryService;
use Illuminate\Foundation\Application;

class KernelExample
{
    private DirectiveKernel $kernel;
    
    public function __construct(Application $app)
    {
        $discovery = $app->make(DirectiveDiscoveryService::class);
        $this->kernel = new DirectiveKernel($app, $discovery);
    }
    
    public function runCommand(string $command): int
    {
        // Construire les arguments
        $argv = ['directive', ...explode(' ', $command)];
        
        // Exécuter
        $exitCode = $this->kernel->run($argv);
        
        return $exitCode->value;
    }
}

// Utilisation
$example = new KernelExample($app);

// Exécuter une commande simple
$result = $example->runCommand('list');
echo "Résultat: " . $result . PHP_EOL;

// Exécuter avec arguments
$result = $example->runCommand('user:create John --admin');
echo "Résultat: " . $result . PHP_EOL;

// Gérer les erreurs
$result = $example->runCommand('nonexistent');
if ($result !== 0) {
    echo "Erreur: Code {$result}\n";
}

// Exécution programmatique
$commands = [
    'cache:clear',
    'config:cache',
    'view:clear',
];

foreach ($commands as $command) {
    $code = $example->runCommand($command);
    if ($code !== 0) {
        echo "Échec de: {$command} (code: {$code})\n";
        break;
    }
    echo "Succès: {$command}\n";
}
```

## Notes techniques

### Résolution des directives

Le kernel utilise deux méthodes pour trouver une directive :

1. **Par nom de commande** : La première partie de la signature
2. **Par alias** : Les alias définis dans la directive

```php
// Signature: 'user:create {name}'
// Nom de commande: 'user:create'
// Alias possibles: ['u', 'uc']

// Résolution:
// directive user:create → trouvée par nom
// directive u → trouvée par alias
// directive uc → trouvée par alias
```

### Commande par défaut

Si aucune commande n'est fournie, le kernel exécute automatiquement `help` :

```php
// Pas de commande
$kernel->run(['directive']);
// → Exécute help
```

### Instanciation des directives

Le kernel utilise le conteneur Laravel pour instancier les directives :

```php
$instance = $this->app->make($directive->class, [
    'query' => $query,
]);
```

Cela permet d'injecter automatiquement les dépendances via le conteneur.

### Points d'extension

1. **Nouvelles directives** : Ajoutées via `DirectiveDiscoveryService`
2. **Nouveaux alias** : Définis dans `getAliases()` de la directive
3. **Comportement par défaut** : Peut être modifié en surchargeant `executeHelpDirective()`

### Bonnes pratiques

1. **Toujours utiliser le conteneur** : Pour l'instanciation des directives
2. **Gérer les erreurs** : Retourner des `ExitCode` appropriés
3. **Tester les directives** : Utiliser `DirectiveTestingService`
4. **Documenter les commandes** : Utiliser `getDescription()`

```php
// ✅ Bonne pratique
$exitCode = $kernel->run(['directive', 'list']);

// ✅ Gestion du code de sortie
if ($exitCode->isFailure()) {
    // Gérer l'erreur
}

// ❌ Mauvaise pratique
$kernel->run(['directive', 'list']); // Ignorer le code de sortie
```
---