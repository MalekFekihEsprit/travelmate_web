Parfait. On va faire une version **propre, plus fiable et plus agréable** du flow visage :

* **signup** : l’utilisateur peut ajouter son visage s’il veut
* **login** : il peut se connecter soit par email/mot de passe, soit par visage
* **profile** : il peut ajouter / mettre à jour / supprimer son visage

L’idée est :

* garder ton microservice Python, mais le rendre **plus robuste**
* faire une intégration **Symfony 6.4 propre**
* faire un **UI plus joli** côté login visage
* stocker l’embedding dans `face_embedding` en JSON texte

FastAPI gère très bien les fichiers envoyés en `UploadFile` et les formulaires/fichiers multipart, et Symfony a un `HttpClient` prévu pour consommer des APIs proprement. DeepFace prend en charge la chaîne classique détection → alignement → représentation → vérification, et RetinaFace est une option de détection réputée plus robuste que des backends plus simples. ([FastAPI][1])

---

# 1) Plan de mise en place

Fais ça dans cet ordre :

1. améliorer le microservice Python visage
2. ajouter l’appel API côté Symfony
3. ajouter le visage au signup
4. ajouter une vraie page “login par visage”
5. ajouter une page “gérer mon visage” depuis le profil
6. tester le flow complet

---

# 2) Commandes à exécuter

## Côté Symfony

```bash
composer require symfony/http-client symfony/mime
php bin/console make:controller FaceAuthController
php bin/console make:controller ProfileFaceController
```

Crée aussi ces formulaires :

```bash
php bin/console make:form FaceEnrollmentFormType
```

Ensuite, remplace les fichiers générés par ceux ci-dessous.

---

# 3) Configuration `.env.local`

Ajoute :

```env
FACE_API_BASE_URL=http://127.0.0.1:8000
FACE_MATCH_THRESHOLD=0.68
```

---

# 4) Configuration `services.yaml`

Ajoute dans `config/services.yaml` :

```yaml
parameters:
    face_api_base_url: '%env(FACE_API_BASE_URL)%'
    face_match_threshold: '%env(float:FACE_MATCH_THRESHOLD)%'

services:
    App\Service\FaceRecognitionClient:
        arguments:
            $faceApiBaseUrl: '%face_api_base_url%'
```

---

# 5) Microservice Python amélioré

Crée ou remplace ton fichier Python par ceci.

## `face_service.py`

