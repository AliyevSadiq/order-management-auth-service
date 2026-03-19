<?php

namespace App\Controller\Api;

use App\Contract\LoginServiceInterface;
use App\DTO\LoginRequest;
use App\Service\RequestValidator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authentication')]
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly LoginServiceInterface $loginService,
        private readonly RequestValidator $requestValidator,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Authenticate user and get JWT tokens',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: LoginRequest::class))
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1Q...'),
                    new OA\Property(property: 'refresh_token', type: 'string', example: 'dGhpcyBpcyBhIHJl...'),
                ])
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 422, description: 'Validation errors'),
        ]
    )]
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return $this->json(
                ['error' => 'Invalid JSON payload.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $loginRequest = new LoginRequest(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
        );

        $errorResponse = $this->requestValidator->validate($loginRequest);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $tokens = $this->loginService->login($loginRequest);

        return $this->json($tokens, Response::HTTP_OK);
    }
}
