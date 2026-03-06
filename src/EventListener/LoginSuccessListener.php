<?php

namespace App\EventListener;

use App\Message\UserLoggedIn;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class LoginSuccessListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $this->messageBus->dispatch(
            new UserLoggedIn(
                userId: (string) $user->getId(),
                email: $user->getUserIdentifier(),
                ipAddress: $request?->getClientIp() ?? 'unknown',
                userAgent: $request?->headers->get('User-Agent', 'unknown') ?? 'unknown',
            )
        );
    }
}
