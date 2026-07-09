# WHY LARAVEL DIRECTIVE

## Le framework CLI qui transforme vos commandes en applications composables

---

## L'histoire qui a donné naissance à Laravel Directive

Imaginez la situation suivante :

Un développeur construit une application SaaS complexe avec Laravel. L'application doit gérer un pipeline de déploiement, orchestrer des tâches de data processing, et offrir une interface CLI riche aux administrateurs.

Il commence par utiliser `php artisan make:command` pour créer ses commandes. Rapidement, il se retrouve avec 15 commandes qui doivent s'appeler les unes les autres. Il doit partager des données entre elles (timestamp de début, chemins de fichiers, résultats intermédiaires). Il doit pouvoir exécuter des pipelines complexes comme :

```bash
php artisan deploy:run --env=staging --skip-tests
```

Cette commande doit appeler `deploy:validate`, `deploy:backup`, `deploy:build`, `deploy:migrate`, `deploy:activate`, et `deploy:post` en cascade, tout en partageant un état (le répertoire de build, la version, les logs).

**Avec Artisan, ce cas d'usage devient rapidement complexe :**

- Pas de contexte partagé → Il faut passer des paramètres partout ou utiliser des variables globales
- Pas d'appels internes structurés → `$this->call()` existe, mais reste limité
- Pas d'état → On ne sait pas où en est le pipeline si une étape échoue
- Pas de découverte automatique → Chaque nouvelle commande doit être enregistrée dans le Kernel

Le développeur a alors plusieurs options :

1. **Utiliser des facades ou des singletons pour partager l'état** → Couplage fort, difficile à tester
2. **Passer tous les paramètres en arguments** → Lignes de commande monstres, difficile à maintenir
3. **Créer un orchestrateur personnalisé** → Réinventer la roue

**C'est précisément ce problème que Laravel Directive résout.** Il permet de construire des applications CLI complexes avec un partage de contexte natif, des appels internes structurés, et une découverte automatique.

---

## Mais d'abord, quels sont les outils Laravel natifs pour le CLI ?

### Artisan Commands

```php
// app/Console/Commands/GreetCommand.php
class GreetCommand extends Command
{
    protected $signature = 'greet {name} {--formal}';
    protected $description = 'Say hello to someone';
    
    public function handle()
    {
        $name = $this->argument('name');
        $formal = $this->option('formal');
        $this->info(($formal ? "Good day, $name" : "Hello, $name"));
    }
}
```

**Ce qu'il fait bien :**
- ✅ Interface simple et familière
- ✅ Intégration native avec Laravel
- ✅ Signatures claires (arguments, options)
- ✅ Sortie console stylée

**Ses limites :**
- ❌ Pas de contexte partagé entre commandes
- ❌ Pas de découverte automatique (enregistrement manuel)
- ❌ Pas de cycle de vie structuré (before/after hooks)
- ❌ Appels internes limités (`$this->call()`)
- ❌ Pas de journalisation structurée intégrée
- ❌ Pas de suggestions automatiques

---

## Et Laravel Directive dans tout ça ?

**Laravel Directive n'est pas un remplacement d'Artisan.** C'est une approche différente pour construire des applications CLI complexes.

Là où Artisan excelle dans les commandes unitaires, **Laravel Directive excelle dans les systèmes CLI composables.**

### Ce qui le rend différent

Chaque directive est un **objet autonome** mais interconnectable, avec :

- ✅ **Un contexte partagé** (MapCollection) entre toutes les directives
- ✅ **Des appels internes structurés** (`call()`) avec détection de circularité
- ✅ **Des hooks de cycle de vie** (beforeExecute, afterExecute)
- ✅ **Une découverte automatique** via AST (aucun enregistrement manuel)
- ✅ **Une journalisation structurée** au format JSONL
- ✅ **Des suggestions de commandes** via BK-tree
- ✅ **Un conteneur adaptable** (Laravel ou autonome)

