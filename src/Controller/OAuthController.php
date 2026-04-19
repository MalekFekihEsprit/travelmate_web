<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\LoginFormAuthenticator;

#[Route('/connect')]
class OAuthController extends AbstractController
{
    #[Route('/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google_main')
            ->redirect(['email', 'profile', 'openid'], []);
    }

    #[Route('/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        return $this->handleSocialLogin(
            'google_main',
            $request,
            $clientRegistry,
            $userRepository,
            $entityManager,
            $passwordHasher,
            $userAuthenticator,
            $loginFormAuthenticator
        );
    }

    #[Route('/github', name: 'connect_github_start')]
    public function connectGithub(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('github_main')
            ->redirect(['user:email'], []);
    }

    #[Route('/github/check', name: 'connect_github_check')]
    public function connectGithubCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        return $this->handleSocialLogin(
            'github_main',
            $request,
            $clientRegistry,
            $userRepository,
            $entityManager,
            $passwordHasher,
            $userAuthenticator,
            $loginFormAuthenticator
        );
    }

    #[Route('/facebook', name: 'connect_facebook_start')]
    public function connectFacebook(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('facebook_main')
            ->redirect(['email'], []);
    }

    #[Route('/facebook/check', name: 'connect_facebook_check')]
    public function connectFacebookCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        return $this->handleSocialLogin(
            'facebook_main',
            $request,
            $clientRegistry,
            $userRepository,
            $entityManager,
            $passwordHasher,
            $userAuthenticator,
            $loginFormAuthenticator
        );
    }

    #[Route('/microsoft', name: 'connect_microsoft_start')]
    public function connectMicrosoft(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('microsoft_main')
            ->redirect(['openid', 'profile', 'email'], []);
    }

    #[Route('/microsoft/check', name: 'connect_microsoft_check')]
    public function connectMicrosoftCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        try {
            $client = $clientRegistry->getClient('microsoft_main');
            $oauthUser = $client->fetchUser();
            
            // Debug: Affichez ce que Microsoft renvoie
            \dump($oauthUser->toArray()); 
            
            return $this->handleSocialLogin(
                'microsoft_main',
                $request,
                $clientRegistry,
                $userRepository,
                $entityManager,
                $passwordHasher,
                $userAuthenticator,
                $loginFormAuthenticator
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Microsoft: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }
    
    private function handleSocialLogin(
        string $clientName,
        Request $request,
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        try {
            $client = $clientRegistry->getClient($clientName);
            $oauthUser = $client->fetchUser();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Connexion sociale impossible : '.$e->getMessage());
            return $this->redirectToRoute('app_login');
        }

        $email = $this->extractEmail($oauthUser);

        if (!$email) {
            $this->addFlash('error', 'Impossible de récupérer un email depuis ce compte social.');
            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim($email));
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();

            $firstName = $this->extractFirstName($oauthUser);
            $lastName = $this->extractLastName($oauthUser);
            $fullName = $this->extractName($oauthUser);
            $avatar = $this->extractAvatar($oauthUser);

            if (!$firstName && !$lastName && $fullName) {
                $parts = preg_split('/\s+/', trim($fullName), 2);
                $firstName = $parts[0] ?? 'Utilisateur';
                $lastName = $parts[1] ?? 'Social';
            }

            $firstName = $firstName ?: 'Utilisateur';
            $lastName = $lastName ?: 'Social';

            $user->setEmail($email);
            $user->setNom($lastName);
            $user->setPrenom($firstName);
            $user->setRole('USER');
            $user->setIsVerified(true);
            $user->setVerificationCode(null);

            if (method_exists($user, 'setPhotoUrl') && $avatar) {
                $user->setPhotoUrl($avatar);
            }

            // because your DB requires date_naissance NOT NULL
            if (method_exists($user, 'setDateNaissance')) {
                $user->setDateNaissance(new \DateTime('2000-01-01'));
            } elseif (method_exists($user, 'setDate_naissance')) {
                $user->setDate_naissance(new \DateTime('2000-01-01'));
            }

            if (method_exists($user, 'setCreatedAt')) {
                $user->setCreatedAt(new \DateTime());
            } elseif (method_exists($user, 'setCreated_at')) {
                $user->setCreated_at(new \DateTime());
            }

            // required because mot_de_passe cannot be null in your DB
            $randomPassword = bin2hex(random_bytes(24));
            $hashedPassword = $passwordHasher->hashPassword($user, $randomPassword);
            $user->setMotDePasse($hashedPassword);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Compte créé avec succès via connexion sociale. Pensez à compléter votre profil.');
        }

        return $userAuthenticator->authenticateUser(
            $user,
            $loginFormAuthenticator,
            $request
        );
    }

    private function extractEmail(ResourceOwnerInterface $oauthUser): ?string
    {
        if (method_exists($oauthUser, 'getEmail')) {
            return $oauthUser->getEmail();
        }

        $data = $oauthUser->toArray();

        return $data['email']
            ?? $data['mail']
            ?? $data['userPrincipalName']
            ?? null;
    }

    private function extractFirstName(ResourceOwnerInterface $oauthUser): ?string
    {
        if (method_exists($oauthUser, 'getFirstName')) {
            return $oauthUser->getFirstName();
        }
        if (method_exists($oauthUser, 'getFirstname')) {
            return $oauthUser->getFirstname();
        }

        $data = $oauthUser->toArray();

        return $data['given_name']
            ?? $data['first_name']
            ?? null;
    }

    private function extractLastName(ResourceOwnerInterface $oauthUser): ?string
    {
        if (method_exists($oauthUser, 'getLastName')) {
            return $oauthUser->getLastName();
        }
        if (method_exists($oauthUser, 'getLastname')) {
            return $oauthUser->getLastname();
        }

        $data = $oauthUser->toArray();

        return $data['family_name']
            ?? $data['last_name']
            ?? null;
    }

    private function extractName(ResourceOwnerInterface $oauthUser): ?string
    {
        if (method_exists($oauthUser, 'getName')) {
            return $oauthUser->getName();
        }

        $data = $oauthUser->toArray();

        return $data['name'] ?? null;
    }

    private function extractAvatar(ResourceOwnerInterface $oauthUser): ?string
    {
        if (method_exists($oauthUser, 'getAvatar')) {
            return $oauthUser->getAvatar();
        }
        if (method_exists($oauthUser, 'getAvatarUrl')) {
            return $oauthUser->getAvatarUrl();
        }
        if (method_exists($oauthUser, 'getPictureUrl')) {
            return $oauthUser->getPictureUrl();
        }

        $data = $oauthUser->toArray();

        return $data['picture']
            ?? $data['avatar_url']
            ?? null;
    }
}