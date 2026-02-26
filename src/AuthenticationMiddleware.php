<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\Authentication\AuthenticationMiddlewareBuilder;
use Skedli\HttpMiddleware\Internal\Authentication\BearerTokenExtractor;
use Skedli\HttpMiddleware\Internal\Authentication\TokenValidationFailed;
use Skedli\HttpMiddleware\Internal\Authentication\UnauthorizedResponse;

final readonly class AuthenticationMiddleware implements MiddlewareInterface
{
    public const string AUTHENTICATED_USER_ATTRIBUTE = 'authenticatedUser';

    private function __construct(
        private TokenDecoder $tokenDecoder,
        private BearerTokenExtractor $tokenExtractor
    ) {
    }

    public static function create(): AuthenticationMiddlewareBuilder
    {
        return new AuthenticationMiddlewareBuilder();
    }

    public static function build(TokenDecoder $tokenDecoder): AuthenticationMiddleware
    {
        return new AuthenticationMiddleware(
            tokenDecoder: $tokenDecoder,
            tokenExtractor: new BearerTokenExtractor()
        );
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $token = $this->tokenExtractor->extract(request: $request);
            $authenticatedUser = $this->tokenDecoder->decode(token: $token);
        } catch (TokenValidationFailed $exception) {
            return UnauthorizedResponse::because(message: $exception->getMessage())->toResponse();
        }

        $request = $request->withAttribute(self::AUTHENTICATED_USER_ATTRIBUTE, $authenticatedUser);

        return $handler->handle(request: $request);
    }
}
