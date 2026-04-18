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
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $logger,
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
        $session->remove('login_failed_attempts_for_last_user');
        $session->remove('security_failed_login_photo_filename');
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $this->logger->info('LOGIN FAILURE DEBUG - entered onAuthenticationFailure');
        $session = $request->getSession();
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $session = $request->getSession();
        $photoFilenameFromSession = $session->get('security_failed_login_photo_filename');
        $this->logger->info('LOGIN FAILURE DEBUG - email received', [
            'email' => $email,
        ]);

        if ($email !== '') {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User) {
                $attempts = $user->getFailedLoginAttempts() + 1;
                $user->setFailedLoginAttempts($attempts);
                $user->setLastFailedLoginAt(new \DateTime());

                $this->logger->info('LOGIN FAILURE DEBUG - user lookup result', [
                    'user_found' => true,
                ]);

                $this->logger->info('LOGIN FAILURE DEBUG - attempts before save', [
                    'attempts' => $attempts,
                ]);

                $photoFilename = null;

                if ($attempts >= 3) {
                    $photoFilename = $photoFilenameFromSession;

                    if ($photoFilename) {
                        $user->setSecurityAlertPhoto($photoFilename);
                    }

                    $this->logger->info('LOGIN FAILURE DEBUG - photo filename from session', [
                        'photoFilename' => $photoFilename,
                    ]);

                    try {
                        $this->securityAlertService->sendFailedLoginAlert($user, $photoFilename);
                    } catch (\Throwable $e) {
                        $this->logger->error('LOGIN FAILURE DEBUG - failed to send alert email', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $this->logger->info('LOGIN FAILURE DEBUG - photo/email skipped because attempts < 3', [
                        'attempts' => $attempts,
                    ]);
                }

                $user->setTrustScore(
                    $this->userTrustScoreService->calculate($user, false)
                );

                $this->entityManager->flush();

                $session->set('login_failed_attempts_for_last_user', $attempts);
            } else {
                $this->logger->info('LOGIN FAILURE DEBUG - user lookup result', [
                    'user_found' => false,
                ]);

                $session->set('login_failed_attempts_for_last_user', 0);
            }
        }

        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        $this->logger->info('LOGIN FAILURE DEBUG - sending alert email', [
            'photoFilename' => $photoFilename,
        ]);
        $session->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}