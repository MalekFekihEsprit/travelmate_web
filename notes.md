Voici une version **courte, propre et prête à mettre dans ton rapport / PPT** 👇

---

### 1️⃣ Fonctionnalités avancées

**Élaboration de fonctionnalités avancées**

* Authentification classique (login/signup)
* Authentification sociale (Google / GitHub via OAuth)
* Reconnaissance faciale (Face ID login)
* Détection d’échecs de connexion + capture photo
* Envoi d’alertes email de sécurité
* Système de score de confiance utilisateur dynamique
* Détection de connexions suspectes (IP / pays)
* Gestion du profil utilisateur (photo, infos, sécurité)

---

### 2️⃣ Utilisation de bundles / technologies externes

**Symfony UX, OAuth, etc.**

* Symfony UX Chart.js (statistiques dynamiques)
* Chart.js (graphiques)
* Symfony Mailer (envoi d’emails)
* KnpU OAuth2 Client Bundle (authentification sociale)
* Hotwired Stimulus (interactivité frontend)
* API IP (géolocalisation pays + indicatif téléphone)
* WebRTC (accès caméra navigateur)

---

### 3️⃣ Scénario + données de test

**Use case, realistic flow, demo logic**

* Parcours complet utilisateur (inscription → login → profil)
* Cas normal + cas d’erreur (mauvais mot de passe)
* Scénario sécurité (3 échecs → capture photo → email)
* Comptes de test variés (user simple / user sécurisé / admin)
* Données réalistes pour statistiques (inscriptions, rôles, âges)

---

### 4️⃣ Maîtrise du sujet

**Understanding + explaining + answering questions**

* Compréhension complète du système d’authentification Symfony
* Gestion des événements de sécurité (success / failure)
* Explication du scoring utilisateur et logique métier
* Intégration frontend + backend cohérente
* Capacité à expliquer chaque fonctionnalité en démo

---

### 5️⃣ Quantité de travail / valeur ajoutée

**Extra effort, originality**

* Ajout de fonctionnalités non demandées (Face ID, trust score, sécurité avancée)
* Amélioration UX (drapeau, indicatif auto, avatar fallback)
* Système de sécurité intelligent (photo + alertes)
* Dashboard admin avec statistiques interactives
* Approche proche d’une application réelle

---

### 6️⃣ Travail collaboratif (Git)

**Git usage, commits, workflow**

* Utilisation de Git pour le versioning
* Commits réguliers et organisés
* Historique de développement clair
* Travail structuré et évolutif

---

Si tu lis juste ça devant le prof, ça fait déjà très pro 👍





Oui — maintenant le travail demandé est clair. Tu dois faire **tests unitaires + PHPStan + DoctrineDoctor + rapport avec captures**. Les ateliers demandent précisément : `make:test` avec `TestCase` pour tester des règles métier, installer/configurer PHPStan puis analyser `src`, et installer DoctrineDoctor puis vérifier ses problèmes dans le profiler Symfony.   

---

# 0) Branche Git

```bash
git checkout integration
git pull origin integration
git checkout -b tests-performance-user
```

---

# 1) Tests unitaires PHPUnit

L’atelier demande de choisir une entité, définir au moins deux règles métier, créer un service métier, générer un test avec `make:test`, puis exécuter `php bin/phpunit`. 

## 1.1 Installer PHPUnit si besoin

```bash
composer require --dev symfony/test-pack
```

## 1.2 Créer un service métier à tester

Crée ce fichier :

### `src/Service/UserSecurityEvaluator.php`

```php
<?php

namespace App\Service;

use App\Entity\User;

class UserSecurityEvaluator
{
    public function calculateTrustScore(User $user, bool $suspiciousLogin = false): int
    {
        $score = 0;

        if ($user->isVerified()) {
            $score += 25;
        }

        if ($user->getTelephone()) {
            $score += 10;
        }

        if ($user->getPhotoFileName() || $user->getPhotoUrl()) {
            $score += 10;
        }

        if ($user->getFaceEmbedding()) {
            $score += 20;
        }

        if ($user->getNom() && $user->getPrenom() && $user->getDateNaissance()) {
            $score += 20;
        }

        if ($user->getFailedLoginAttempts() === 0) {
            $score += 15;
        }

        if ($user->getFailedLoginAttempts() >= 3) {
            $score -= 20;
        }

        if ($suspiciousLogin) {
            $score -= 20;
        }

        return max(0, min(100, $score));
    }

    public function shouldSendSecurityAlert(User $user): bool
    {
        return $user->getFailedLoginAttempts() >= 3;
    }

    public function isProfileComplete(User $user): bool
    {
        return
            !empty($user->getNom()) &&
            !empty($user->getPrenom()) &&
            !empty($user->getEmail()) &&
            $user->getDateNaissance() !== null &&
            !empty($user->getTelephone());
    }
}
```

## 1.3 Générer le test

```bash
php bin/console make:test
```

