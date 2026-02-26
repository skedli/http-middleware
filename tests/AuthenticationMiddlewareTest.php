<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware;

use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Skedli\HttpMiddleware\AuthenticatedUser;
use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\Internal\Authentication\TokenValidationFailed;
use Skedli\HttpMiddleware\SigningAlgorithm;
use Skedli\HttpMiddleware\TokenDecoder;
use Test\Skedli\HttpMiddleware\Mocks\CapturingHandler;
use TinyBlocks\Http\Code;

final class AuthenticationMiddlewareTest extends TestCase
{
    private const string SECRET = 'super-secret-key-for-testing-purposes';

    private string $publicKey;
    private string $privateKey;

    protected function setUp(): void
    {
        $keyPair = openssl_pkey_new([
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export(key: $keyPair, output: $privateKeyPem);

        $details = openssl_pkey_get_details(key: $keyPair);

        $this->privateKey = $privateKeyPem;
        $this->publicKey = $details['key'];
    }

    public function testAuthenticatesValidToken(): void
    {
        /** @Given a valid JWT token with sub, iat, and exp claims */
        $issuedAt = time();
        $expiresAt = $issuedAt + 3600;
        $token = JWT::encode(
            payload: ['sub' => 'user-123', 'iat' => $issuedAt, 'exp' => $expiresAt],
            key: self::SECRET,
            alg: SigningAlgorithm::HS256->value
        );

        /** @And a request with a valid Authorization Bearer header */
        $request = new ServerRequest('GET', '/', ['Authorization' => "Bearer $token"]);

        /** @And a middleware configured with HS256 algorithm and secret key */
        $middleware = AuthenticationMiddleware::create()
            ->withAlgorithm(SigningAlgorithm::HS256)
            ->withKeyMaterial(self::SECRET)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the authenticated user should be set as a request attribute */
        $authenticatedUser = $handler->capturedAuthenticatedUser();

        self::assertNotNull($authenticatedUser);
        self::assertInstanceOf(AuthenticatedUser::class, $authenticatedUser);
        self::assertSame('user-123', $authenticatedUser->userId());
        self::assertSame($issuedAt, $authenticatedUser->issuedAt());
        self::assertSame($expiresAt, $authenticatedUser->expiresAt());

        /** @And the response should have a 200 status code */
        self::assertSame(Code::OK->value, $response->getStatusCode());
    }

    public function testReturnsUnauthorizedWhenAuthorizationHeaderIsMissing(): void
    {
        /** @Given a request without the Authorization header */
        $request = new ServerRequest('GET', '/');

        /** @And a middleware configured with HS256 algorithm and secret key */
        $middleware = AuthenticationMiddleware::create()
            ->withAlgorithm(SigningAlgorithm::HS256)
            ->withKeyMaterial(self::SECRET)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate the missing header */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Missing Authorization header.', $body['message']);
    }

    public function testReturnsUnauthorizedWhenAuthorizationHeaderIsNotBearerScheme(): void
    {
        /** @Given a request with an Authorization header that does not use the Bearer scheme */
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Basic dXNlcjpwYXNz']);

        /** @And a middleware configured with HS256 algorithm and secret key */
        $middleware = AuthenticationMiddleware::create()
            ->withAlgorithm(SigningAlgorithm::HS256)
            ->withKeyMaterial(self::SECRET)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate the wrong scheme */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Authorization header must use Bearer scheme.', $body['message']);
    }

    public function testReturnsUnauthorizedWhenBearerTokenIsEmpty(): void
    {
        /** @Given a request with an empty Bearer token */
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer ']);

        /** @And a middleware configured with HS256 algorithm and secret key */
        $middleware = AuthenticationMiddleware::create()
            ->withAlgorithm(SigningAlgorithm::HS256)
            ->withKeyMaterial(self::SECRET)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate the token is empty */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Bearer token is empty.', $body['message']);
    }

    public function testReturnsUnauthorizedWhenTokenIsMalformed(): void
    {
        /** @Given a request with a malformed token */
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer not.a.valid.jwt']);

        /** @And a middleware configured with RS256 and a public key */
        $middleware = AuthenticationMiddleware::create()
            ->withKeyMaterial($this->publicKey)
            ->withAlgorithm(SigningAlgorithm::RS256)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate the token is invalid */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Token is invalid or could not be decoded.', $body['message']);
    }

    public function testReturnsUnauthorizedWhenTokenHasExpired(): void
    {
        /** @Given a JWT token that has already expired */
        $token = JWT::encode(
            payload: [
                'sub' => 'e3b0c442-98fc-1c14-b39f-f32d831cb27a',
                'iat' => time() - 7200,
                'exp' => time() - 3600
            ],
            key: $this->privateKey,
            alg: 'RS256'
        );

        /** @And a request with the expired token */
        $request = new ServerRequest('GET', '/', ['Authorization' => "Bearer $token"]);

        /** @And a middleware configured with the matching public key */
        $middleware = AuthenticationMiddleware::create()
            ->withKeyMaterial($this->publicKey)
            ->withAlgorithm(SigningAlgorithm::RS256)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate the token expired */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Token has expired.', $body['message']);
    }

    public function testReturnsUnauthorizedWhenTokenSignatureDoesNotMatch(): void
    {
        /** @Given a JWT token signed with a different private key */
        $differentKeyPair = openssl_pkey_new([
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export(key: $differentKeyPair, output: $differentPrivateKey);

        $token = JWT::encode(
            payload: [
                'sub' => 'e3b0c442-98fc-1c14-b39f-f32d831cb27a',
                'iat' => time(),
                'exp' => time() + 3600
            ],
            key: $differentPrivateKey,
            alg: 'RS256'
        );

        /** @And a request with the mismatched token */
        $request = new ServerRequest('GET', '/', ['Authorization' => "Bearer $token"]);

        /** @And a middleware configured with the original public key */
        $middleware = AuthenticationMiddleware::create()
            ->withKeyMaterial($this->publicKey)
            ->withAlgorithm(SigningAlgorithm::RS256)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate validation failure */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Token is invalid or could not be decoded.', $body['message']);
    }

    public function testReturnsUnauthorizedWhenTokenIsMissingSubjectClaim(): void
    {
        /** @Given a JWT token without the sub claim */
        $token = JWT::encode(
            payload: [
                'iat' => time(),
                'exp' => time() + 3600
            ],
            key: $this->privateKey,
            alg: 'RS256'
        );

        /** @And a request with the token missing sub */
        $request = new ServerRequest('GET', '/', ['Authorization' => "Bearer $token"]);

        /** @And a middleware configured with the matching public key */
        $middleware = AuthenticationMiddleware::create()
            ->withKeyMaterial($this->publicKey)
            ->withAlgorithm(SigningAlgorithm::RS256)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should indicate the missing claim */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Token is missing the subject (sub) claim.', $body['message']);
    }

    public function testPropagatesAuthenticatedUserOnValidRsaToken(): void
    {
        /** @Given a valid JWT token signed with RS256 */
        $issuedAt = time();
        $expiresAt = $issuedAt + 3600;
        $userId = 'e3b0c442-98fc-1c14-b39f-f32d831cb27a';

        $token = JWT::encode(
            payload: ['sub' => $userId, 'iat' => $issuedAt, 'exp' => $expiresAt],
            key: $this->privateKey,
            alg: 'RS256'
        );

        /** @And a request with the valid Bearer token */
        $request = new ServerRequest('GET', '/', ['Authorization' => "Bearer $token"]);

        /** @And a middleware configured with the matching public key */
        $middleware = AuthenticationMiddleware::create()
            ->withKeyMaterial($this->publicKey)
            ->withAlgorithm(SigningAlgorithm::RS256)
            ->build();

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the authenticated user should be propagated as a request attribute */
        $authenticatedUser = $handler->capturedAuthenticatedUser();

        self::assertNotNull($authenticatedUser);
        self::assertInstanceOf(AuthenticatedUser::class, $authenticatedUser);
        self::assertSame($userId, $authenticatedUser->userId());
        self::assertSame($issuedAt, $authenticatedUser->issuedAt());
        self::assertSame($expiresAt, $authenticatedUser->expiresAt());
    }

    public function testAcceptsCustomTokenDecoderImplementation(): void
    {
        /** @Given a custom authenticated user implementation */
        $expectedUser = new readonly class implements AuthenticatedUser {
            public function userId(): string
            {
                return 'custom-user-id';
            }

            public function issuedAt(): int
            {
                return 1000000;
            }

            public function expiresAt(): int
            {
                return 9999999;
            }
        };

        /** @And a custom token decoder that always returns the fixed user */
        $customDecoder = new readonly class ($expectedUser) implements TokenDecoder {
            public function __construct(private AuthenticatedUser $authenticatedUser)
            {
            }

            public function decode(string $token): AuthenticatedUser
            {
                return $this->authenticatedUser;
            }
        };

        /** @And a middleware configured with the custom decoder */
        $middleware = AuthenticationMiddleware::create()
            ->withTokenDecoder($customDecoder)
            ->build();

        /** @And a request with any Bearer token */
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer any-opaque-token-value']);

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 200 OK */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        /** @And the custom user should be propagated */
        $authenticatedUser = $handler->capturedAuthenticatedUser();

        self::assertNotNull($authenticatedUser);
        self::assertSame('custom-user-id', $authenticatedUser->userId());
        self::assertSame(1000000, $authenticatedUser->issuedAt());
        self::assertSame(9999999, $authenticatedUser->expiresAt());
    }

    public function testTokenDecoderPrecedesKeyMaterialConfiguration(): void
    {
        /** @Given a custom authenticated user */
        $expectedUser = new readonly class implements AuthenticatedUser {
            public function userId(): string
            {
                return 'decoder-wins';
            }

            public function issuedAt(): int
            {
                return 100;
            }

            public function expiresAt(): int
            {
                return 999;
            }
        };

        /** @And a custom token decoder */
        $customDecoder = new readonly class ($expectedUser) implements TokenDecoder {
            public function __construct(private AuthenticatedUser $authenticatedUser)
            {
            }

            public function decode(string $token): AuthenticatedUser
            {
                return $this->authenticatedUser;
            }
        };

        /** @And a middleware configured with both a custom decoder and key material */
        $middleware = AuthenticationMiddleware::create()
            ->withTokenDecoder($customDecoder)
            ->withKeyMaterial($this->publicKey)
            ->withAlgorithm(SigningAlgorithm::RS256)
            ->build();

        /** @And a request with any Bearer token */
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer irrelevant-token']);

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the custom decoder should take precedence */
        self::assertSame(Code::OK->value, $response->getStatusCode());

        $authenticatedUser = $handler->capturedAuthenticatedUser();

        self::assertNotNull($authenticatedUser);
        self::assertSame('decoder-wins', $authenticatedUser->userId());
    }

    public function testThrowsWhenBuiltWithoutTokenDecoderOrKeyMaterial(): void
    {
        /** @Given a builder without any configuration */
        $this->expectException(TokenValidationFailed::class);
        $this->expectExceptionMessage('A TokenDecoder instance or key material must be provided');

        /** @When the builder attempts to build the middleware */
        AuthenticationMiddleware::create()->build();
    }

    public function testThrowsWhenBuiltWithKeyMaterialButWithoutAlgorithm(): void
    {
        /** @Given a builder with key material but without an algorithm */
        $this->expectException(TokenValidationFailed::class);
        $this->expectExceptionMessage('A signing algorithm must be provided');

        /** @When the builder attempts to build the middleware */
        AuthenticationMiddleware::create()
            ->withKeyMaterial($this->publicKey)
            ->build();
    }

    public function testReturnsUnauthorizedWhenCustomDecoderThrowsTokenValidationFailed(): void
    {
        /** @Given a custom token decoder that always rejects tokens */
        $rejectingDecoder = new readonly class implements TokenDecoder {
            public function decode(string $token): AuthenticatedUser
            {
                throw TokenValidationFailed::withReason(reason: 'Custom validation failed.');
            }
        };

        /** @And a middleware configured with the rejecting decoder */
        $middleware = AuthenticationMiddleware::create()
            ->withTokenDecoder($rejectingDecoder)
            ->build();

        /** @And a request with a Bearer token */
        $request = new ServerRequest('GET', '/', ['Authorization' => 'Bearer some-token']);

        /** @And a handler that captures the request */
        $handler = new CapturingHandler();

        /** @When the middleware processes the request */
        $response = $middleware->process($request, $handler);

        /** @Then the response should be 401 Unauthorized */
        self::assertSame(Code::UNAUTHORIZED->value, $response->getStatusCode());

        /** @And the handler should not have been called */
        self::assertNull($handler->capturedAuthenticatedUser());

        /** @And the response body should contain the custom error message */
        $body = json_decode((string)$response->getBody(), true);
        self::assertSame('UNAUTHORIZED', $body['code']);
        self::assertSame('Custom validation failed.', $body['message']);
    }
}
