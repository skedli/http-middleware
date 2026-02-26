<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Mocks;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\AuthenticatedUser;
use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\CorrelationId;

final class CapturingHandler implements RequestHandlerInterface
{
    private ?ServerRequestInterface $capturedRequest = null;

    public function __construct(private readonly ResponseInterface $response = new Response())
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->capturedRequest = $request;
        return $this->response;
    }

    public function capturedCorrelationId(): ?CorrelationId
    {
        $attribute = $this->capturedRequest?->getAttribute('correlationId');
        return $attribute instanceof CorrelationId ? $attribute : null;
    }

    public function capturedAuthenticatedUser(): ?AuthenticatedUser
    {
        $attribute = $this->capturedRequest?->getAttribute(AuthenticationMiddleware::AUTHENTICATED_USER_ATTRIBUTE);
        return $attribute instanceof AuthenticatedUser ? $attribute : null;
    }
}
