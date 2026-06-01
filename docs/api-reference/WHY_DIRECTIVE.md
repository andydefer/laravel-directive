# Pourquoi Laravel Directive ?

## Le manifeste d'une alternative à Artisan

---

## Introduction : Le constat

Laravel Artisan est un outil remarquable. Il a posé les bases de ce qu'une CLI frameworkée devrait être. Mais après des années à l'utiliser, à l'étendre, et à le subir, des fissures apparaissent.

Ce manifeste n'est pas une attaque contre Artisan. C'est une analyse honnête de ses limites et la présentation d'une alternative qui les adresse.

---

## Les 7 problèmes fondamentaux d'Artisan

### 1. L'héritage unique imposé

**Le problème :** Pour créer une commande Artisan, vous DEVEZ étendre `Illuminate\Console\Command`.

```php
// Artisan - Pas le choix
class MyCommand extends Command { }

// Votre classe ne peut rien étendre d'autre
// Impossible d'utiliser un Value Object ou une autre base
```

**Pourquoi c'est un problème :**
- Votre logique métier est enfermée dans une hiérarchie imposée
- Impossible de réutiliser une classe existante comme commande
- Le pattern Template Method vous force à implémenter `handle()` mais vous laisse peu de contrôle sur le reste

**La solution Directive :**
```php
// Directive - Vous choisissez
class MyDirective extends AbstractDirective { }
// ou
final class MyDirective extends AbstractDirective { } // final, si vous voulez
// Votre logique reste VOTRE logique
```

---

### 2. Le couplage logique métier / présentation

**Le problème :** Dans Artisan, votre `handle()` contient à la fois la logique métier ET l'affichage.

```php
// Artisan - Tout est mélangé
public function handle()
{
    $users = User::all();  // Logique métier
    
    // Mélangé avec l'affichage
    $this->table(['ID', 'Name'], $users->toArray());
    $this->info('Done!');
    
    // Pas de séparation, pas de test unitaire pur
}
```

**Pourquoi c'est un problème :**
- Impossible de tester la logique métier sans tester l'affichage
- Changer le format de sortie (JSON, XML, etc.) demande de modifier la commande
- Le Single Responsibility Principle est violé

**La solution Directive :**
```php
// Directive - Logique et présentation séparées
public function execute(): ExitCode
{
    // Logique métier pure
    $users = $this->userRepository->getActiveUsers();
    
    // Présentation déléguée
    $this->renderUsers($users);
    
    return ExitCode::SUCCESS;
}

private function renderUsers(array $users): void
{
    // L'affichage peut être mocké, remplacé, ou testé séparément
    $this->table($this->getHeaders(), $this->formatRows($users));
}
```

---

### 3. La testabilité impossible

**Le problème :** Les commandes Artisan sont extrêmement difficiles à tester proprement.

```php
// Artisan - Comment tester ça proprement ?
public function handle()
{
    $name = $this->ask('What is your name?');  // ← impossible à mock
    
    if ($this->confirm('Continue?')) {          // ← impossible à mock
        // ...
    }
}
```

**Pourquoi c'est un problème :**
- `ask()` et `confirm()` ne peuvent pas être mockés facilement
- Les tests d'acceptance sont lourds (`artisan command --flag`)
- Impossible de faire des tests unitaires ; forcé de faire des tests d'intégration

**La solution Directive :**
```php
// Directive - Tout est injectable et mockable
class MyDirective extends AbstractDirective
{
    public function __construct(
        DirectiveInteractionService $interaction,  // ← injecté, donc mockable
        private readonly UserRepository $users,    // ← vos dépendances
    ) {
        parent::__construct($interaction);
    }
    
    public function execute(): ExitCode
    {
        $name = $this->ask('Name?');  // ← interaction service, donc mockable
        // ...
    }
}
```

En test :
```php
// Test unitaire pur - l'interaction est mockée
$interaction->expects($this->once())->method('ask')->willReturn('John');
```

