Oui. On peut le faire comme **une seule fonctionnalité cohérente** :

# Smart User Trust & Security System

qui regroupe :

* **échecs de connexion**
* **capture photo après 3 mauvais mots de passe**
* **email d’alerte**
* **détection de connexion suspecte**
* **score de confiance utilisateur**

Et on va le faire de manière réaliste pour Symfony 6.4.

Important : la **photo via webcam dépend de l’autorisation du navigateur**. Si l’utilisateur refuse l’accès caméra, on envoie quand même l’alerte email sans photo.

Les hooks Symfony pour l’échec d’authentification passent bien par `onAuthenticationFailure()` dans un custom authenticator, et Symfony a aussi une protection native de type **login throttling** via RateLimiter, donc on reste dans une approche alignée avec le framework. ([symfony.com][1])

---

# Vue d’ensemble du plan

On va le faire en 7 étapes :

1. **ajouter des colonnes** dans `user`
2. **créer un service de score de confiance**
3. **créer un service d’alerte sécurité**
4. **modifier le login form** pour capturer une photo après 2 échecs
5. **modifier `LoginFormAuthenticator`**

   * incrémenter les échecs
   * déclencher l’alerte au 3e échec
   * détecter connexion suspecte au succès
6. **afficher le score et l’état sécurité**

   * dans le profil
   * dans l’admin
7. **tester tout le flow**

---

# 1) Ajouter les colonnes SQL

Ajoute ces colonnes dans ta table `user` :

```sql
ALTER TABLE user
ADD failed_login_attempts INT NOT NULL DEFAULT 0,
ADD last_failed_login_at DATETIME DEFAULT NULL,
ADD trust_score INT NOT NULL DEFAULT 50,
ADD suspicious_login_count INT NOT NULL DEFAULT 0,
ADD last_login_country_code VARCHAR(10) DEFAULT NULL,
ADD security_alert_photo VARCHAR(255) DEFAULT NULL;
```

Si tu utilises Doctrine migrations, adapte ensuite ton entité et lance la migration.

---

# 2) Ajouter les propriétés dans l’entité `User`

Dans `src/Entity/User.php`, ajoute les propriétés + getters/setters.

```php
#[ORM\Column(type: 'integer', options: ['default' => 0])]
private int $failed_login_attempts = 0;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $last_failed_login_at = null;

#[ORM\Column(type: 'integer', options: ['default' => 50])]
private int $trust_score = 50;

#[ORM\Column(type: 'integer', options: ['default' => 0])]
private int $suspicious_login_count = 0;

#[ORM\Column(type: 'string', length: 10, nullable: true)]
private ?string $last_login_country_code = null;

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $security_alert_photo = null;
```

Puis ajoute :

```php
public function getFailedLoginAttempts(): int
{
    return $this->failed_login_attempts;
}

public function setFailedLoginAttempts(int $failed_login_attempts): static
{
    $this->failed_login_attempts = $failed_login_attempts;
    return $this;
}

public function getLastFailedLoginAt(): ?\DateTimeInterface
{
    return $this->last_failed_login_at;
}

public function setLastFailedLoginAt(?\DateTimeInterface $last_failed_login_at): static
{
    $this->last_failed_login_at = $last_failed_login_at;
    return $this;
}

public function getTrustScore(): int
{
    return $this->trust_score;
}

public function setTrustScore(int $trust_score): static
{
    $this->trust_score = $trust_score;
    return $this;
}

public function getSuspiciousLoginCount(): int
{
    return $this->suspicious_login_count;
}

public function setSuspiciousLoginCount(int $suspicious_login_count): static
{
    $this->suspicious_login_count = $suspicious_login_count;
    return $this;
}

public function getLastLoginCountryCode(): ?string
{
    return $this->last_login_country_code;
}

public function setLastLoginCountryCode(?string $last_login_country_code): static
{
    $this->last_login_country_code = $last_login_country_code;
    return $this;
}

public function getSecurityAlertPhoto(): ?string
{
    return $this->security_alert_photo;
}

public function setSecurityAlertPhoto(?string $security_alert_photo): static
{
    $this->security_alert_photo = $security_alert_photo;
    return $this;
}
```

---

# 3) Créer le service de score de confiance

Crée :

## `src/Service/UserTrustScoreService.php`

