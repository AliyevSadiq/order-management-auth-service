<?php

namespace App\Controller;

use App\Service\HealthCheckService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Health')]
final class HealthController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
    ) {
    }

    #[OA\Get(
        path: '/health/auth',
        summary: 'Health check endpoint',
        description: 'Returns the health status of the auth service and its dependencies (database, redis, rabbitmq, jwt_keys)',
        tags: ['Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                        new OA\Property(
                            property: 'checks',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'database',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'status', type: 'string', example: 'up'),
                                        new OA\Property(property: 'response_time_ms', type: 'number', format: 'float', example: 1.23),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'redis',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'status', type: 'string', example: 'up'),
                                        new OA\Property(property: 'response_time_ms', type: 'number', format: 'float', example: 0.85),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'rabbitmq',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'status', type: 'string', example: 'up'),
                                        new OA\Property(property: 'response_time_ms', type: 'number', format: 'float', example: 2.10),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'jwt_keys',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'status', type: 'string', example: 'up'),
                                        new OA\Property(property: 'response_time_ms', type: 'number', format: 'float', example: 0.05),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'Service is unhealthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'unhealthy'),
                        new OA\Property(property: 'checks', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    #[Route('/health/auth', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = $this->healthCheckService->check();

        $isHealthy = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'up') {
                $isHealthy = false;
                break;
            }
        }

        return new JsonResponse(
            [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
            ],
            $isHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
