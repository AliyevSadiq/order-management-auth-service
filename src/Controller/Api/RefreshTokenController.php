<?php

namespace App\Controller\Api;

use App\Contract\TokenServiceInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authentication')]
#[Route('/api/auth')]
final class RefreshTokenController extends AbstractController
{
    public function __construct(
        private readonly TokenServiceInterface $tokenService,
    ) {
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Refresh JWT token using refresh token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'refresh_token', type: 'string', example: 'dGhpcyBpcyBhIHJl...'),
            ])
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'refresh_token', type: 'string'),
                ])
            ),
            new OA\Response(response: 400, description: 'Refresh token is required'),
            new OA\Response(response: 401, description: 'Invalid refresh token'),
        ]
    )]
    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if ($data === null || !isset($data['refresh_token'])) {
            return $this->json(
                ['error' => 'Refresh token is required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $tokens = $this->tokenService->refreshToken($data['refresh_token']);

        return $this->json($tokens, Response::HTTP_OK);
    }
}