```php
<?php

namespace App\Service;

use App\Entity\User;

class UserTrustScoreService
{
    public function calculate(User $user, bool $suspiciousLogin = false): int
    {
        $score = 0;

        if ($user->isVerified()) {
            $score += 25;
        }

        if ($user->getFaceEmbedding()) {
            $score += 20;
        }

        if ($user->getTelephone()) {
            $score += 10;
        }

        if ($user->getPhotoFileName() || $user->getPhotoUrl()) {
            $score += 10;
        }

        if ($user->getNom() && $user->getPrenom() && $user->getDateNaissance()) {
            $score += 20;
        }

        if ($user->getFailedLoginAttempts() === 0) {
            $score += 15;
        }

        if ($suspiciousLogin) {
            $score -= 20;
        }

        if ($user->getFailedLoginAttempts() >= 3) {
            $score -= 20;
        } elseif ($user->getFailedLoginAttempts() > 0) {
            $score -= 10;
        }

        if ($user->getSuspiciousLoginCount() > 0) {
            $score -= min(20, $user->getSuspiciousLoginCount() * 5);
        }

        return max(0, min(100, $score));
    }

    public function getLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Élevé',
            $score >= 50 => 'Moyen',
            default => 'Faible',
        };
    }
}
```

---

# 4) Créer le service d’alerte sécurité

Crée :

## `src/Service/SecurityAlertService.php`

```php
<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpKernel\KernelInterface;

class SecurityAlertService
{
    public function __construct(
        private MailerInterface $mailer,
        private KernelInterface $kernel
    ) {
    }

    public function saveBase64Photo(?string $base64Image, string $prefix = 'security-alert'): ?string
    {
        if (!$base64Image || !str_starts_with($base64Image, 'data:image/')) {
            return null;
        }

        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]);
        $data = substr($base64Image, strpos($base64Image, ',') + 1);
        $decoded = base64_decode($data);

        if ($decoded === false) {
            return null;
        }

        $directory = $this->kernel->getProjectDir() . '/public/uploads/security-alerts';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $filename = $prefix . '-' . uniqid() . '.' . $extension;
        $path = $directory . '/' . $filename;

        file_put_contents($path, $decoded);

        return $filename;
    }

    public function sendFailedLoginAlert(User $user, ?string $photoFilename = null): void
    {
        $email = (new Email())
            ->from('travelmate@example.com')
            ->to($user->getEmail())
            ->subject('Alerte sécurité - Tentatives de connexion suspectes')
            ->html("
                <h2>Alerte sécurité</h2>
                <p>Nous avons détecté au moins 3 tentatives de connexion échouées sur votre compte TravelMate.</p>
                <p>Si ce n’était pas vous, nous vous conseillons de changer votre mot de passe immédiatement.</p>
            ");

        if ($photoFilename) {
            $fullPath = $this->kernel->getProjectDir() . '/public/uploads/security-alerts/' . $photoFilename;
            if (is_file($fullPath)) {
                $email->attachFromPath($fullPath, 'security-alert-photo.jpg');
            }
        }

        $this->mailer->send($email);
    }

    public function sendSuspiciousLoginAlert(User $user, string $currentIp, string $currentCountry): void
    {
        $email = (new Email())
            ->from('travelmate@example.com')
            ->to($user->getEmail())
            ->subject('Alerte sécurité - Connexion inhabituelle')
            ->html("
                <h2>Connexion inhabituelle détectée</h2>
                <p>Une nouvelle connexion a été détectée sur votre compte.</p>
                <p><strong>IP :</strong> {$currentIp}</p>
                <p><strong>Pays :</strong> {$currentCountry}</p>
                <p>Si ce n’était pas vous, changez votre mot de passe immédiatement.</p>
            ");

        $this->mailer->send($email);
    }
}
```

---

# 5) Modifier le login pour inclure la capture photo

Dans ton `templates/security/login.html.twig`, **dans le formulaire de login**, ajoute :

```twig
<input type="hidden" name="failed_capture" id="failed_capture">
```

Ajoute aussi ce bloc sous les champs :

```twig
<div id="security-camera-box" style="display:none; margin-top:1rem;">
    <p style="margin-bottom:.75rem; color: var(--color-text-muted);">
        Pour des raisons de sécurité, après plusieurs tentatives échouées, une capture caméra peut être jointe à l’alerte.
    </p>

    <div style="border-radius:20px; overflow:hidden; background:#111; aspect-ratio:4/3; margin-bottom:.75rem;">
        <video id="security-video" autoplay playsinline style="width:100%; height:100%; object-fit:cover;"></video>
    </div>

    <button type="button" id="capture-security-photo" class="btn btn--ghost">
        Capturer une photo sécurité
    </button>
</div>
```

