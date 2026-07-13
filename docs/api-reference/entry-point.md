# Directive CLI - Référence Technique

## Description

Le binaire `directive` est un exécutable CLI autonome qui sert de **modèle de démonstration** pour le package `laravel-directive`. Il illustre comment utiliser le système de directives dans un contexte CLI, mais **la recommandation officielle est de créer votre propre système** adapté à vos besoins.

## Rôle principal

- **Démontrer** l'utilisation du package dans un contexte CLI
- **Servir de modèle** pour créer votre propre système
- **Exécuter des directives** dans n'importe quel contexte PHP
- **Fonctionner** sans configuration ni dépendances applicatives

---

## ⚠️ IMPORTANT : Philosophie d'utilisation

**Ce binaire est un exemple, pas une solution finale.**

Le package `laravel-directive` est conçu pour être **intégré** dans vos propres systèmes, que ce soit :

- Un **CLI personnalisé** avec vos propres commandes et logique métier
- Un **worker** ou **daemon** exécutant des tâches en arrière-plan
- Un **système de tâches planifiées** (cron jobs)
- Un **système de plugins** pour votre application
- Un **moteur de scripts** pour votre infrastructure

### Pourquoi créer son propre système ?

1. **Contrôle total** : Vous décidez quelles directives sont disponibles
2. **Logique métier** : Intégration avec votre domaine applicatif
3. **Providers personnalisés** : Enregistrez vos propres services
4. **Configuration** : Adaptez l'environnement à vos besoins
5. **Performance** : Chargez uniquement ce dont vous avez besoin
6. **Sécurité** : Contrôlez l'accès aux directives

---

## Code source

```bash
#!/usr/bin/env php
<?php

declare(strict_types=1);

require './vendor/autoload.php';

use AndyDefer\ConsoleWriter\Console\Contracts\ConsoleInterface;
use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Exceptions\BootstrapException;

$bootstrap = ApplicationBuilder::init()
    ->withProvider(DirectiveServiceProvider::class)
    ->build();

$console = $bootstrap->make(ConsoleInterface::class);

try {
    $kernel = $bootstrap->make(DirectiveKernel::class);
    $exitCode = $kernel->run($argv);

    exit($exitCode->value);
} catch (BootstrapException $e) {
    $console->error('Bootstrap Error: ' . $e->getMessage());
    exit(2);
} catch (\Throwable $e) {
    $console->error('Fatal Error: ' . $e->getMessage());
    $console->line($e->getTraceAsString());
    exit(255);
}
```

---

## Utilisation en dehors du CLI

Les directives ne sont pas limitées au CLI ! Elles peuvent être exécutées dans n'importe quel contexte PHP :

### 1. Dans un contrôleur Laravel

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

class TaskController extends Controller
{
    public function run(DirectiveKernel $kernel)
    {
        // Exécuter une directive depuis un contrôleur web
        $exitCode = $kernel->runSignature('process:data --format=json');
        
        if ($exitCode === ExitCode::SUCCESS) {
            return response()->json(['message' => 'Task completed']);
        }
        
        return response()->json(['error' => 'Task failed'], 500);
    }
}
```

### 2. Dans un job ou queue worker

```php
<?php

namespace App\Jobs;

use AndyDefer\Directive\DirectiveKernel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessDataJob implements ShouldQueue
{
    use Queueable;

    public function handle(DirectiveKernel $kernel): void
    {
        // Exécuter une directive dans un job
        $kernel->runSignature('process:batch --limit=1000');
        
        // Ou par FQCN
        $kernel->runDirective(ProcessBatchDirective::class, ['--limit=1000']);
    }
}
```

### 3. Dans une tâche planifiée (Cron)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function (DirectiveKernel $kernel) {
        $kernel->runSignature('backup:database --compress');
    })->daily();
}
```

### 4. Dans une commande Artisan

```php
<?php

namespace App\Console\Commands;

use AndyDefer\Directive\DirectiveKernel;
use Illuminate\Console\Command;

class RunDirectiveCommand extends Command
{
    protected $signature = 'app:run-directive {directive} {--params=*}';
    
    public function handle(DirectiveKernel $kernel)
    {
        $directive = $this->argument('directive');
        $params = $this->option('params');
        
        $exitCode = $kernel->runDirective($directive, $params);
        
        return $exitCode->value;
    }
}
```

### 5. Dans un service ou classe métier

```php
<?php

namespace App\Services;

use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;

class ReportService
{
    public function __construct(
        private DirectiveKernel $kernel
    ) {}
    
    public function generateDailyReport(): array
    {
        // Utiliser des directives comme moteur de traitement
        $this->kernel->runSignature('report:fetch data');
        $this->kernel->runSignature('report:process --format=pdf');
        $this->kernel->runSignature('report:send --email=admin@example.com');
        
        return ['status' => 'completed'];
    }
}
```

### 6. Dans un script PHP autonome

```php
#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;

// ✅ Créer l'application
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class
])->build();

$kernel = $app->make(DirectiveKernel::class);

// ✅ Exécuter une directive par signature
$exitCode = $kernel->runSignature('deploy:production --migrate');

// ✅ Ou exécuter par FQCN
$exitCode = $kernel->runDirective(
    'App\\Directives\\DeployDirective',
    ['production', '--migrate']
);

exit($exitCode->value);
```

---

## Cas d'utilisation avancés

### Cas 1 : Système de plugins

