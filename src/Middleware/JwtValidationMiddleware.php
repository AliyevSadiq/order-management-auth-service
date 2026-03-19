<?php

namespace App\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class JwtValidationMiddleware implements EventSubscriberInterface
{
    private const array PUBLIC_ROUTES = [
        '/api/v1/auth/register',
        '/api/v1/auth/login',
        '/api/v1/auth/refresh',
        '/api/doc',
    ];

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach (self::PUBLIC_ROUTES as $publicRoute) {
            if (str_starts_with($path, $publicRoute)) {
                return;
            }
        }

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $authHeader = $request->headers->get('Authorization');

        if ($authHeader === null || !str_starts_with($authHeader, 'Bearer ')) {
            $this->logger->warning('Missing or invalid Authorization header.', [
                'path' => $path,
                'method' => $request->getMethod(),
                'ip' => $request->getClientIp(),
            ]);

            $response = new JsonResponse(
                [
                    'error' => [
                        'code' => Response::HTTP_UNAUTHORIZED,
                        'message' => 'Missing or invalid Authorization header. Expected: Bearer <token>',
                    ],
                ],
                Response::HTTP_UNAUTHORIZED,
            );

            $event->setResponse($response);
            return;
        }

        $token = substr($authHeader, 7);

        if (empty($token)) {
            $this->logger->warning('Empty JWT token provided.', [
                'path' => $path,
                'ip' => $request->getClientIp(),
            ]);

            $response = new JsonResponse(
                [
                    'error' => [
                        'code' => Response::HTTP_UNAUTHORIZED,
                        'message' => 'JWT token is empty.',
                    ],
                ],
                Response::HTTP_UNAUTHORIZED,
            );

            $event->setResponse($response);
            return;
        }

        $this->logger->debug('JWT token present in request.', [
            'path' => $path,
            'method' => $request->getMethod(),
        ]);
    }
}
