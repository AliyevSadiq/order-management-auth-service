<?php

namespace App\Controller\Api;

use App\Contract\RegistrationServiceInterface;
use App\DTO\RegisterRequest;
use App\DTO\UserResponse;
use App\Service\RequestValidator;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authentication')]
#[Route('/api/auth')]
final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly RequestValidator $requestValidator,
    ) {
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterRequest::class))
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'User registered successfully.'),
                    new OA\Property(property: 'user', ref: new Model(type: UserResponse::class)),
                ])
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 422, description: 'Validation errors'),
        ]
    )]
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return $this->json(
                ['error' => 'Invalid JSON payload.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $registerRequest = new RegisterRequest(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            firstName: $data['firstName'] ?? '',
            lastName: $data['lastName'] ?? '',
        );

        $errorResponse = $this->requestValidator->validate($registerRequest);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $user = $this->registrationService->register($registerRequest);
        $userResponse = UserResponse::fromUser($user);

        return $this->json(
            [
                'message' => 'User registered successfully.',
                'user' => $userResponse->toArray(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
