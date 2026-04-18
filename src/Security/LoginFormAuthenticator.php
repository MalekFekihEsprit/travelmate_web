<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GeoIpService;
use App\Service\SecurityAlertService;
use App\Service\UserTrustScoreService;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,        
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAlertService $securityAlertService,
        private UserTrustScoreService $userTrustScoreService,
        private GeoIpService $geoIpService
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        $request->getSession()->set('_last_username', $email);

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): User {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Aucun compte n’est associé à cette adresse email.');
                }

                $isVerified = method_exists($user, 'isVerified')
                    ? $user->isVerified()
                    : (method_exists($user, 'isVerified') ? $user->isVerified() : false);

                if (!$isVerified) {
                    throw new CustomUserMessageAuthenticationException(
                        'Votre compte n’est pas encore vérifié. Veuillez vérifier votre email avant de vous connecter.'
                    );
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $session = $request->getSession();

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

            $session->remove('login_failed_attempts_for_last_user');
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $failedCapture = (string) $request->request->get('failed_capture', '');

        if ($email !== '') {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User) {
                $attempts = $user->getFailedLoginAttempts() + 1;
                $user->setFailedLoginAttempts($attempts);
                $user->setLastFailedLoginAt(new \DateTime());

                $photoFilename = null;

                // Only try to save photo if attempts >= 3 AND we have capture data
                if ($attempts >= 3 && !empty($failedCapture)) {
                    $photoFilename = $this->securityAlertService->saveBase64Photo($failedCapture, 'failed-login');

                    if ($photoFilename) {
                        $user->setSecurityAlertPhoto($photoFilename);
                    }
                }

                // Always send alert when attempts >= 3 (with or without photo)
                if ($attempts >= 3) {
                    try {
                        // Pass the photo filename (will be null if no photo was saved)
                        $this->securityAlertService->sendFailedLoginAlert($user, $photoFilename);
                    } catch (\Throwable $e) {
                        // Log the error
                        error_log('Failed to send login alert: ' . $e->getMessage());
                    }
                }

                $user->setTrustScore(
                    $this->userTrustScoreService->calculate($user, false)
                );

                $this->entityManager->flush();

                $session->set('login_failed_attempts_for_last_user', $attempts);
            } else {
                $session->set('login_failed_attempts_for_last_user', 0);
            }
        }
        // In onAuthenticationFailure, add:
        error_log('Failed capture data length: ' . strlen($failedCapture));
        error_log('Failed capture empty? ' . (empty($failedCapture) ? 'YES' : 'NO'));
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        $session->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}