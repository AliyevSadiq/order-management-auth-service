<?php

namespace App\Tests\Unit\Service;

use App\Service\RequestValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestValidatorTest extends TestCase
{
    private ValidatorInterface&MockObject $validator;
    private RequestValidator $requestValidator;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->requestValidator = new RequestValidator($this->validator);
    }

    public function testValidateReturnsNullWhenNoViolations(): void
    {
        $dto = new \stdClass();

        $this->validator->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        $result = $this->requestValidator->validate($dto);

        self::assertNull($result);
    }

    public function testValidateReturnsJsonResponseWithErrors(): void
    {
        $dto = new \stdClass();

        $violation1 = new ConstraintViolation(
            'Email is required.',
            null,
            [],
            $dto,
            'email',
            null,
        );
        $violation2 = new ConstraintViolation(
            'Password is required.',
            null,
            [],
            $dto,
            'password',
            null,
        );

        $this->validator->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList([$violation1, $violation2]));

        $result = $this->requestValidator->validate($dto);

        self::assertNotNull($result);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $result->getStatusCode());

        $content = json_decode($result->getContent(), true);
        self::assertSame('Email is required.', $content['errors']['email']);
        self::assertSame('Password is required.', $content['errors']['password']);
    }

    public function testValidateReturnsSingleError(): void
    {
        $dto = new \stdClass();

        $violation = new ConstraintViolation(
            'Please provide a valid email address.',
            null,
            [],
            $dto,
            'email',
            'invalid',
        );

        $this->validator->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList([$violation]));

        $result = $this->requestValidator->validate($dto);

        self::assertNotNull($result);

        $content = json_decode($result->getContent(), true);
        self::assertCount(1, $content['errors']);
        self::assertSame('Please provide a valid email address.', $content['errors']['email']);
    }
}
