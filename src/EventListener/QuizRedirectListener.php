<?php
// src/EventListener/QuizRedirectListener.php
namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Bundle\SecurityBundle\Security;

class QuizRedirectListener
{
    private array $protectedRoutes = [
        'app_activites',
        'app_categories',
        'app_activite_show',
        'app_categorie_show',
    ];

    public function __construct(
        private RouterInterface $router,
        private Security $security
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!in_array($route, $this->protectedRoutes)) {
            return;
        }

        if (!$this->security->getUser()) {
            return;
        }

        $session = $request->getSession();

        if (!$session->get('quiz_completed', false)) {
            $event->setResponse(
                new RedirectResponse($this->router->generate('app_quiz'))
            );
        }
    }
}