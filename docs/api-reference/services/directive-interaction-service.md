# DirectiveInteractionService - Référence Technique

## Description

Service central pour toutes les interactions utilisateur dans les directives CLI. Gère l'affichage des messages, la capture des entrées utilisateur et le rendu des tableaux. Délègue le rendu à `RenderDispatcher` et l'entrée utilisateur à `InputDispatcher`.

## Hiérarchie

```
DirectiveInteractionService (final)
    ├── Dépend de : RenderDispatcher
    └── Dépend de : InputDispatcher
```

## Rôle principal

Faire le pont entre les directives et les tâches de rendu/entrée. Fournit une API simple et cohérente pour afficher des messages (info, erreur, avertissement), poser des questions, demander des confirmations et afficher des tableaux formatés.

## Installation

```bash
composer require andydefer/laravel-directive
```

## API / Méthodes publiques

### `__construct(RenderDispatcher $renderDispatcher, InputDispatcher $inputDispatcher): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$renderDispatcher` | `RenderDispatcher` | Tâche de rendu pour l'affichage |
| `$inputDispatcher` | `InputDispatcher` | Tâche d'entrée pour la capture utilisateur |

Constructeur du service. Reçoit les deux dispatchers nécessaires pour le rendu et l'entrée.

**Exemple :**
```php
$renderDispatcher = new RenderDispatcher();
$inputDispatcher = new InputDispatcher();
$interaction = new DirectiveInteractionService($renderDispatcher, $inputDispatcher);
```

### `line(string $message): void`

Affiche un message texte brut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message à afficher |

**Exemple :**
```php
$this->interaction->line('Processing users...');
```

### `info(string $message): void`

Affiche un message d'information (généralement en vert).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message d'information |

**Exemple :**
```php
$this->interaction->info('Operation completed successfully!');
```

### `error(string $message): void`

Affiche un message d'erreur (généralement en rouge).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message d'erreur |

**Exemple :**
```php
$this->interaction->error('Failed to connect to database.');
```

### `warn(string $message): void`

Affiche un message d'avertissement (généralement en jaune).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `string` | Le message d'avertissement |

**Exemple :**
```php
$this->interaction->warn('This operation may take a while.');
```

### `newLine(): void`

Affiche une ligne vide.

**Exemple :**
```php
$this->interaction->newLine();
```

### `separator(string $character = '-', int $length = 80): void`

Affiche une ligne de séparation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$character` | `string` | Caractère de séparation (défaut: '-') |
| `$length` | `int` | Longueur de la ligne (défaut: 80) |

**Exemple :**
```php
$this->interaction->separator('=', 100);
$this->interaction->line('Section Title');
$this->interaction->separator();
```

### `ask(string $question): string`

Pose une question et retourne la réponse de l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | La question à poser |

**Retourne :** `string` - La réponse saisie (trimée)

**Exemple :**
```php
$name = $this->interaction->ask('What is your name?');
```

### `confirm(string $question): bool`

Demande une confirmation et retourne le choix de l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$question` | `string` | La question de confirmation |

**Retourne :** `bool` - `true` pour `y`/`yes`, `false` pour `n`/`no`

**Exemple :**
```php
if ($this->interaction->confirm('Do you want to continue?')) {
    // Continuer
}
```

### `askUserChoice(string $name, int $max): int`

Demande à l'utilisateur de choisir dans une liste numérotée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Le nom du choix (pour l'affichage) |
| `$max` | `int` | Le nombre maximum de choix (1 à max) |

**Retourne :** `int` - Le numéro choisi (1 à max), ou `0` si invalide

**Exemple :**
```php
$this->interaction->line('1. List users');
$this->interaction->line('2. Create user');
$this->interaction->line('3. Exit');

$choice = $this->interaction->askUserChoice('Select an action', 3);
```

### `table(StringTypedCollection $headers, RowCollection $rows): void`

Affiche un tableau formaté avec en-têtes et lignes.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$headers` | `StringTypedCollection` | Les en-têtes du tableau |
| `$rows` | `RowCollection` | Les lignes du tableau |

**Exemple :**
```php
$headers = new StringTypedCollection();
$headers->add('ID', 'Name', 'Email');