---

### 4. L'extensibilité des packages inexistante

**Le problème :** Un package Laravel ne peut pas facilement enregistrer ses propres commandes.

**Pourquoi c'est un problème :**
- Obligation d'utiliser le service provider et `$this->commands([...])`
- Les commandes doivent être explicitement listées
- Pas de découverte automatique
- Pour un package qui livre 10 commandes, c'est 10 lignes à maintenir

**La solution Directive :**
```php
// Découverte automatique - rien à configurer
// Il suffit de placer vos directives dans src/Directives/
```

Le package scanne automatiquement :
- `app/Directives/*.php` (l'application)
- `vendor/*/src/Directives/*.php` (les packages)

**Aucune configuration requise.**

---

### 5. L'absence de typage fort

**Le problème :** Artisan passe les arguments et options comme un tableau brut.

```php
// Artisan - Tableau brut non typé
protected $signature = 'user:create {name} {--role=admin}';

public function handle()
{
    $name = $this->argument('name');     // mixed, pas typé
    $role = $this->option('role');       // mixed, pas typé
    
    // Si $name n'existe pas, c'est null
    // Si $role n'existe pas, c'est null
    // Tout est à vérifier manuellement
}
```

**La solution Directive :**
```php
// Directive - Accès typé
public function execute(): ExitCode
{
    // string|null - vous savez ce que vous manipulez
    $name = $this->argument('name');
    
    // bool|string|null - les flags sont automatiquement des booléens
    $force = $this->option('force');  // true ou false
    
    // hasArgument() / hasOption() pour vérifier l'existence
    if ($this->hasArgument('count')) {
        $count = (int) $this->argument('count');
    }
}
```

---

### 6. Le parsing des signatures rigide

**Le problème :** La signature d'Artisan permet des caractères ambigus.

```php
// Artisan - Syntaxe permissive
protected $signature = 'user:create {name?} {--role=admin}';
```

**Pourquoi c'est un problème :**
- Les `:` et `_` peuvent causer des conflits selon les shells
- Pas de validation stricte du format
- Des erreurs silencieuses

**La solution Directive :**
```php
// Directive - Format strict validé
// Seuls les tirets '-' sont autorisés, pas d'underscore, pas de deux-points
public function getSignature(): string
{
    return 'user-create {name} {email} {--role=admin}';
}

// Ordre imposé et validé :
// 1. Arguments requis
// 2. Arguments avec valeur par défaut
// 3. Arguments optionnels
// 4. Options
```

Et surtout, **validation automatique** :
- Signature invalide → exit code `INVALID_ARGUMENT`
- Message d'erreur clair avec la raison

---

### 7. L'absence de bootstrap Laravel à la demande

**Le problème :** Artisan charge TOUT Laravel, TOUT LE TEMPS.

```php
// Même pour une commande qui fait juste echo "Hello World"
// Laravel charge la base de données, le cache, les providers, etc.
```

**Pourquoi c'est un problème :**
- Des commandes simples sont pénalisées par un bootstrap lourd
- Performance dégradée
- Pas de contrôle sur ce qui est chargé

**La solution Directive :**
```php
// Directive - Bootstrap uniquement si demandé
public function shouldBootLaravel(): bool
{
    return false;  // Par défaut, pas de bootstrap
}

// Pour une directive qui a besoin d'Eloquent :
public function shouldBootLaravel(): bool
{
    return true;   // Bootstrap uniquement pour celle-ci
}
```

**Résultat :**
- Les directives simples s'exécutent en millisecondes
- Le bootstrap se fait une seule fois par exécution
- Contrôle total sur les performances

---

## Les avantages synthétisés

| Problème Artisan | Solution Directive |
|-----------------|-------------------|
| Héritage unique imposé | `AbstractDirective` mais vous pouvez faire `final` |
| Logique métier + présentation mélangées | Séparation claire via `execute()` |
| Testabilité difficile (ask/confirm) | Injection de `DirectiveInteractionService` |
| Extensibilité manuelle des packages | Découverte automatique (`vendor/*/src/Directives/`) |
| Pas de typage (`array` brut) | `argument(): ?string`, `option(): bool\|string\|null` |
| Parsing permissif | Format strict + validation automatique |
| Bootstrap Laravel systématique | Bootstrap à la demande (`shouldBootLaravel()`) |

---

## Les inconvénients assumés

Aucune solution n'est parfaite. Directive a aussi ses compromis :

### 1. Une dépendance supplémentaire
- Artisan est natif à Laravel
- Directive ajoute `andydefer/php-records` comme dépendance

### 2. Une courbe d'apprentissage
- Artisan est la solution "standard"
- Directive demande d'apprendre une nouvelle API

### 3. Moins de "magie"
- Artisan a des années d'optimisation
- Directive est plus récente, avec moins de recul

### 4. Pas de compatibilité directe
- Vous ne pouvez PAS exécuter une directive via `php artisan`
- Vous devez utiliser `./vendor/bin/directive`
- C'est un choix délibéré : une philosophie différente

---

## Pour qui est ce package ?

### Vous devriez utiliser Directive si :

- ✅ Vous voulez une **séparation claire** entre logique métier et présentation
- ✅ Vous avez des **commandes simples** qui ne nécessitent pas Laravel
- ✅ Vous **testez intensivement** vos commandes
- ✅ Vous développez des **packages** avec des commandes
- ✅ Vous voulez une **API typée** pour les arguments et options
- ✅ Vous voulez **contrôler** quand Laravel est bootstrappé

### Vous devriez rester sur Artisan si :

- ❌ Vous êtes satisfait d'Artisan
- ❌ Vous ne voulez pas de dépendance supplémentaire
- ❌ Toutes vos commandes ont besoin de Laravel de toute façon
- ❌ Vous préférez la solution "officielle"

---

## Conclusion : Une question de philosophie

Artisan et Directive ne sont pas en compétition. Ils répondent à des besoins différents.

**Artisan** excelle quand :
- Votre commande a BESOIN de Laravel
- Vous êtes dans un écosystème 100% Laravel
- La "magie" vous convient

**Directive** excelle quand :
- Vous voulez séparer logique et présentation
- Vous testez intensivement
- Vous voulez contrôler le bootstrap
- Vous développez des packages réutilisables

**Laravel Directive n'est pas un remplacement d'Artisan. C'est une alternative pour ceux qui veulent une architecture différente.**

---

## Un dernier mot

Ce package est né de la frustration. La frustration de ne pas pouvoir tester proprement. La frustration de voir des commandes simples payer le prix d'un bootstrap lourd. La frustration de devoir lister manuellement chaque commande dans un package.

Mais cette frustration a donné naissance à une solution. Pas parfaite, mais honnête.

**Laravel Directive : pour ceux qui veulent écrire des commandes CLI comme ils écrivent le reste de leur code : propre, testable, et découplé.**

---

*Andy Defer*

---

## Annexe : Comparaison côte à côte

### Artisan
```php
use Illuminate\Console\Command;

class UserCreateCommand extends Command
{
    protected $signature = 'user:create {name} {email} {--role=admin}';
    protected $description = 'Create a new user';
    
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $role = $this->option('role');
        
        // Logique + affichage mélangés
        $this->info("User {$name} created with role {$role}");
        
        return 0;
    }
}
```

### Directive
```php
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-create {name} {email} {--role=admin}';
    }
    
    public function getDescription(): string
    {
        return 'Create a new user';
    }
    
    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $role = $this->option('role');
        
        // Logique pure ici
        $this->userService->create($name, $email, $role);
        
        // Affichage délégué
        $this->info("User {$name} created with role {$role}");
        
        return ExitCode::SUCCESS;
    }
}
```

La différence ? La **séparation**. Et c'est toute la philosophie.