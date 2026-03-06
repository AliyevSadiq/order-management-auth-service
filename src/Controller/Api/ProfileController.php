<?php

namespace App\Controller\Api;

use App\DTO\UserResponse;
use App\Entity\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Authentication')]
#[Route('/api/auth')]
final class ProfileController extends AbstractController
{
    #[OA\Get(
        path: '/api/auth/profile',
        summary: 'Get current user profile',
        security: [['Bearer' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'user', ref: new Model(type: UserResponse::class)),
                ])
            ),
            new OA\Response(response: 401, description: 'Unauthorized - invalid or missing JWT token'),
        ]
    )]
    #[Route('/profile', name: 'api_auth_profile', methods: ['GET'])]
    public function profile(#[CurrentUser] User $user): JsonResponse
    {
        $userResponse = UserResponse::fromUser($user);

        return $this->json(
            ['user' => $userResponse->toArray()],
            Response::HTTP_OK,
        );
    }
}