```python
from fastapi import FastAPI, File, UploadFile, HTTPException, Request
from fastapi.responses import JSONResponse
import numpy as np
import cv2
from deepface import DeepFace
import logging
import os

logging.basicConfig(level=logging.INFO)
app = FastAPI(title="TravelMate Face Recognition Service")

MODEL_NAME = os.getenv("FACE_MODEL_NAME", "ArcFace")
PRIMARY_DETECTOR = os.getenv("FACE_DETECTOR", "retinaface")
FALLBACK_DETECTORS = ["retinaface", "mtcnn", "opencv"]

logging.info(f"Chargement du modèle {MODEL_NAME}...")
DeepFace.build_model(MODEL_NAME)
logging.info("Modèle chargé avec succès")


def decode_image(contents: bytes):
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="Image invalide")
    img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    return img_rgb


def resize_if_needed(img_rgb):
    h, w = img_rgb.shape[:2]
    if h > 1200 or w > 1200:
        scale = 1200 / max(h, w)
        img_rgb = cv2.resize(img_rgb, (int(w * scale), int(h * scale)))
    return img_rgb


def validate_quality(img_rgb):
    gray = cv2.cvtColor(img_rgb, cv2.COLOR_RGB2GRAY)
    brightness = float(np.mean(gray))
    sharpness = float(cv2.Laplacian(gray, cv2.CV_64F).var())

    if brightness < 35:
        raise HTTPException(status_code=400, detail="Image trop sombre")
    if sharpness < 35:
        raise HTTPException(status_code=400, detail="Image trop floue")


def extract_embedding_with_fallback(img_rgb):
    last_error = None

    for detector in [PRIMARY_DETECTOR] + [d for d in FALLBACK_DETECTORS if d != PRIMARY_DETECTOR]:
        try:
            representations = DeepFace.represent(
                img_path=img_rgb,
                model_name=MODEL_NAME,
                detector_backend=detector,
                enforce_detection=True,
                align=True
            )

            if not representations or len(representations) != 1:
                raise HTTPException(status_code=400, detail="Veuillez fournir une image avec un seul visage")

            embedding = representations[0]["embedding"]
            return embedding, detector

        except Exception as e:
            last_error = e
            logging.warning(f"Échec avec detector {detector}: {str(e)}")

    raise HTTPException(status_code=500, detail=f"Impossible d'extraire le visage: {str(last_error)}")


@app.get("/health")
async def health():
    return {"status": "ok", "model": MODEL_NAME, "detector": PRIMARY_DETECTOR}


@app.post("/extract")
async def extract_embedding(file: UploadFile = File(...)):
    try:
        contents = await file.read()
        img_rgb = decode_image(contents)
        img_rgb = resize_if_needed(img_rgb)
        validate_quality(img_rgb)

        embedding, detector_used = extract_embedding_with_fallback(img_rgb)

        return JSONResponse({
            "success": True,
            "embedding": embedding,
            "model": MODEL_NAME,
            "detector": detector_used
        })

    except HTTPException:
        raise
    except Exception as e:
        logging.exception("Erreur dans /extract")
        raise HTTPException(status_code=500, detail=f"Erreur extraction: {str(e)}")


@app.post("/compare")
async def compare_embeddings(request: Request):
    try:
        body = await request.json()

        embedding1 = body.get("embedding1")
        embedding2 = body.get("embedding2")
        threshold = float(body.get("threshold", 0.68))

        if embedding1 is None or embedding2 is None:
            raise HTTPException(status_code=400, detail="embedding1 et embedding2 sont requis")

        v1 = np.array(embedding1, dtype=np.float32)
        v2 = np.array(embedding2, dtype=np.float32)

        if np.linalg.norm(v1) == 0 or np.linalg.norm(v2) == 0:
            raise HTTPException(status_code=400, detail="Embedding invalide")

        v1 = v1 / np.linalg.norm(v1)
        v2 = v2 / np.linalg.norm(v2)

        similarity = float(np.dot(v1, v2))

        return JSONResponse({
            "success": True,
            "similarity": similarity,
            "threshold": threshold,
            "is_match": similarity >= threshold
        })

    except HTTPException:
        raise
    except Exception as e:
        logging.exception("Erreur dans /compare")
        raise HTTPException(status_code=500, detail=f"Erreur comparaison: {str(e)}")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("face_service:app", host="127.0.0.1", port=8000, reload=True)
```

## Dépendances Python

```bash
pip install fastapi uvicorn python-multipart numpy opencv-python deepface
```

Puis lance :

```bash
python face_service.py
```

Teste :

```text
http://127.0.0.1:8000/health
```

---

# 6) Service Symfony pour parler au microservice

Crée :

## `src/Service/FaceRecognitionClient.php`