---

## La valeur ajoutée de Laravel Directive

### 1. Un contexte partagé

Avec Artisan, chaque commande est isolée. Pas de mémoire partagée.

Avec Directive, le contexte est un `MapCollection` mutable qui vit pendant toute l'exécution d'un pipeline.

```php
// Dans une directive
$this->contextSet('deployment_start', microtime(true));
$this->contextSet('environment', 'staging');
$this->contextSet('backup_file', $backupPath);

// Dans une autre directive
$start = $this->contextGet('deployment_start');
$env = $this->contextGet('environment');
$backup = $this->contextGet('backup_file');
```

**Résultat :** Des pipelines complexes sans passation de paramètres manuelle.

### 2. Des appels internes structurés

Avec Artisan, `$this->call()` existe mais est limité.

Avec Directive, `call()` est un mécanisme central avec détection de circularité.

```php
// Orchestration d'un pipeline de déploiement
protected function execute(): ExitCode
{
    $this->call('deploy:validate');
    $this->call('deploy:backup');
    $this->call('deploy:build --with-tests');
    $this->call('deploy:migrate --force');
    $this->call('deploy:activate');
    $this->call('deploy:post --notify');
    return ExitCode::SUCCESS;
}
```

**Résultat :** Des pipelines lisibles, modulables et résilients.

### 3. Un cycle de vie structuré

```php
class DeployDirective extends AbstractDirective
{
    protected function beforeExecute(): void
    {
        // Initialisation
        $this->contextSet('start_time', microtime(true));
        $this->info('Starting deployment...');
    }
    
    protected function execute(): ExitCode
    {
        // Logique métier
        return ExitCode::SUCCESS;
    }
    
    protected function afterExecute(ExitCode $exitCode): void
    {
        // Nettoyage et reporting
        $duration = microtime(true) - $this->contextGet('start_time');
        $this->info("Completed in {$duration}s");
    }
}
```

**Résultat :** Des directives avec des responsabilités claires et séparées.

### 4. Une découverte automatique

Avec Artisan, chaque commande doit être enregistrée.

Avec Directive, la découverte est automatique via analyse AST.

```php
// Pas besoin d'enregistrer quoi que ce soit
// Une classe qui étend AbstractDirective est automatiquement découverte
// si elle se trouve dans src/Directives/, app/Directives/, ou un dossier configuré
```

**Résultat :** Zero configuration pour les nouvelles directives.

### 5. Une journalisation structurée

Chaque exécution est automatiquement journalisée au format JSONL.

```json
{
  "time": "2026-07-09T11:45:23+00:00",
  "level": "info",
  "type": "directive_execution",
  "payload": {
    "command": "deploy",
    "directive_class": "App\\Directives\\DeployDirective",
    "exit_code": 0,
    "duration_seconds": 12.345,
    "memory_bytes": 2048,
    "context": { "environment": "staging", "backup_file": "backup_2026-07-09.sql" }
  }
}
```

**Résultat :** Un audit complet de toutes les exécutions, avec le contexte.

### 6. Des suggestions de commandes

Quand une commande est mal tapée, Directive propose automatiquement des alternatives.

```bash
$ bin/directive depoy
Directive not found: depoy

💡 Did you mean:
  • deploy
  • deploy:validate
  • deploy:backup
```

**Résultat :** Une UX CLI améliorée.

---

## En une phrase

> **Artisan exécute des commandes. Laravel Directive orchestre des systèmes CLI.**

---

## Quand utiliser quoi ?

| Besoin | Artisan | Laravel Directive |
|--------|---------|-------------------|
| "Une commande simple avec arguments" | ✅ | ✅ |
| "Un pipeline de plusieurs commandes" | ❌ | ✅ |
| "Partager un état entre commandes" | ❌ | ✅ |
| "Découverte automatique des commandes" | ❌ | ✅ |
| "Suggestions de commandes" | ❌ | ✅ |
| "Journalisation structurée intégrée" | ❌ | ✅ |
| "Fonctionner sans Laravel" | ❌ | ✅ |
| "Environnement mature et éprouvé" | ✅ | ⚠️ |
| "Intégration standard avec Laravel" | ✅ | ✅ |