```php
<?php

namespace App\PluginSystem;

use AndyDefer\Directive\DirectiveKernel;

class PluginManager
{
    public function __construct(
        private DirectiveKernel $kernel
    ) {}
    
    public function executePlugin(string $pluginName, array $params = []): void
    {
        // Les plugins sont des directives
        $this->kernel->runDirective(
            "App\\Plugins\\{$pluginName}Directive",
            $params
        );
    }
}
```

### Cas 2 : Moteur de workflow

```php
<?php

namespace App\Workflow;

use AndyDefer\Directive\DirectiveKernel;

class WorkflowEngine
{
    private array $steps = [
        'validate',
        'process',
        'transform',
        'persist',
        'notify',
    ];
    
    public function __construct(
        private DirectiveKernel $kernel
    ) {}
    
    public function run(array $data): void
    {
        $context = $this->kernel->getContext();
        $context->put('data', $data);
        
        foreach ($this->steps as $step) {
            $this->kernel->runSignature("workflow:step {$step}");
        }
    }
}
```

### Cas 3 : Système de tâches planifiées

```php
<?php

namespace App\Scheduler;

use AndyDefer\Directive\DirectiveKernel;
use Carbon\Carbon;

class TaskScheduler
{
    private array $tasks = [
        'daily' => ['task:daily-report', 'task:backup'],
        'hourly' => ['task:process-queue'],
        'minutely' => ['task:ping-health-check'],
    ];
    
    public function __construct(
        private DirectiveKernel $kernel
    ) {}
    
    public function run(): void
    {
        $now = Carbon::now();
        
        if ($now->minute === 0) {
            foreach ($this->tasks['hourly'] as $task) {
                $this->kernel->runSignature($task);
            }
        }
        
        if ($now->hour === 0) {
            foreach ($this->tasks['daily'] as $task) {
                $this->kernel->runSignature($task);
            }
        }
    }
}
```

### Cas 4 : Moteur de migration de données

```php
<?php

namespace App\DataMigration;

use AndyDefer\Directive\DirectiveKernel;

class MigrationEngine
{
    public function __construct(
        private DirectiveKernel $kernel
    ) {}
    
    public function migrate(string $from, string $to, array $options = []): void
    {
        $this->kernel->runSignature("migrate:extract --source={$from}");
        
        if ($options['transform'] ?? false) {
            $this->kernel->runSignature("migrate:transform --rules=default");
        }
        
        $this->kernel->runSignature("migrate:load --target={$to}");
        
        if ($options['verify'] ?? false) {
            $this->kernel->runSignature("migrate:verify");
        }
    }
}
```

---

## Créer son propre système

### Étape 1 : Créer son propre point d'entrée

```php
#!/usr/bin/env php
<?php

// bin/my-system

require './vendor/autoload.php';

use AndyDefer\Directive\Bootstrap\ApplicationBuilder;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\DirectiveServiceProvider;
use App\Providers\MyAppServiceProvider;

// ✅ Personnalisation complète
$app = ApplicationBuilder::internal([
    DirectiveServiceProvider::class,
    MyAppServiceProvider::class,      // Vos providers
])->withConfig([
    'app.name' => 'My Custom System',
    'app.debug' => true,
])->build();

$kernel = $app->make(DirectiveKernel::class);

// ✅ Ajouter vos sources
$kernel->addSource(__DIR__ . '/../src/Directives');
$kernel->addSource(__DIR__ . '/../modules/*/Directives');

// ✅ Configurer
$kernel->verbose(true)
    ->setLogBasePath('/var/log/my-system');

exit($kernel->run($argv)->value);
```

### Étape 2 : Créer ses propres directives

```php
<?php

// src/Directives/MyCustomDirective.php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class MyCustomDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'my:custom {--param=*}';
    }
    
    public function getDescription(): string
    {
        return 'Ma directive personnalisée';
    }
    
    protected function execute(): ExitCode
    {
        $params = $this->getVariadic('param');
        
        // Votre logique métier ici
        $this->info('Exécution de ma directive personnalisée');
        
        return ExitCode::SUCCESS;
    }
}
```

### Étape 3 : Utiliser son système

```bash
# Exécuter une directive
./bin/my-system my:custom --param=value1 --param=value2

# Voir l'aide
./bin/my-system help

# Lister les directives
./bin/my-system --list
```

### Étape 4 : Intégrer dans son application

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\Directive\DirectiveKernel;
use App\Directives\MyCustomDirective;

class DashboardController extends Controller
{
    public function runCustom(DirectiveKernel $kernel)
    {
        // Exécuter directement une directive
        $result = $kernel->runDirective(MyCustomDirective::class, [
            '--param=test'
        ]);
        
        return response()->json([
            'success' => $result->value === 0
        ]);
    }
}
```

---

## Avantages de créer son propre système

✅ **Contrôle total** : Vous décidez tout  
✅ **Intégration métier** : Aligné avec votre domaine  
✅ **Performance** : Chargez uniquement ce dont vous avez besoin  
✅ **Sécurité** : Contrôle d'accès granulaire  
✅ **Évolutivité** : Facile à étendre  
✅ **Maintenance** : Code clair et organisé  

---

## Voir aussi

- `ApplicationBuilder` - Builder pour créer l'application
- `DirectiveKernel` - Noyau d'exécution
- `DirectiveServiceProvider` - Provider principal
- `AbstractDirective` - Classe de base pour les directives
- `ApplicationType` - Types d'application