```php
<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceRecognitionClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $faceApiBaseUrl
    ) {
    }

    public function extractEmbeddingFromUploadedFile(UploadedFile $file): array
    {
        $formData = new FormDataPart([
            'file' => DataPart::fromPath(
                $file->getPathname(),
                $file->getClientOriginalName(),
                $file->getMimeType() ?: 'image/jpeg'
            ),
        ]);

        $response = $this->httpClient->request('POST', rtrim($this->faceApiBaseUrl, '/').'/extract', [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
        ]);

        $data = $response->toArray(false);

        if (($data['success'] ?? false) !== true || !isset($data['embedding']) || !is_array($data['embedding'])) {
            $message = $data['detail'] ?? 'Erreur inconnue lors de l’extraction du visage.';
            throw new \RuntimeException($message);
        }

        return $data['embedding'];
    }

    public function compareEmbeddings(array $embedding1, array $embedding2, float $threshold = 0.68): array
    {
        $response = $this->httpClient->request('POST', rtrim($this->faceApiBaseUrl, '/').'/compare', [
            'json' => [
                'embedding1' => $embedding1,
                'embedding2' => $embedding2,
                'threshold' => $threshold,
            ],
        ]);

        $data = $response->toArray(false);

        if (($data['success'] ?? false) !== true && !isset($data['similarity'])) {
            $message = $data['detail'] ?? 'Erreur inconnue lors de la comparaison faciale.';
            throw new \RuntimeException($message);
        }

        return [
            'similarity' => (float) ($data['similarity'] ?? 0),
            'is_match' => (bool) ($data['is_match'] ?? false),
            'threshold' => (float) ($data['threshold'] ?? $threshold),
        ];
    }

    public function decodeStoredEmbedding(?string $json): ?array
    {
        if (!$json) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function encodeEmbedding(array $embedding): string
    {
        return json_encode($embedding, JSON_THROW_ON_ERROR);
    }
}
```

---

# 7) Repository : récupérer les users avec visage

Ajoute dans :

## `src/Repository/UserRepository.php`