---

## Cas d'usage concrets

### 1. Pipeline de déploiement

```php
class DeployDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'deploy {environment} {--skip-tests} {--force}';
    }
    
    protected function beforeExecute(): void
    {
        $this->contextSet('environment', $this->argument('environment'));
        $this->contextSet('start_time', microtime(true));
    }
    
    protected function execute(): ExitCode
    {
        $env = $this->contextGet('environment');
        
        $this->call("deploy:validate $env");
        $this->call("deploy:backup $env");
        
        if (!$this->flag('skip-tests')) {
            $this->call('deploy:build --with-tests');
        } else {
            $this->call('deploy:build');
        }
        
        $this->call("deploy:migrate $env --force=" . ($this->flag('force') ? 'true' : 'false'));
        $this->call("deploy:activate $env");
        $this->call('deploy:post --notify');
        
        return ExitCode::SUCCESS;
    }
    
    protected function afterExecute(ExitCode $exitCode): void
    {
        $duration = microtime(true) - $this->contextGet('start_time');
        $this->info("✅ Deployment completed in {$duration}s");
    }
}
```

### 2. Pipeline de data processing

```php
class ProcessDataDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'process {file} {--format=csv} {--dry-run}';
    }
    
    protected function execute(): ExitCode
    {
        $file = $this->argument('file');
        $dryRun = $this->flag('dry-run');
        
        // Étape 1 : Chargement
        $this->call("data:load $file");
        
        // Étape 2 : Nettoyage
        $this->call('data:clean');
        
        // Étape 3 : Transformation
        $this->call('data:transform --aggressive');
        
        // Étape 4 : Validation
        $this->call('data:validate');
        
        // Étape 5 : Export
        if (!$dryRun) {
            $format = $this->argument('format');
            $this->call("data:export --format=$format");
        }
        
        // Récupérer les statistiques du contexte
        $stats = $this->contextGet('processing_stats', []);
        $this->info("📊 Processed {$stats['total']} records");
        
        return ExitCode::SUCCESS;
    }
}
```

### 3. Application CLI complète

```php
// Une application multi-commandes avec menus et tableaux
class AppDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'app {action} {--id=} {--format=}';
    }
    
    protected function execute(): ExitCode
    {
        $action = $this->argument('action');
        
        match ($action) {
            'deploy' => $this->call('deploy'),
            'reports' => $this->call('reports --format=' . $this->argument('format')),
            'monitor' => $this->call('monitor'),
            'status' => $this->call('status --id=' . $this->argument('id')),
            default => $this->error("Unknown action: $action"),
        };
        
        return ExitCode::SUCCESS;
    }
}
```

### 4. Tests CI/CD

```php
class PipelineDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'ci:pipeline {--branch=main} {--parallel}';
    }
    
    protected function execute(): ExitCode
    {
        $branch = $this->argument('branch');
        
        $this->call('ci:lint');
        
        if ($this->flag('parallel')) {
            $this->call('ci:test --parallel');
        } else {
            $this->call('ci:test');
        }
        
        $this->call('ci:coverage');
        $this->call('ci:build');
        
        if ($branch === 'main') {
            $this->call('deploy --skip-tests');
        }
        
        $coverage = $this->contextGet('coverage', 0);
        $this->info("📊 Coverage: {$coverage}%");
        
        return ExitCode::SUCCESS;
    }
}
```

---

## Ce que le développeur gagne en confort

### 1. Une API claire et intuitive

