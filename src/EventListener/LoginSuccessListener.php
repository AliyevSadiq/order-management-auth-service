<?php

namespace App\EventListener;

use App\Contract\JwtTokenServiceInterface;
use App\DTO\UserResponse;
use App\Entity\User;
use App\Message\UserLoggedIn;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class LoginSuccessListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
        private JwtTokenServiceInterface $jwtTokenService,
    ) {
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $refreshToken = $this->jwtTokenService->createRefreshToken($user);

        $data = $event->getData();
        $data['refresh_token'] = $refreshToken->getToken();
        $data['token_type'] = 'Bearer';
        $data['expires_in'] = 3600;
        $data['user'] = UserResponse::fromUser($user)->toArray();
        $event->setData($data);

        $this->messageBus->dispatch(
            new UserLoggedIn(
                userId: (string) $user->getId(),
                email: $user->getUserIdentifier(),
                ipAddress: $request?->getClientIp() ?? 'unknown',
                userAgent: $request?->headers->get('User-Agent', 'unknown') ?? 'unknown',
            ),
            [new AmqpStamp('auth.user_logged_in')]
        );
    }
}