```php
public function findVerifiedUsersWithFaceEmbedding(): array
{
    return $this->createQueryBuilder('u')
        ->andWhere('u.faceEmbedding IS NOT NULL')
        ->andWhere('u.faceEmbedding <> :empty')
        ->andWhere('u.isVerified = :verified')
        ->setParameter('empty', '')
        ->setParameter('verified', true)
        ->orderBy('u.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

---

# 8) Ajouter le champ visage au signup

Remplace ton formulaire d’inscription par cette version.

## `src/Form/RegistrationFormType.php`

```php
<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Ex: Fekih'],
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.'), new Length(max: 255)],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['placeholder' => 'Ex: Malek'],
                'constraints' => [new NotBlank(message: 'Le prénom est obligatoire.'), new Length(max: 255)],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [new NotBlank(message: 'La date de naissance est obligatoire.')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => ['placeholder' => 'Ex: malek@email.com'],
                'constraints' => [new NotBlank(message: 'L’email est obligatoire.'), new Length(max: 255)],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: +216 12 345 678'],
                'constraints' => [new Length(max: 255)],
            ])
            ->add('photoUrl', TextType::class, [
                'label' => 'URL de la photo de profil',
                'required' => false,
                'attr' => ['placeholder' => 'https://...'],
                'constraints' => [new Length(max: 255), new Url(message: 'Veuillez saisir une URL valide.')],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Ou importer une photo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(maxSize: '4M', mimeTypesMessage: 'Veuillez envoyer une image valide.'),
                ],
            ])
            ->add('faceImage', FileType::class, [
                'label' => 'Ajouter votre visage (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/*',
                    'capture' => 'user',
                ],
                'constraints' => [
                    new Image(maxSize: '5M', mimeTypesMessage: 'Veuillez envoyer une image valide du visage.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['placeholder' => 'Minimum 8 caractères'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['placeholder' => 'Retapez votre mot de passe'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', max: 255),
                    new Regex(pattern: '/^(?=.*[A-Za-z])(?=.*\d).+$/', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
```

---

# 9) Mise à jour du contrôleur d’inscription

Dans ton `RegistrationController`, injecte le service visage dans `register()` et remplace le cœur de la méthode par cette version.

## Dans `src/Controller/RegistrationController.php`, méthode `register(...)`

Ajoute `FaceRecognitionClient $faceRecognitionClient` aux arguments, puis utilise ceci à l’intérieur :

```php
if ($form->isSubmitted() && $form->isValid()) {
    $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));
    $user->setRole('USER');
    $user->setCreatedAt(new \DateTime());
    $user->setIsVerified(false);

    $verificationCode = $this->generateVerificationCode();
    $user->setVerificationCode($verificationCode);

    $hashedPassword = $passwordHasher->hashPassword(
        $user,
        (string) $form->get('plainPassword')->getData()
    );
    $user->setMotDePasse($hashedPassword);

    $photoFile = $form->get('photoFile')->getData();

    if ($photoFile) {
        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

        try {
            $photoFile->move(
                $this->getParameter('kernel.project_dir').'/public/uploads/profiles',
                $newFilename
            );
            $user->setPhotoFileName($newFilename);

            if (method_exists($user, 'setPhotoUrl')) {
                $user->setPhotoUrl(null);
            }
        } catch (FileException $e) {
            $this->addFlash('error', 'La photo n’a pas pu être importée.');
            return $this->render('registration/register.html.twig', [
                'registrationForm' => $form,
            ]);
        }
    }

    $faceImage = $form->get('faceImage')->getData();

    if ($faceImage) {
        try {
            $embedding = $faceRecognitionClient->extractEmbeddingFromUploadedFile($faceImage);
            $user->setFaceEmbedding($faceRecognitionClient->encodeEmbedding($embedding));
        } catch (\Throwable $e) {
            $form->get('faceImage')->addError(new FormError('Impossible d’enregistrer ce visage : '.$e->getMessage()));

            return $this->render('registration/register.html.twig', [
                'registrationForm' => $form,
            ]);
        }
    }

    $entityManager->persist($user);
    $entityManager->flush();

    try {
        $this->sendVerificationEmail($mailer, $user, $verificationCode);
        $this->addFlash('success', 'Un code de vérification a été envoyé à votre adresse email.');
    } catch (TransportExceptionInterface $e) {
        $this->addFlash('warning', 'Compte créé, mais l’email n’a pas pu être envoyé. Vérifiez votre configuration mailer.');
    }

    return $this->redirectToRoute('app_verify_email', ['id' => $user->getId()]);
}
```

N’oublie pas aussi d’ajouter l’import en haut :

```php
use App\Service\FaceRecognitionClient;
use Symfony\Component\Form\FormError;
```

---

# 10) Ajouter le champ visage dans le template signup

Dans `templates/registration/register.html.twig`, ajoute ce bloc **après** `photoFile` :

```twig
<div class="form-control-block full">
    {{ form_label(registrationForm.faceImage) }}
    {{ form_widget(registrationForm.faceImage) }}
    <div class="form-help">
        Optionnel. Vous pouvez ajouter une photo de votre visage pour activer la connexion faciale.
    </div>
    <div class="form-error">{{ form_errors(registrationForm.faceImage) }}</div>
</div>
```

---

# 11) Contrôleur login par visage

Crée :

## `src/Controller/FaceAuthController.php`

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use App\Service\FaceRecognitionClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\UserAuthenticatorInterface;

class FaceAuthController extends AbstractController
{
    #[Route('/login/face', name: 'app_face_login', methods: ['GET', 'POST'])]
    public function loginWithFace(
        Request $request,
        UserRepository $userRepository,
        FaceRecognitionClient $faceRecognitionClient,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $faceImage */
            $faceImage = $request->files->get('faceImage');

            if (!$faceImage) {
                $this->addFlash('error', 'Veuillez capturer ou importer une image du visage.');
                return $this->redirectToRoute('app_face_login');
            }

            try {
                $probeEmbedding = $faceRecognitionClient->extractEmbeddingFromUploadedFile($faceImage);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de lire le visage : '.$e->getMessage());
                return $this->redirectToRoute('app_face_login');
            }

            $users = $userRepository->findVerifiedUsersWithFaceEmbedding();
            $bestUser = null;
            $bestScore = 0.0;
            $threshold = (float) $this->getParameter('face_match_threshold');

            foreach ($users as $user) {
                $stored = $faceRecognitionClient->decodeStoredEmbedding($user->getFaceEmbedding());

                if (!$stored) {
                    continue;
                }

                try {
                    $comparison = $faceRecognitionClient->compareEmbeddings($probeEmbedding, $stored, $threshold);

                    if ($comparison['similarity'] > $bestScore) {
                        $bestScore = $comparison['similarity'];
                        $bestUser = $user;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if (!$bestUser || $bestScore < $threshold) {
                $this->addFlash('error', 'Aucun visage correspondant n’a été reconnu.');
                return $this->redirectToRoute('app_face_login');
            }

            return $userAuthenticator->authenticateUser(
                $bestUser,
                $loginFormAuthenticator,
                $request
            );
        }

        return $this->render('security/face_login.html.twig');
    }
}
```

---

# 12) Template joli pour login par visage

Crée :

## `templates/security/face_login.html.twig`

```twig
{% extends 'base.html.twig' %}

{% block title %}Connexion par visage | TravelMate{% endblock %}

{% block stylesheets %}
    {{ parent() }}
    <style>
        .face-shell { max-width: 1100px; margin: 0 auto; padding: 2rem 0 3rem; }
        .face-grid { display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; }
        .face-panel, .face-card {
            border: 1px solid var(--color-border);
            border-radius: 30px;
            background: rgba(255,253,249,.94);
            padding: 1.8rem;
        }
        .face-panel {
            background: linear-gradient(135deg, rgba(255,253,249,.96), rgba(243,234,223,.92));
        }
        .face-title { font-family:'Fraunces', serif; font-size:2rem; margin-bottom:.6rem; }
        .face-text { color: var(--color-text-muted); margin-bottom: 1rem; }
        .flash-stack { display:grid; gap:.75rem; margin-bottom:1rem; }
        .flash-message {
            padding:.95rem 1rem; border-radius:16px; border:1px solid transparent;
        }
        .flash-message--error { background:rgba(191,91,91,.10); border-color:rgba(191,91,91,.16); color:#9e4747; }
        .camera-wrap {
            border:1px solid var(--color-border);
            border-radius:24px;
            overflow:hidden;
            background:#111;
            position: relative;
            aspect-ratio: 4 / 3;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        video, canvas, .captured-preview {
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }
        canvas { display:none; }
        .captured-preview { display:none; }
        .face-actions {
            display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem;
        }
        .btn-face {
            min-height:48px; padding:0 1rem; border-radius:999px; border:0; cursor:pointer; font-weight:700;
        }
        .btn-face--primary { background: var(--color-primary); color:#fff; }
        .btn-face--ghost { background: transparent; color: var(--color-primary); border:1px solid rgba(196,111,75,.22); }
        .file-input-wrap { margin-top:1rem; }
        .face-submit {
            width:100%; min-height:52px; margin-top:1.25rem;
            border:0; border-radius:999px; background:var(--color-primary); color:#fff; font-weight:700; cursor:pointer;
        }
        @media (max-width: 900px) {
            .face-grid { grid-template-columns: 1fr; }
        }
    </style>
{% endblock %}

{% block body %}
<section class="face-shell">
    <div class="face-grid">
        <div class="face-panel">
            <h1 class="face-title">Connexion par visage</h1>
            <p class="face-text">
                Placez votre visage devant la caméra ou importez une photo nette de votre visage.
                Si un visage enregistré correspond, vous serez connecté automatiquement.
            </p>

            <ul style="color:var(--color-text-muted); padding-left:1.1rem; line-height:1.8;">
                <li>Utilisez une image nette, bien éclairée</li>
                <li>Gardez un seul visage dans le cadre</li>
                <li>Vous pouvez aussi continuer avec email et mot de passe</li>
            </ul>

            <div style="margin-top:1.2rem;">
                <a href="{{ path('app_login') }}" class="btn-face btn-face--ghost" style="display:inline-flex; align-items:center; text-decoration:none;">
                    Retour à la connexion classique
                </a>
            </div>
        </div>

        <div class="face-card">
            <div class="flash-stack">
                {% for message in app.flashes('error') %}
                    <div class="flash-message flash-message--error">{{ message }}</div>
                {% endfor %}
            </div>

            <form method="post" enctype="multipart/form-data" id="face-login-form">
                <div class="camera-wrap">
                    <video id="video" autoplay playsinline></video>
                    <canvas id="canvas"></canvas>
                    <img id="capturedPreview" class="captured-preview" alt="Capture visage">
                </div>

                <div class="face-actions">
                    <button type="button" class="btn-face btn-face--ghost" id="startCameraBtn">Activer la caméra</button>
                    <button type="button" class="btn-face btn-face--ghost" id="captureBtn">Capturer</button>
                    <button type="button" class="btn-face btn-face--ghost" id="resetBtn">Réinitialiser</button>
                </div>

                <div class="file-input-wrap">
                    <label for="faceImage" style="display:block; margin-bottom:.5rem; font-weight:600;">Ou importer une image</label>
                    <input type="file" name="faceImage" id="faceImage" accept="image/*" capture="user">
                </div>

                <button type="submit" class="face-submit">Se connecter avec le visage</button>
            </form>
        </div>
    </div>
</section>
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const capturedPreview = document.getElementById('capturedPreview');
            const faceInput = document.getElementById('faceImage');
            const startCameraBtn = document.getElementById('startCameraBtn');
            const captureBtn = document.getElementById('captureBtn');
            const resetBtn = document.getElementById('resetBtn');

            let stream = null;

            async function startCamera() {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                    video.srcObject = stream;
                    video.style.display = 'block';
                    capturedPreview.style.display = 'none';
                } catch (e) {
                    alert('Impossible d’accéder à la caméra.');
                }
            }

            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }
            }

            startCameraBtn.addEventListener('click', startCamera);

            captureBtn.addEventListener('click', () => {
                if (!video.videoWidth || !video.videoHeight) {
                    alert('Activez la caméra avant de capturer.');
                    return;
                }

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                canvas.toBlob(blob => {
                    if (!blob) {
                        return;
                    }

                    const file = new File([blob], 'face-capture.jpg', { type: 'image/jpeg' });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    faceInput.files = dataTransfer.files;

                    capturedPreview.src = URL.createObjectURL(blob);
                    capturedPreview.style.display = 'block';
                    video.style.display = 'none';

                    stopCamera();
                }, 'image/jpeg', 0.95);
            });

            resetBtn.addEventListener('click', () => {
                faceInput.value = '';
                capturedPreview.src = '';
                capturedPreview.style.display = 'none';
                video.style.display = 'block';
            });

            faceInput.addEventListener('change', () => {
                if (faceInput.files && faceInput.files[0]) {
                    const file = faceInput.files[0];
                    capturedPreview.src = URL.createObjectURL(file);
                    capturedPreview.style.display = 'block';
                    video.style.display = 'none';
                    stopCamera();
                }
            });
        });
    </script>
{% endblock %}
```

---

# 13) Ajouter le lien sur la page login classique

Dans `templates/security/login.html.twig`, ajoute un bouton ou lien :

```twig
<p class="login-bottom" style="margin-top:1rem;">
    <a href="{{ path('app_face_login') }}">Continuer avec le visage</a>
</p>
```

---

# 14) Formulaire pour gérer le visage depuis le profil

Crée :

## `src/Form/FaceEnrollmentFormType.php`

```php
<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FaceEnrollmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('faceImage', FileType::class, [
            'label' => 'Photo de votre visage',
            'mapped' => false,
            'required' => true,
            'attr' => [
                'accept' => 'image/*',
                'capture' => 'user',
            ],
            'constraints' => [
                new Image(
                    maxSize: '5M',
                    mimeTypesMessage: 'Veuillez envoyer une image valide du visage.'
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
```

---

# 15) Contrôleur pour gérer le visage depuis le profil

Crée :

## `src/Controller/ProfileFaceController.php`

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\FaceEnrollmentFormType;
use App\Service\FaceRecognitionClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileFaceController extends AbstractController
{
    #[Route('/profile/face', name: 'app_profile_face', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FaceRecognitionClient $faceRecognitionClient,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(FaceEnrollmentFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $faceImage = $form->get('faceImage')->getData();

            try {
                $embedding = $faceRecognitionClient->extractEmbeddingFromUploadedFile($faceImage);
                $user->setFaceEmbedding($faceRecognitionClient->encodeEmbedding($embedding));
                $entityManager->flush();

                $this->addFlash('success', 'Votre visage a été enregistré avec succès.');
                return $this->redirectToRoute('app_profile_face');
            } catch (\Throwable $e) {
                $form->get('faceImage')->addError(new FormError('Impossible d’enregistrer le visage : '.$e->getMessage()));
            }
        }

        return $this->render('profile/face.html.twig', [
            'form' => $form,
            'hasFace' => !empty($user->getFaceEmbedding()),
        ]);
    }

    #[Route('/profile/face/remove', name: 'app_profile_face_remove', methods: ['POST'])]
    public function remove(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $user->setFaceEmbedding(null);
        $entityManager->flush();

        $this->addFlash('success', 'Le visage enregistré a été supprimé.');
        return $this->redirectToRoute('app_profile_face');
    }
}
```

---

# 16) Template de gestion du visage

Crée :

## `templates/profile/face.html.twig`

```twig
{% extends 'base.html.twig' %}