$rows = new RowCollection();
$row = new RowCollection();
$row->add(1, 'John Doe', 'john@example.com');
$rows->add($row);

$this->interaction->table($headers, $rows);
```

## Cas d'utilisation

### Cas 1 : Directive interactive complète

```php
final class SetupDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        $this->interaction->info('Welcome to the setup wizard!');
        
        $appName = $this->interaction->ask('Application name');
        $environment = $this->interaction->ask('Environment (local/production)');
        
        if (!$this->interaction->confirm("Create configuration for {$appName}?")) {
            $this->interaction->warn('Setup cancelled');
            return ExitCode::SUCCESS;
        }
        
        $headers = new StringTypedCollection();
        $headers->add('Setting', 'Value');
        
        $rows = new RowCollection();
        $row = new RowCollection();
        $row->add('App Name', $appName);
        $row->add('Environment', $environment);
        $rows->add($row);
        
        $this->interaction->table($headers, $rows);
        $this->interaction->info('Setup completed!');
        
        return ExitCode::SUCCESS;
    }
}
```

### Cas 2 : Menu avec choix utilisateur

```php
public function execute(): ExitCode
{
    $this->interaction->info('Available actions:');
    $this->interaction->line('1. List users');
    $this->interaction->line('2. Create user');
    $this->interaction->line('3. Delete user');
    $this->interaction->line('4. Exit');
    
    $choice = $this->interaction->askUserChoice('Select an action', 4);
    
    return match($choice) {
        1 => $this->listUsers(),
        2 => $this->createUser(),
        3 => $this->deleteUser(),
        4 => ExitCode::SUCCESS,
        default => ExitCode::INVALID_ARGUMENT,
    };
}
```

### Cas 3 : Résultats en tableau

```php
public function execute(): ExitCode
{
    $users = $this->userRepository->all();
    
    $headers = new StringTypedCollection();
    $headers->add('ID', 'Name', 'Email', 'Status');
    
    $rows = new RowCollection();
    foreach ($users as $user) {
        $row = new RowCollection();
        $row->add($user->id, $user->name, $user->email, $user->active ? 'Active' : 'Inactive');
        $rows->add($row);
    }
    
    $this->interaction->table($headers, $rows);
    $this->interaction->info('Total: ' . count($users) . ' users');
    
    return ExitCode::SUCCESS;
}
```

### Cas 4 : Directive de progression

```php
public function execute(): ExitCode
{
    $items = ['task1', 'task2', 'task3'];
    $total = count($items);
    
    $this->interaction->info("Processing {$total} items...");
    
    foreach ($items as $index => $item) {
        $this->interaction->line("  [{".($index+1)."/{$total}] Processing {$item}...");
        // Traitement...
        $this->interaction->line("  ✓ Completed");
    }
    
    $this->interaction->newLine();
    $this->interaction->info('All tasks completed!');
    
    return ExitCode::SUCCESS;
}
```

### Cas 5 : Directive d'information système

```php
public function execute(): ExitCode
{
    $info = [
        ['PHP Version', phpversion()],
        ['Memory Limit', ini_get('memory_limit')],
        ['Max Execution Time', ini_get('max_execution_time')],
    ];
    
    $headers = new StringTypedCollection();
    $headers->add('Setting', 'Value');
    
    $rows = new RowCollection();
    foreach ($info as $rowData) {
        $row = new RowCollection();
        $row->add($rowData[0], $rowData[1]);
        $rows->add($row);
    }
    
    $this->interaction->info('System Information:');
    $this->interaction->table($headers, $rows);
    
    return ExitCode::SUCCESS;
}
```

## Flux d'exécution

```
1. Appel d'une méthode d'interaction
   │
   ├── line() / info() / error() / warn()
   │   ├── Crée DisplayMessageRecord avec MessageType
   │   ├── Crée RenderRecord avec RenderType::DISPLAY_MESSAGE
   │   └── RenderDispatcher->execute() → affichage
   │
   ├── ask()
   │   ├── Crée QuestionRecord
   │   └── InputDispatcher->execute(InputType::SIMPLE_QUESTION) → retourne string
   │
   ├── confirm()
   │   ├── Crée QuestionRecord
   │   └── InputDispatcher->execute(InputType::CONFIRMATION) → retourne bool
   │
   ├── askUserChoice()
   │   ├── Crée UserChoiceRecord
   │   └── InputDispatcher->execute(InputType::USER_CHOICE) → retourne int
   │
   └── table()
       ├── Crée DisplayTableRecord
       ├── Crée RenderRecord avec RenderType::TABLE
       └── RenderDispatcher->execute() → affichage du tableau
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Entrée utilisateur vide pour `ask()` | Retourne une chaîne vide (`''`) |
| Confirmation avec réponse invalide | `confirm()` retourne `false` |
| Choix utilisateur non numérique | `askUserChoice()` retourne `0` |
| Choix utilisateur hors plage (1-max) | `askUserChoice()` retourne `0` |

