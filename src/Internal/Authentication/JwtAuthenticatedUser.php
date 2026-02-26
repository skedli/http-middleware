<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication;

use Skedli\HttpMiddleware\AuthenticatedUser;

final readonly class JwtAuthenticatedUser implements AuthenticatedUser
{
    private function __construct(
        private string $userId,
        private int $issuedAt,
        private int $expiresAt
    ) {
    }

    public static function from(string $userId, int $issuedAt, int $expiresAt): JwtAuthenticatedUser
    {
        return new JwtAuthenticatedUser(
            userId: $userId,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt
        );
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function issuedAt(): int
    {
        return $this->issuedAt;
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }
}