{% block title %}Mon visage | TravelMate{% endblock %}

{% block stylesheets %}
    {{ parent() }}
    <style>
        .face-profile-shell { max-width: 860px; margin: 0 auto; padding: 2rem 0 3rem; }
        .face-profile-card {
            border: 1px solid var(--color-border);
            border-radius: 30px;
            background: rgba(255,253,249,.94);
            padding: 2rem;
        }
        .face-profile-title { font-family:'Fraunces', serif; font-size:2rem; margin-bottom:.4rem; }
        .face-profile-text { color: var(--color-text-muted); margin-bottom:1.2rem; }
        .status-box {
            margin-bottom: 1rem;
            padding: .9rem 1rem;
            border-radius: 16px;
            background: rgba(47,127,121,.10);
            color: #245f5a;
        }
        .flash-stack { display:grid; gap:.75rem; margin-bottom:1rem; }
        .flash-message { padding:.95rem 1rem; border-radius:16px; border:1px solid transparent; }
        .flash-message--success { background:rgba(47,127,121,.10); border-color:rgba(47,127,121,.16); color:#245f5a; }
        .form-control-block { display:grid; gap:.45rem; margin-bottom:1rem; }
        .form-control-block input { min-height:52px; padding:.9rem 1rem; border-radius:16px; border:1px solid var(--color-border); background:#fffdfa; }
        .form-error { color:#b84d4d; font-size:.84rem; }
        .btn-row { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem; }
        .btn-primary, .btn-ghost, .btn-danger {
            min-height:48px; padding:0 1rem; border-radius:999px; font-weight:700; cursor:pointer; border:0;
        }
        .btn-primary { background:var(--color-primary); color:#fff; }
        .btn-ghost { background:transparent; color:var(--color-primary); border:1px solid rgba(196,111,75,.22); }
        .btn-danger { background:#bf5b5b; color:#fff; }
    </style>
{% endblock %}

{% block body %}
<section class="face-profile-shell">
    <div class="face-profile-card">
        <h1 class="face-profile-title">Gérer mon visage</h1>
        <p class="face-profile-text">
            Ajoutez ou mettez à jour votre visage pour pouvoir utiliser la connexion faciale.
        </p>

        <div class="status-box">
            Statut actuel :
            <strong>{{ hasFace ? 'visage enregistré' : 'aucun visage enregistré' }}</strong>
        </div>

        <div class="flash-stack">
            {% for message in app.flashes('success') %}
                <div class="flash-message flash-message--success">{{ message }}</div>
            {% endfor %}
        </div>

        {{ form_start(form, {'attr': {'novalidate': 'novalidate'}}) }}
            <div class="form-control-block">
                {{ form_label(form.faceImage) }}
                {{ form_widget(form.faceImage) }}
                <div class="form-error">{{ form_errors(form.faceImage) }}</div>
            </div>

            <div class="btn-row">
                <button type="submit" class="btn-primary">
                    {{ hasFace ? 'Mettre à jour mon visage' : 'Enregistrer mon visage' }}
                </button>

                <a href="{{ path('app_profile') }}" class="btn-ghost" style="text-decoration:none; display:inline-flex; align-items:center;">
                    Retour au profil
                </a>
            </div>
        {{ form_end(form) }}

        {% if hasFace %}
            <form method="post" action="{{ path('app_profile_face_remove') }}" style="margin-top:1rem;">
                <button type="submit" class="btn-danger">Supprimer le visage enregistré</button>
            </form>
        {% endif %}
    </div>
</section>
{% endblock %}
```

---

# 17) Ajouter le lien depuis le profil

Dans `templates/profile/index.html.twig`, ajoute un lien dans ta section sécurité, par exemple :

```twig
<div style="margin-top: 1rem;">
    <a href="{{ path('app_profile_face') }}" class="profile-submit profile-submit--ghost" style="text-decoration:none; display:inline-flex; align-items:center;">
        Gérer mon visage
    </a>
</div>
```

---

# 18) Vérifie l’entité `User`

Tu dois avoir :

```php
getFaceEmbedding()
setFaceEmbedding(?string $faceEmbedding)
```

Si le nom généré diffère, adapte juste les appels.

---

# 19) Résultat final

Avec ça :

* **signup** : visage optionnel
* **login classique** : inchangé
* **login visage** : nouvelle page webcam / upload
* **profile** : ajout / mise à jour / suppression du visage

---

# 20) Ce qui améliore vraiment la reconnaissance

Pour améliorer ce que tu avais en Java :

* on passe sur un service backend mieux structuré
* on force une image nette et un seul visage
* on essaye plusieurs détecteurs si besoin
* on stocke des embeddings normalisés comparés proprement
* on rend le seuil configurable dans `.env.local`

Le point le plus important côté pratique sera de **tester ton seuil** sur plusieurs photos de toi. Commence à `0.68`, puis essaye `0.65` ou `0.70` selon les résultats.

---

# 21) Vérification rapide

Quand tout est branché :

1. lance le microservice Python
2. lance Symfony
3. teste `/register` avec visage optionnel
4. teste `/login/face`
5. teste `/profile/face`

---

# 22) Prochaine étape logique

Après ça, la suite parfaite serait :

* ajouter **détection de connexion suspecte**
* ou ajouter **Google login**
* ou ajouter **VichUploaderBundle** pour rendre la photo de profil plus propre

Si tu veux, je peux maintenant te donner la **version corrigée exacte de ton `login.html.twig` et de ton `RegistrationController.php` complets**, sans extrait, pour éviter tout mélange.

[1]: https://fastapi.tiangolo.com/reference/uploadfile/?utm_source=chatgpt.com "UploadFile class"


# Cannot resolve argument $userAuthenticator of "App\Controller\FaceAuthController::loginWithFace()": Cannot determine controller argument for "App\Controller\FaceAuthController::loginWithFace()": the $userAuthenticator argument is type-hinted with the non-existent class or interface: "Symfony\Component\Security\Http\UserAuthenticatorInterface".