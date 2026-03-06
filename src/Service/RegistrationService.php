<?php

namespace App\Service;

use App\Contract\PasswordHasherServiceInterface;
use App\Contract\RegistrationServiceInterface;
use App\DTO\RegisterRequest;
use App\Entity\User;
use App\Message\UserRegistered;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private PasswordHasherServiceInterface $passwordHasherService,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function register(RegisterRequest $request): User
    {
        $existingUser = $this->userRepository->findByEmail($request->email);

        if ($existingUser !== null) {
            throw new BadRequestHttpException('A user with this email already exists.');
        }

        $user = new User();
        $user->setEmail($request->email);
        $user->setFirstName($request->firstName);
        $user->setLastName($request->lastName);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasherService->hash($user, $request->password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new UserRegistered(
                userId: (string) $user->getId(),
                email: $user->getEmail(),
                firstName: $user->getFirstName(),
                lastName: $user->getLastName(),
            )
        );

        return $user;
    }
}
