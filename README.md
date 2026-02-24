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
        * [Logging errors](#logging-errors)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div>

## Overview

Provides PSR-15 middleware for HTTP requests, including correlation ID propagation, structured request/response logging,
and error handling.

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

The middleware logs two entries per request cycle — one for the incoming **request** and one for the outgoing **response
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
to the logger context. No additional configuration is needed — just register both middleware in the correct order:

```php
use Skedli\HttpMiddleware\CorrelationIdMiddleware;
use Skedli\HttpMiddleware\LogMiddleware;

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

The library provides an `ErrorMiddleware` to catch uncaught exceptions during request processing. It transforms
exceptions into structured JSON responses and optionally logs the error details.

<div id='error-default-usage'></div>

#### Default usage

Register the middleware to automatically catch exceptions. If the exception contains a valid HTTP error code (4xx or
5xx), it is used, otherwise, it defaults to **500 Internal Server Error**.

```php
use Skedli\HttpMiddleware\ErrorMiddleware;

$middleware = ErrorMiddleware::create()->build();
```

<div id='logging-errors'></div>

#### Logging errors

You can integrate with [tiny-blocks/logger](https://github.com/tiny-blocks/logger) to automatically log the exception
message before the response is generated.

```php
use Skedli\HttpMiddleware\ErrorMiddleware;
use TinyBlocks\Logger\Logger;

$middleware = ErrorMiddleware::create()
    ->withLogger(logger: $logger)
    ->build();
```

<div id='license'></div>

## License

HTTP Middleware is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/skedli/http-middleware/blob/main/CONTRIBUTING.md) to
contribute to the project.