## Intégration

`DirectiveInteractionService` s'intègre avec :

- **`RenderDispatcher`** : Tâche de rendu pour l'affichage des messages et tableaux
- **`InputDispatcher`** : Tâche d'entrée pour la capture utilisateur
- **`MessageType`** : Enum des types de messages (`LINE`, `INFO`, `ERROR`, `WARNING`)
- **`InputType`** : Enum des types d'entrée (`SIMPLE_QUESTION`, `CONFIRMATION`, `USER_CHOICE`)
- **`RenderType`** : Enum des types de rendu (`DISPLAY_MESSAGE`, `TABLE`)
- **`DisplayMessageRecord`** : Record pour les messages
- **`DisplayTableRecord`** : Record pour les tableaux
- **`QuestionRecord`** : Record pour les questions
- **`UserChoiceRecord`** : Record pour les choix utilisateur

## Performance

| Aspect | Caractéristique |
|--------|----------------|
| Affichage | Délégation à `RenderDispatcher` (pas de surcharge) |
| Entrée | Délégation à `InputDispatcher` (temps réel utilisateur) |
| Tableau | O(n × m) avec n = lignes, m = colonnes |
| Messages | O(1) - simple création de records |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Requis (types union, readonly) |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Dispatchers\InputDispatcher;
use AndyDefer\Directive\Dispatchers\RenderDispatcher;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Directive\Collections\RowCollection;

// 1. Créer les dépendances
$renderDispatcher = new RenderDispatcher();
$inputDispatcher = new InputDispatcher();

// 2. Créer le service d'interaction
$interaction = new DirectiveInteractionService($renderDispatcher, $inputDispatcher);

// 3. Afficher un message de bienvenue
$interaction->info('Welcome to the application!');
$interaction->separator();

// 4. Poser une question
$name = $interaction->ask('What is your name?');
$interaction->line("Hello, {$name}!");

// 5. Demander une confirmation
if ($interaction->confirm('Do you want to see the information?')) {
    // 6. Afficher un tableau
    $headers = new StringTypedCollection();
    $headers->add('Item', 'Value');
    
    $rows = new RowCollection();
    $row = new RowCollection();
    $row->add('Name', $name);
    $row->add('Time', date('Y-m-d H:i:s'));
    $row->add('PHP Version', phpversion());
    $rows->add($row);
    
    $interaction->table($headers, $rows);
}

// 7. Menu de choix
$interaction->newLine();
$interaction->line('1. Option One');
$interaction->line('2. Option Two');
$interaction->line('3. Exit');

$choice = $interaction->askUserChoice('Select an option', 3);

switch ($choice) {
    case 1:
        $interaction->info('You selected Option One');
        break;
    case 2:
        $interaction->info('You selected Option Two');
        break;
    case 3:
        $interaction->warn('Exiting...');
        break;
    default:
        $interaction->error('Invalid selection');
}

// 8. Message de fin
$interaction->separator();
$interaction->info('Goodbye!');
```

## Voir aussi

- [`RenderDispatcher`](../tasks/render-task.md) - Tâche de rendu
- [`InputDispatcher`](../tasks/input-task.md) - Tâche d'entrée utilisateur
- [`MessageType`](../enums/message-type.md) - Types de messages
- [`InputType`](../enums/input-type.md) - Types d'entrée
- [`RenderType`](../enums/render-type.md) - Types de rendu
- [`RowCollection`](../collections/row-collection.md) - Collection pour lignes de tableau
---