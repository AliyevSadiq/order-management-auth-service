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
final class LogoutController extends AbstractController
{
    public function __construct(
        private readonly TokenServiceInterface $tokenService,
    ) {
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout user and invalidate refresh token',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'refresh_token', type: 'string', description: 'Optional refresh token to invalidate'),
            ])
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 204, description: 'Logged out successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? null;

        if ($refreshToken !== null) {
            $this->tokenService->logout($refreshToken);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