Puis ajoute ce bloc JS :

```twig
{% block javascripts %}
    {{ parent() }}
    <script>
        document.addEventListener('DOMContentLoaded', async function () {
            const failedAttempts = {{ app.session.get('login_failed_attempts_for_last_user') ?? 0 }};
            const cameraBox = document.getElementById('security-camera-box');
            const video = document.getElementById('security-video');
            const captureBtn = document.getElementById('capture-security-photo');
            const hiddenInput = document.getElementById('failed_capture');

            let stream = null;

            async function startCamera() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user' },
                        audio: false
                    });
                    video.srcObject = stream;
                } catch (e) {
                    console.error('Camera access denied or unavailable', e);
                }
            }

            function capturePhoto() {
                if (!video.videoWidth || !video.videoHeight) {
                    return;
                }

                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                hiddenInput.value = canvas.toDataURL('image/jpeg', 0.9);
            }

            if (failedAttempts >= 2 && cameraBox) {
                cameraBox.style.display = 'block';
                await startCamera();
            }

            if (captureBtn) {
                captureBtn.addEventListener('click', capturePhoto);
            }
        });
    </script>
{% endblock %}
```

Ici :

* après **2 échecs**, on affiche la caméra
* au **prochain submit**, si l’utilisateur clique sur capturer, l’image est envoyée
* au **3e échec**, on l’attache à l’alerte email

---

# 6) Modifier `LoginFormAuthenticator`

Maintenant on ajoute :

* compteur d’échecs
* photo sécurité
* email d’alerte
* détection connexion suspecte
* recalcul du trust score

Remplace ou adapte :

## `src/Security/LoginFormAuthenticator.php`

Ajoute ces imports :

```php
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\GeoIpService;
use App\Service\SecurityAlertService;
use App\Service\UserTrustScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
```

Puis dans le constructeur, injecte :

```php
public function __construct(
    private UrlGeneratorInterface $urlGenerator,
    private EntityManagerInterface $entityManager,
    private UserRepository $userRepository,
    private SecurityAlertService $securityAlertService,
    private UserTrustScoreService $userTrustScoreService,
    private GeoIpService $geoIpService
) {
}
```

### Ajoute ou remplace `onAuthenticationFailure()`

```php
public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
{
    $email = mb_strtolower(trim((string) $request->request->get('email', '')));
    $failedCapture = (string) $request->request->get('failed_capture', '');

    if ($email !== '') {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user instanceof User) {
            $attempts = $user->getFailedLoginAttempts() + 1;
            $user->setFailedLoginAttempts($attempts);
            $user->setLastFailedLoginAt(new \DateTime());

            $photoFilename = null;

            if ($attempts >= 3) {
                $photoFilename = $this->securityAlertService->saveBase64Photo($failedCapture, 'failed-login');
                if ($photoFilename) {
                    $user->setSecurityAlertPhoto($photoFilename);
                }

                try {
                    $this->securityAlertService->sendFailedLoginAlert($user, $photoFilename);
                } catch (\Throwable $e) {
                    // optionally log
                }
            }

            $user->setTrustScore(
                $this->userTrustScoreService->calculate($user, false)
            );

            $this->entityManager->flush();

            $request->getSession()->set('login_failed_attempts_for_last_user', $attempts);
        }
    }

    return new RedirectResponse($this->urlGenerator->generate('app_login'));
}
```

### Ajoute ou adapte `onAuthenticationSuccess()`