```php
// Accès aux arguments et flags
$name = $this->argument('name');
$verbose = $this->flag('verbose');

// Gestion du contexte
$this->contextSet('user_id', 42);
$userId = $this->contextGet('user_id');
$this->contextIncrement('counter', 5);

// Appels internes
$this->call('build --clean');
$this->call('test --unit');

// Sortie console
$this->info('Success!');
$this->error('Failed!');
$this->table($headers, $rows);
```

### 2. Un débogage facilité

```bash
# La journalisation JSONL permet d'analyser les exécutions
$ cat .directive/2026-07-09/11.jsonl | jq '.payload.command'

# Statistiques d'exécution
$ bin/directive list
Total: 12 directives

Deployment:
  deploy                    Déployer l'application
  deploy:validate           Valider l'environnement
  deploy:backup             Sauvegarder la base
```

### 3. Une découverte automatique

```bash
# Nouvelle directive créée
class NewDirective extends AbstractDirective { ... }

# Immédiatement disponible, sans enregistrement
$ bin/directive new
```

### 4. Une exécution autonome

```bash
# Sans Laravel
$ php bin/directive list

# Avec Laravel (même commande)
$ php artisan directive:run list
```

---

## Fonctionne dans tous les contextes

| Contexte | Artisan | Laravel Directive |
|----------|---------|-------------------|
| Application Laravel standard | ✅ | ✅ |
| Package autonome | ❌ | ✅ |
| Tests unitaires | ⚠️ | ✅ |
| CI/CD | ✅ | ✅ |
| Application CLI standalone | ❌ | ✅ |

**Laravel Directive peut fonctionner avec ou sans Laravel.**

---

## Installation et mise en route

```bash
# 1. Installation
composer require andydefer/laravel-directive

# 2. Configuration (Laravel)
php artisan vendor:publish --tag=directive-config

# 3. Création d'une directive
php artisan make:directive GreetDirective

# 4. Exécution
./vendor/bin/directive greet John --formal
```

**Pour une utilisation autonome :**

```php
# bin/app
require_once __DIR__ . '/vendor/autoload.php';

use AndyDefer\Directive\Container\DirectiveContainer;
use AndyDefer\Directive\DirectiveKernel;

$container = DirectiveContainer::create(__DIR__);
$kernel = DirectiveKernel::init($container);
$kernel->addSource(__DIR__ . '/src/Directives');

exit($kernel->run($argv)->value);
```

---

## Conclusion

Laravel Directive n'est pas une réécriture d'Artisan. Ce n'est pas un remplacement du système de commandes Laravel.

**C'est un complément à l'écosystème Laravel.**

Il répond à un besoin précis : **construire des applications CLI complexes où les commandes communiquent, partagent un état, et s'orchestrent.**

- ✅ Quand vous avez besoin de pipelines complexes
- ✅ Quand vous voulez un contexte partagé
- ✅ Quand vous voulez une découverte automatique
- ✅ Quand vous voulez des suggestions de commandes
- ✅ Quand vous voulez une journalisation structurée
- ✅ Quand vous voulez fonctionner avec ou sans Laravel

**Laravel Directive apporte une solution élégante à un problème concret : la construction d'applications CLI complexes.**

---

## Pourquoi "Directive" ?

Le nom "Directive" reflète la philosophie du package :

- **Directive** = une instruction claire, un ordre
- **Directive** = une orientation, une guidance
- **Directive** = une directive qui peut en appeler d'autres

Comme en programmation impérative, une directive peut enchaîner d'autres directives. Chaque directive a un but précis et peut être composée avec d'autres pour former des systèmes complexes.

---

## Liens utiles

- [📦 Documentation complète](https://github.com/andydefer/laravel-directive)
- [🐛 Signaler un bug](https://github.com/andydefer/laravel-directive/issues)
- [💡 Proposer une fonctionnalité](https://github.com/andydefer/laravel-directive/issues)

---

**Le framework CLI qui transforme vos commandes en applications composables.** 🚀