Choisis :

```text
TestCase
```

Nom :

```text
UserSecurityEvaluatorTest
```

## 1.4 Remplacer le fichier test

### `tests/UserSecurityEvaluatorTest.php`

```php
<?php

namespace App\Tests;

use App\Entity\User;
use App\Service\UserSecurityEvaluator;
use PHPUnit\Framework\TestCase;

class UserSecurityEvaluatorTest extends TestCase
{
    public function testTrustScoreIsHighForSecureUser(): void
    {
        $user = new User();
        $user->setNom('Fekih');
        $user->setPrenom('Malek');
        $user->setEmail('malek@test.com');
        $user->setTelephone('+216 12 345 678');
        $user->setDateNaissance(new \DateTime('2000-01-01'));
        $user->setIsVerified(true);
        $user->setPhotoFileName('profile.jpg');
        $user->setFaceEmbedding('[0.1,0.2,0.3]');
        $user->setFailedLoginAttempts(0);

        $evaluator = new UserSecurityEvaluator();

        $this->assertGreaterThanOrEqual(80, $evaluator->calculateTrustScore($user));
    }

    public function testTrustScoreDecreasesAfterFailedAttempts(): void
    {
        $user = new User();
        $user->setNom('Fekih');
        $user->setPrenom('Malek');
        $user->setEmail('malek@test.com');
        $user->setDateNaissance(new \DateTime('2000-01-01'));
        $user->setIsVerified(true);
        $user->setFailedLoginAttempts(3);

        $evaluator = new UserSecurityEvaluator();

        $this->assertLessThan(80, $evaluator->calculateTrustScore($user));
    }

    public function testSecurityAlertIsSentAfterThreeFailedAttempts(): void
    {
        $user = new User();
        $user->setFailedLoginAttempts(3);

        $evaluator = new UserSecurityEvaluator();

        $this->assertTrue($evaluator->shouldSendSecurityAlert($user));
    }

    public function testSecurityAlertIsNotSentBeforeThreeFailedAttempts(): void
    {
        $user = new User();
        $user->setFailedLoginAttempts(2);

        $evaluator = new UserSecurityEvaluator();

        $this->assertFalse($evaluator->shouldSendSecurityAlert($user));
    }

    public function testProfileCompleteness(): void
    {
        $user = new User();
        $user->setNom('Ben Salah');
        $user->setPrenom('Aya');
        $user->setEmail('aya@test.com');
        $user->setTelephone('+216 99 999 999');
        $user->setDateNaissance(new \DateTime('2001-05-10'));

        $evaluator = new UserSecurityEvaluator();

        $this->assertTrue($evaluator->isProfileComplete($user));
    }
}
```

## 1.5 Lancer les tests

```bash
php bin/phpunit
```

À capturer pour le rapport :

* capture d’écran du terminal avec tests OK
* si tu as une erreur au début, capture “avant correction”
* puis capture “après correction”

---

# 2) PHPStan

L’atelier demande d’installer PHPStan avec `composer require --dev phpstan/phpstan`, vérifier la version, lancer `vendor/bin/phpstan analyse src`, puis créer `phpstan.neon` avec un niveau d’analyse et relancer. 

## 2.1 Installer PHPStan

```bash
composer require --dev phpstan/phpstan
```

Vérifier :

```bash
vendor/bin/phpstan version
```

## 2.2 Première analyse avant configuration

```bash
vendor/bin/phpstan analyse src
```

Capture cette sortie pour la partie **Avant optimisation**.

## 2.3 Créer `phpstan.neon`

À la racine du projet :

### `phpstan.neon`

```neon
parameters:
    level: 5
    paths:
        - src
    tmpDir: var/cache/phpstan
```

Relancer :

```bash
vendor/bin/phpstan analyse
```

## 2.4 Niveau plus strict

Dans le workshop, ils demandent aussi d’augmenter le niveau à `8` pour détecter plus d’erreurs. 

Change :

```neon
level: 8
```

Puis :

```bash
vendor/bin/phpstan analyse
```

## 2.5 Corrections fréquentes

PHPStan va probablement te signaler :

* méthodes sans type de retour
* paramètres non typés
* possible `null`
* méthode inexistante
* accès à propriété/méthode sur `mixed`

Exemples de corrections :

```php
public function index(): Response
```

```php
public function calculateTrustScore(User $user): int
```

```php
if (!$user instanceof User) {
    throw new \LogicException('Utilisateur invalide.');
}
```

Après corrections :

```bash
vendor/bin/phpstan analyse
```

Capture :

* erreurs avant
* résultat après

---

# 3) DoctrineDoctor

L’atelier demande d’installer `ahmed-bhs/doctrine-doctor`, d’aller sur une page Symfony en dev, d’ouvrir le profiler, de cliquer sur le panel “Doctrine Doctor”, puis de corriger les problèmes un par un. 

## 3.1 Installation

