# HTTP Middleware

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Correlation ID](#correlation-id)
        * [Default usage](#default-usage)
        * [Reusing an existing correlation ID](#reusing-an-existing-correlation-id)
        * [Custom provider](#custom-provider)
        * [Integration with tiny-blocks/logger](#integration-with-tiny-blockslogger)
    * [Request and response logging](#request-and-response-logging)
        * [Default usage](#log-default-usage)
        * [What is logged](#what-is-logged)
        * [Automatic correlation ID binding](#automatic-correlation-id)
    * [Error handling](#error-handling)
        * [Default usage](#error-default-usage)
        * [Error handling settings](#error-handling-settings)
        * [Logging errors](#logging-errors)
    * [Authentication](#authentication)
        * [Default usage](#auth-default-usage)
        * [Supported algorithms](#supported-algorithms)
        * [Accessing the authenticated user](#accessing-the-authenticated-user)
        * [Custom token decoder](#custom-token-decoder)
        * [Custom authenticated user](#custom-authenticated-user)
        * [Builder precedence](#builder-precedence)
* [License](#license)

<div id='overview'></div>

## Overview

Provides PSR-15 middleware for HTTP requests, including correlation ID propagation, structured request/response logging,
error handling, and stateless JWT authentication.

Built on top of [PSR-15](https://www.php-fig.org/psr/psr-15) and [PSR-7](https://www.php-fig.org/psr/psr-7), the
middleware can be used with any framework that supports the `MiddlewareInterface` standard.

<div id='installation'></div>

## Installation

```bash
composer require skedli/http-middleware
```

<div id='how-to-use'></div>

## How to use

<div id='correlation-id'></div>

### Correlation ID

The middleware reads the `Correlation-Id` header from the incoming request. If present and non-empty, it reuses the
value. Otherwise, it generates a new UUID v4. In both cases, the correlation ID is:

- Injected as a request attribute (`correlationId`) for downstream handlers.
- Added to the response as the `Correlation-Id` header.

<div id='default-usage'></div>

#### Default usage

Create the middleware with `CorrelationIdMiddleware::create()` and register it in your application. The default provider
generates a UUID v4 when no `Correlation-Id` header is present.

```php
use Skedli\HttpMiddleware\CorrelationIdMiddleware;

$middleware = CorrelationIdMiddleware::create()->build();
```

In a Slim 4 application:

```php
use Skedli\HttpMiddleware\CorrelationIdMiddleware;

$app->add(CorrelationIdMiddleware::create()->build());
```

The correlation ID is accessible in any handler via the request attribute:

```php
use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\CorrelationId;

$correlationId = $request->getAttribute('correlationId');
/** @var CorrelationId $correlationId */
$correlationId->toString(); # "550e8400-e29b-41d4-a716-446655440000"
```

<div id='reusing-an-existing-correlation-id'></div>

#### Reusing an existing correlation ID

When the incoming request already contains the `Correlation-Id` header, the middleware preserves it instead of
generating a new one. This enables end-to-end traceability across service boundaries.

```
Client → BFF (generates Correlation-Id: req-abc-123)
       → Service X (reuses req-abc-123)
       → Service Y (reuses req-abc-123)
       → Service Z (reuses req-abc-123)
```

No additional configuration is needed. The middleware handles this automatically.

<div id='custom-provider'></div>

#### Custom provider

Implement the `CorrelationIdProvider` interface to replace the default UUID v4 generation strategy:

```php
use Skedli\HttpMiddleware\CorrelationId;
use Skedli\HttpMiddleware\CorrelationIdProvider;
use Skedli\HttpMiddleware\Internal\CorrelationId\UuidCorrelationId;

final readonly class PrefixedCorrelationIdProvider implements CorrelationIdProvider
{
    public function __construct(private string $prefix)
    {
    }

    public function generate(): CorrelationId
    {
        return UuidCorrelationId::from(value: sprintf('%s-%s', $this->prefix, bin2hex(random_bytes(8))));
    }
}
```

Then configure the middleware with the custom provider:

```php
use Skedli\HttpMiddleware\CorrelationIdMiddleware;

$middleware = CorrelationIdMiddleware::create()
    ->withProvider(provider: new PrefixedCorrelationIdProvider(prefix: 'bff'))
    ->build();
```

<div id='integration-with-tiny-blockslogger'></div>

#### Integration with tiny-blocks/logger

The middleware is designed to work seamlessly with
[tiny-blocks/logger](https://github.com/tiny-blocks/logger). Extract the correlation ID from the request
attribute and bind it to the logger context:

```php
use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\CorrelationId;
use TinyBlocks\Logger\LogContext;
use TinyBlocks\Logger\Logger;

final readonly class CreateUserHandler
{
    public function __construct(private Logger $logger)
    {
    }

    public function __invoke(ServerRequestInterface $request): void
    {
        /** @var CorrelationId $correlationId */
        $correlationId = $request->getAttribute('correlationId');

        $logger = $this->logger->withContext(
            context: LogContext::from(correlationId: $correlationId->toString())
        );

        $logger->info(message: 'user.creating', context: ['email' => 'john@example.com']);
    }
}
```

Output:

```
2026-02-21T16:00:00+00:00 component=identity correlation_id=550e8400-e29b-41d4-a716-446655440000 level=INFO key=user.creating data={"email":"jo**@example.com"}
```

<div id='request-and-response-logging'></div>

### Request and response logging

The `LogMiddleware` provides structured logging for every HTTP request and response. It captures method, URI,
query parameters, body, status code, and duration automatically.

<div id='log-default-usage'></div>

#### Default usage

Create the middleware with a [tiny-blocks/logger](https://github.com/tiny-blocks/logger) instance:

```php
use Skedli\HttpMiddleware\LogMiddleware;
use TinyBlocks\Logger\Logger;

$middleware = LogMiddleware::create(logger: $logger);
```

In a Slim 4 application:

```php
use Skedli\HttpMiddleware\LogMiddleware;

$app->add(LogMiddleware::create(logger: $logger));
```

<div id='what-is-logged'></div>

#### What is logged

The middleware logs two entries per request cycle, one for the incoming **request** and one for the outgoing **response
**.

**Request** is always logged at `info` level:

```
level=INFO key=request data={"method":"POST","uri":"/api/users","query_parameters":{"page":"1"},"body":{"name":"John","email":"john@example.com"}}
```

**Response** is logged at `info` for success (2xx/3xx) and `error` for failures (4xx/5xx):

```
level=INFO key=response data={"method":"POST","uri":"/api/users","status_code":201,"duration_ms":45.32,"body":{"id":"550e8400","name":"John"}}
```

```
level=ERROR key=response data={"method":"POST","uri":"/api/users","status_code":422,"duration_ms":12.87,"body":{"error":"Validation failed"}}
```

The `query_parameters` and `body` fields are **only included when present**. A `GET` request without query parameters
or body produces a minimal log:

```
level=INFO key=request data={"method":"GET","uri":"/api/health"}
level=INFO key=response data={"method":"GET","uri":"/api/health","status_code":200,"duration_ms":1.04}
```

<div id='automatic-correlation-id'></div>

#### Automatic correlation ID binding

When used together with the `CorrelationIdMiddleware`, the `LogMiddleware` automatically binds the correlation ID
to the logger context. No additional configuration is needed, just register both middleware in the correct order:

```php
use Skedli\HttpMiddleware\CorrelationIdMiddleware;
use Skedli\HttpMiddleware\LogMiddleware;

// In Slim 4, middleware executes in LIFO order (last added = first to run).
// CorrelationIdMiddleware must run before LogMiddleware.
$app->add(LogMiddleware::create(logger: $logger));
$app->add(CorrelationIdMiddleware::create()->build());
```

The correlation ID is then automatically included in every log entry:

```
2026-02-24T10:00:00+00:00 component=identity correlation_id=550e8400-e29b-41d4-a716-446655440000 level=INFO key=request data={"method":"POST","uri":"/api/users","body":{"name":"John"}}
2026-02-24T10:00:00+00:00 component=identity correlation_id=550e8400-e29b-41d4-a716-446655440000 level=INFO key=response data={"method":"POST","uri":"/api/users","status_code":201,"duration_ms":45.32}
```

If the `CorrelationIdMiddleware` is not registered, the `LogMiddleware` works normally without the correlation ID.

<div id='error-handling'></div>

### Error handling

The `ErrorMiddleware` catches uncaught exceptions during request processing and transforms them into structured JSON
responses. Error logging and response detail exposure are fully configurable through `ErrorHandlingSettings`.

When used together with the `CorrelationIdMiddleware`, the `ErrorMiddleware` automatically binds the correlation ID
to the error log context. No additional configuration is needed, just register both middleware in the correct order.

<div id='error-default-usage'></div>

#### Default usage

Register the middleware to automatically catch exceptions. If the exception contains a valid HTTP error code (4xx or
5xx), it is used, otherwise, it defaults to **500 Internal Server Error**.

By default, error details are not displayed in the response and errors are not logged.

```php
use Skedli\HttpMiddleware\ErrorMiddleware;

$middleware = ErrorMiddleware::create()->build();
```

Response body:

```json
{
    "error": "Unexpected database error"
}
```

<div id='error-handling-settings'></div>

#### Error handling settings

Use `ErrorHandlingSettings` to control how errors are displayed and logged:

| Setting               | Default | Description                                                                |
|-----------------------|---------|----------------------------------------------------------------------------|
| `logErrors`           | `false` | Enables logging of exceptions when a logger is provided                    |
| `logErrorDetails`     | `false` | Includes exception class, file, line, and stack trace in the log context   |
| `displayErrorDetails` | `false` | Includes exception class, file, line, and stack trace in the response body |

**Development** full visibility in response and logs:

```php
use Skedli\HttpMiddleware\ErrorMiddleware;
use Skedli\HttpMiddleware\Internal\Error\ErrorHandlingSettings;

$middleware = ErrorMiddleware::create()
    ->withLogger(logger: $logger)
    ->withSettings(settings: ErrorHandlingSettings::from(
        logErrors: true,
        logErrorDetails: true,
        displayErrorDetails: true
    ))
    ->build();
```

Response body:

```json
{
    "error": "Unexpected database error",
    "exception": "RuntimeException",
    "file": "/app/src/Application/Handlers/UserCreatingHandler.php",
    "line": 42,
    "trace": [
        "#0 /app/src/Driver/Http/Endpoints/User/CreateUser.php(25): ...",
        "#1 /app/vendor/slim/slim/Slim/Handlers/Strategies/RequestResponseArgs.php(30): ..."
    ]
}
```

Log output:

```
correlation_id=550e8400-e29b-41d4-a716-446655440000 level=ERROR key=error data={"message":"Unexpected database error","exception":"RuntimeException","file":"/app/src/...","line":42,"trace":"#0 /app/src/..."}
```

**Production** log with details, minimal response:

```php
use Skedli\HttpMiddleware\ErrorMiddleware;
use Skedli\HttpMiddleware\Internal\Error\ErrorHandlingSettings;

$middleware = ErrorMiddleware::create()
    ->withLogger(logger: $logger)
    ->withSettings(settings: ErrorHandlingSettings::from(
        logErrors: true,
        logErrorDetails: true,
        displayErrorDetails: false
    ))
    ->build();
```

Response body:

```json
{
    "error": "Unexpected database error"
}
```

Log output:

```
correlation_id=550e8400-e29b-41d4-a716-446655440000 level=ERROR key=error data={"message":"Unexpected database error","exception":"RuntimeException","file":"/app/src/...","line":42,"trace":"#0 /app/src/..."}
```

<div id='logging-errors'></div>

#### Logging errors

To enable error logging, provide a [tiny-blocks/logger](https://github.com/tiny-blocks/logger) instance **and** enable
`logErrors` in the settings. The logger alone is not sufficient, `logErrors` must be explicitly enabled.

```php
use Skedli\HttpMiddleware\ErrorMiddleware;
use Skedli\HttpMiddleware\Internal\Error\ErrorHandlingSettings;

$middleware = ErrorMiddleware::create()
    ->withLogger(logger: $logger)
    ->withSettings(settings: ErrorHandlingSettings::from(
        logErrors: true,
        logErrorDetails: false,
        displayErrorDetails: false
    ))
    ->build();
```

Log output:

```
correlation_id=550e8400-e29b-41d4-a716-446655440000 level=ERROR key=error data={"message":"Unexpected database error"}
```

If the `CorrelationIdMiddleware` is not registered, the `ErrorMiddleware` works normally without the correlation ID.

<div id='authentication'></div>

### Authentication

The `AuthenticationMiddleware` enforces stateless token-based authentication on incoming requests. It extracts the
Bearer token from the `Authorization` header, validates it using a `TokenDecoder`, and propagates the authenticated
user context as a request attribute.

No database access is performed validation relies exclusively on the token's cryptographic signature and its claims.

<div id='auth-default-usage'></div>

#### Default usage

Configure the middleware with a signing algorithm and key material. The built-in `JwtTokenDecoder` handles JWT
validation using [firebase/php-jwt](https://github.com/firebase/php-jwt).

**With RSA (asymmetric):**

```php
use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\SigningAlgorithm;

$middleware = AuthenticationMiddleware::create()
    ->withAlgorithm(algorithm: SigningAlgorithm::RS256)
    ->withKeyMaterial(keyMaterial: $publicKey)
    ->build();
```

**With HMAC (symmetric):**

```php
use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\SigningAlgorithm;

$middleware = AuthenticationMiddleware::create()
    ->withAlgorithm(algorithm: SigningAlgorithm::HS256)
    ->withKeyMaterial(keyMaterial: 'your-shared-secret-key')
    ->build();
```

In a Slim 4 application:

```php
use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\SigningAlgorithm;

$app->add(
    AuthenticationMiddleware::create()
        ->withAlgorithm(algorithm: SigningAlgorithm::RS256)
        ->withKeyMaterial(keyMaterial: $publicKey)
        ->build()
);
```

When authentication fails, the middleware returns a `401 Unauthorized` response:

```json
{
    "code": "UNAUTHORIZED",
    "message": "Token has expired."
}
```

Possible error messages:

| Message                                        | Cause                                                  |
|------------------------------------------------|--------------------------------------------------------|
| `Missing Authorization header.`                | The request has no `Authorization` header              |
| `Authorization header must use Bearer scheme.` | The header does not start with `Bearer `               |
| `Bearer token is empty.`                       | The header is `Bearer` with no token value             |
| `Token is invalid or could not be decoded.`    | The token is malformed or the signature does not match |
| `Token has expired.`                           | The token `exp` claim is in the past                   |
| `Token is missing the subject (sub) claim.`    | The token has no `sub` claim                           |

<div id='supported-algorithms'></div>

#### Supported algorithms

The `SigningAlgorithm` enum defines the supported algorithms:

| Algorithm | Type  | Use case                        |
|-----------|-------|---------------------------------|
| `RS256`   | RSA   | Asymmetric — public/private key |
| `RS384`   | RSA   | Asymmetric — public/private key |
| `RS512`   | RSA   | Asymmetric — public/private key |
| `HS256`   | HMAC  | Symmetric — shared secret       |
| `HS384`   | HMAC  | Symmetric — shared secret       |
| `HS512`   | HMAC  | Symmetric — shared secret       |
| `ES256`   | ECDSA | Asymmetric — elliptic curve     |
| `ES384`   | ECDSA | Asymmetric — elliptic curve     |

<div id='accessing-the-authenticated-user'></div>

#### Accessing the authenticated user

On successful authentication, the middleware injects an `AuthenticatedUser` instance as a request attribute. The
`AuthenticatedUser` is an interface with three methods:

```php
use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\AuthenticatedUser;
use Skedli\HttpMiddleware\AuthenticationMiddleware;

/** @var AuthenticatedUser $user */
$user = $request->getAttribute(AuthenticationMiddleware::AUTHENTICATED_USER_ATTRIBUTE);

$user->userId();    # "e3b0c442-98fc-1c14-b39f-f32d831cb27a"
$user->issuedAt();  # 1740000000
$user->expiresAt(); # 1740003600
```

<div id='custom-token-decoder'></div>

#### Custom token decoder

Implement the `TokenDecoder` interface to replace the built-in JWT validation with your own strategy. The decoder
must validate the token locally (stateless), without performing any network call or database query.

```php
use Skedli\HttpMiddleware\AuthenticatedUser;
use Skedli\HttpMiddleware\TokenDecoder;
use Skedli\HttpMiddleware\Internal\Authentication\TokenValidationFailed;

final readonly class OpaqueTokenDecoder implements TokenDecoder
{
    public function __construct(private TokenStore $tokenStore)
    {
    }

    public function decode(string $token): AuthenticatedUser
    {
        $claims = $this->tokenStore->lookup($token);

        if ($claims === null) {
            throw TokenValidationFailed::withReason(reason: 'Token not found.');
        }

        return MyAuthenticatedUser::from(claims: $claims);
    }
}
```

Then configure the middleware with the custom decoder:

```php
use Skedli\HttpMiddleware\AuthenticationMiddleware;

$middleware = AuthenticationMiddleware::create()
    ->withTokenDecoder(tokenDecoder: new OpaqueTokenDecoder($tokenStore))
    ->build();
```

<div id='custom-authenticated-user'></div>

#### Custom authenticated user

The `AuthenticatedUser` is an interface, so you can extend it with additional claims specific to your domain.
Return your custom implementation from your `TokenDecoder`:

```php
use Skedli\HttpMiddleware\AuthenticatedUser;

final readonly class TenantAwareUser implements AuthenticatedUser
{
    public function __construct(
        private string $userId,
        private int $issuedAt,
        private int $expiresAt,
        private string $tenantId,
        private array $roles
    ) {
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

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function roles(): array
    {
        return $this->roles;
    }
}
```

Access the extended claims in your handler:

```php
use Psr\Http\Message\ServerRequestInterface;
use Skedli\HttpMiddleware\AuthenticationMiddleware;

/** @var TenantAwareUser $user */
$user = $request->getAttribute(AuthenticationMiddleware::AUTHENTICATED_USER_ATTRIBUTE);

$user->userId();   # "e3b0c442-98fc-1c14-b39f-f32d831cb27a"
$user->tenantId(); # "tenant-42"
$user->roles();    # ["admin", "billing"]
```

<div id='builder-precedence'></div>

#### Builder precedence

When both a custom `TokenDecoder` and key material are provided, the custom decoder **always takes precedence**.
The key material and algorithm are ignored:

```php
use Skedli\HttpMiddleware\AuthenticationMiddleware;
use Skedli\HttpMiddleware\SigningAlgorithm;

// The custom decoder wins — key material and algorithm are ignored.
$middleware = AuthenticationMiddleware::create()
    ->withAlgorithm(algorithm: SigningAlgorithm::RS256)
    ->withKeyMaterial(keyMaterial: $publicKey)
    ->withTokenDecoder(tokenDecoder: $customDecoder)
    ->build();
```

Building the middleware without a `TokenDecoder` or key material throws a `TokenValidationFailed` exception.
Using key material without an algorithm also throws.

<div id='license'></div>

## License

HTTP Middleware is licensed under [MIT](LICENSE).