```php
public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
{
    $user = $token->getUser();

    if ($user instanceof User) {
        $currentIp = $this->geoIpService->getClientIp($request) ?? $request->getClientIp() ?? '';
        $geo = $this->geoIpService->lookupIp($currentIp);

        $currentCountry = $geo['country_name'] ?? '';
        $currentCountryCode = $geo['country_code'] ?? '';

        $suspicious = false;

        if ($user->getLastLoginIp() && $currentIp && $user->getLastLoginIp() !== $currentIp) {
            $suspicious = true;
        }

        if (
            $user->getLastLoginCountryCode() &&
            $currentCountryCode &&
            $user->getLastLoginCountryCode() !== $currentCountryCode
        ) {
            $suspicious = true;
        }

        if ($suspicious) {
            $user->setSuspiciousLoginCount($user->getSuspiciousLoginCount() + 1);

            try {
                $this->securityAlertService->sendSuspiciousLoginAlert(
                    $user,
                    $currentIp,
                    $currentCountry ?: $currentCountryCode
                );
            } catch (\Throwable $e) {
            }
        }

        $user->setFailedLoginAttempts(0);

        if (method_exists($user, 'setLastLogin')) {
            $user->setLastLogin(new \DateTime());
        }

        if (method_exists($user, 'setLastLoginIp')) {
            $user->setLastLoginIp($currentIp ?: null);
        }

        if (method_exists($user, 'setLastLoginLocation')) {
            $user->setLastLoginLocation($currentCountry ?: null);
        }

        $user->setLastLoginCountryCode($currentCountryCode ?: null);

        $user->setTrustScore(
            $this->userTrustScoreService->calculate($user, $suspicious)
        );

        $this->entityManager->flush();

        $request->getSession()->remove('login_failed_attempts_for_last_user');
    }

    return new RedirectResponse($this->urlGenerator->generate('app_home'));
}
```

---

# 7) Afficher le score dans le profil

Dans ton template profile, ajoute un bloc :

```twig
<div class="profile-security-score">
    <h3>Niveau de confiance</h3>
    <div class="trust-score-badge trust-score-badge--{{ app.user.trustScore >= 80 ? 'high' : (app.user.trustScore >= 50 ? 'medium' : 'low') }}">
        Score : {{ app.user.trustScore }}/100
        —
        {{ app.user.trustScore >= 80 ? 'Élevé' : (app.user.trustScore >= 50 ? 'Moyen' : 'Faible') }}
    </div>
</div>
```

Ajoute le style :

```twig
<style>
    .trust-score-badge {
        display:inline-flex;
        align-items:center;
        gap:.5rem;
        padding:.7rem 1rem;
        border-radius:999px;
        font-weight:700;
    }

    .trust-score-badge--high {
        background: rgba(47,127,121,.12);
        color:#245f5a;
    }

    .trust-score-badge--medium {
        background: rgba(221,191,140,.18);
        color:#8a6a25;
    }

    .trust-score-badge--low {
        background: rgba(191,91,91,.12);
        color:#9e4747;
    }
</style>
```

---

# 8) Afficher le score dans l’admin users

Dans `templates/user_admin/_users_results.html.twig`, ajoute une colonne :

```twig
<th>Confiance</th>
```

Et dans les lignes :

```twig
<td>
    <span class="status-pill
        {{ user.trustScore >= 80 ? 'status-pill--success' : (user.trustScore >= 50 ? 'status-pill--warning' : 'status-pill--danger') }}">
        {{ user.trustScore }}/100
    </span>
</td>
```

Ajoute le style si besoin :

```twig
.status-pill--danger {
    background: rgba(191,91,91,.12);
    color: #9e4747;
}
```

---

# 9) Ce que ça couvre

Avec ça, tu obtiens une vraie feature forte :

### Sécurité

* compteur d’échecs de connexion
* alerte email après 3 échecs
* photo sécurité jointe si disponible

### Détection suspecte

* IP différente
* pays différent
* alerte email

### Score intelligent

* profil complet
* email vérifié
* visage enregistré
* photo/téléphone
* échecs de connexion
* connexions suspectes

---

# 10) Ordre exact d’implémentation

Fais-le dans cet ordre :

1. ajouter les colonnes SQL
2. mettre à jour l’entité `User`
3. créer `UserTrustScoreService`
4. créer `SecurityAlertService`
5. modifier `login.html.twig`
6. modifier `LoginFormAuthenticator`
7. afficher le score dans profile
8. afficher le score dans admin

---

# 11) Recommandation importante

Commence par faire marcher d’abord :

* compteur d’échecs
* email d’alerte
* score

Ensuite seulement :

* caméra sur login
* pièce jointe photo

Parce que la partie webcam dépend du navigateur et peut demander un peu plus de tests.

Mais le code que je t’ai donné est déjà structuré pour tout faire ensemble.

Si tu veux, le prochain message peut être la **version complète de ton `LoginFormAuthenticator.php`**, prête à copier-coller, adaptée à ton code actuel.

[1]: https://symfony.com/doc/current/security/custom_authenticator.html?utm_source=chatgpt.com "How to Write a Custom Authenticator"