```bash
composer require --dev ahmed-bhs/doctrine-doctor
```

Si erreur d’installation, le workshop donne cette commande alternative : 

```bash
composer require ahmed-bhs/doctrine-doctor:^1.0 webmozart/assert:^1.11 --with-all-dependencies
composer require --dev ahmed-bhs/doctrine-doctor
```

## 3.2 Vérifier `config/bundles.php`

Si le panel n’apparaît pas dans le profiler, ajoute : 

```php
AhmedBhs\DoctrineDoctor\DoctrineDoctorBundle::class => ['dev' => true],
```

## 3.3 Lancer et analyser

```bash
symfony server:start
```

Va sur :

* `/`
* `/admin/users`
* `/admin/users/stats`
* `/profile`

Puis ouvre la toolbar Symfony en bas → clique **Doctrine Doctor**.

Capture :

* integrity
* security
* configurations
* slowest queries

Le workshop demande justement de faire des captures des problèmes signalés. 

## 3.4 Après corrections

Après chaque correction :

```bash
symfony server:stop
php bin/console cache:clear
symfony server:start
```

C’est exactement demandé dans l’atelier. 

---

# 4) Mesures de performance pour le rapport

Le rapport demande de comparer avant/après PHPStan, tests unitaires, DoctrineDoctor, puis de remplir les indicateurs : temps moyen de réponse page d’accueil, temps d’exécution d’une fonctionnalité principale, utilisation mémoire. 

## 4.1 Mesurer page d’accueil

Dans navigateur :

* F12
* Network
* recharge `/`
* prends le temps en ms

À remplir :

```text
Temps moyen de réponse de la page d’accueil : XXX ms
```

## 4.2 Mesurer fonctionnalité principale

Choisis une fonctionnalité :

* login utilisateur
* stats admin
* login sécurité après échecs
* recherche dynamique admin

Je te conseille :

```text
Fonctionnalité principale : chargement de /admin/users/stats
```

Mesure dans Network ou Symfony profiler.

## 4.3 Mesurer mémoire

Dans Symfony Profiler :

* ouvre une page
* regarde Memory usage

À remplir :

```text
Utilisation mémoire : XX MB
```

---

# 5) Valeur ajoutée à écrire

Dans le rapport, pour “Valeur ajoutée”, écris court :

```text
Dans le module Gestion Utilisateur, nous avons ajouté plusieurs fonctionnalités avancées :
- authentification sociale via OAuth ;
- reconnaissance faciale avec microservice IA ;
- détection de tentatives de connexion suspectes ;
- capture photo après plusieurs échecs de connexion ;
- envoi d’alertes email de sécurité ;
- score de confiance utilisateur dynamique ;
- géolocalisation IP avec drapeau et indicatif téléphonique ;
- statistiques dynamiques avec Symfony UX Chart.js.
```

---

# 6) Texte prêt pour le rapport

## PHPStan — Avant

```text
Avant correction, PHPStan a détecté plusieurs problèmes liés au typage, aux valeurs potentiellement nulles et à certaines méthodes insuffisamment typées. Ces erreurs ont été capturées dans le terminal lors de l’analyse du dossier src.
```

## PHPStan — Après

```text
Après correction, les types de retour, les paramètres et les accès aux valeurs nulles ont été améliorés. Une nouvelle analyse PHPStan a été relancée afin de vérifier la réduction des erreurs.
```

## Tests unitaires

```text
Des tests unitaires ont été réalisés sur la logique métier du module utilisateur, notamment le calcul du score de confiance, la détection des tentatives échouées et la complétion du profil utilisateur.
```

## DoctrineDoctor

```text
DoctrineDoctor a été utilisé depuis le profiler Symfony afin d’analyser les problèmes liés à l’intégrité, la sécurité, la configuration et les requêtes lentes. Les problèmes détectés ont été corrigés progressivement puis vérifiés après vidage du cache et redémarrage du serveur.
```

---

# 7) Checklist finale à faire maintenant

Fais dans cet ordre :

```bash
git checkout -b tests-performance-user
composer require --dev symfony/test-pack
composer require --dev phpstan/phpstan
composer require --dev ahmed-bhs/doctrine-doctor
```

Puis :

```bash
php bin/console make:test
php bin/phpunit
vendor/bin/phpstan analyse src
vendor/bin/phpstan analyse
php bin/console cache:clear
symfony server:start
```

Ensuite :

* capture PHPUnit
* capture PHPStan avant/après
* capture DoctrineDoctor avant/après
* capture profiler performance
* remplir le rapport Word

---

# 8) Ce que tu peux répartir dans l’équipe

Comme c’est un travail de groupe :

* toi : tests unitaires module utilisateur + valeur ajoutée IA/sécurité
* Minyar : tests voyages/participants
* autre membre : PHPStan global
* autre membre : DoctrineDoctor + captures
* un membre : rapport Word final

Comme ça, tout le monde participe.


