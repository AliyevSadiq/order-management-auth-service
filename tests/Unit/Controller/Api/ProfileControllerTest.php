<?php

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\ProfileController;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ProfileControllerTest extends TestCase
{
    private ProfileController $controller;

    protected function setUp(): void
    {
        $this->controller = new ProfileController();

        $container = new \Symfony\Component\DependencyInjection\Container();
        $this->controller->setContainer($container);
    }

    public function testProfileReturnsUserData(): void
    {
        $user = $this->createUser();

        $response = $this->controller->profile($user);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        self::assertArrayHasKey('user', $content);
        self::assertSame('test@example.com', $content['user']['email']);
        self::assertSame('John', $content['user']['firstName']);
        self::assertSame('Doe', $content['user']['lastName']);
        self::assertContains('ROLE_USER', $content['user']['roles']);
    }

    public function testProfileReturnsCorrectStructure(): void
    {
        $user = $this->createUser();

        $response = $this->controller->profile($user);
        $content = json_decode($response->getContent(), true);

        self::assertArrayHasKey('id', $content['user']);
        self::assertArrayHasKey('email', $content['user']);
        self::assertArrayHasKey('firstName', $content['user']);
        self::assertArrayHasKey('lastName', $content['user']);
        self::assertArrayHasKey('roles', $content['user']);
        self::assertArrayHasKey('createdAt', $content['user']);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);

        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, Uuid::v4());

        return $user;
    }
}
