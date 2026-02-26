<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware\Internal\Authentication;

use Psr\Http\Message\ResponseInterface;
use TinyBlocks\Http\Response;

final readonly class UnauthorizedResponse
{
    private function __construct(private string $message)
    {
    }

    public static function because(string $message): UnauthorizedResponse
    {
        return new UnauthorizedResponse(message: $message);
    }

    public function toResponse(): ResponseInterface
    {
        return Response::unauthorized(body: [
            'code'    => 'TOKEN_VALIDATION_FAILED',
            'message' => $this->message
        ]);
    }
}
