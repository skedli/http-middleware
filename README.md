# HTTP Middleware

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Default usage](#default-usage)
    * [Reusing an existing correlation ID](#reusing-an-existing-correlation-id)
    * [Custom provider](#custom-provider)
    * [Integration with tiny-blocks/logger](#integration-with-tiny-blockslogger)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div>

## Overview

Provides a PSR-15 middleware for correlation ID propagation in HTTP requests.

Built on top of [PSR-15](https://www.php-fig.org/psr/psr-15) and [PSR-7](https://www.php-fig.org/psr/psr-7), the
middleware can be used with any framework that supports the `MiddlewareInterface` standard.

The middleware reads the `Correlation-Id` header from the incoming request. If present and non-empty, it reuses the
value. Otherwise, it generates a new UUID v4. In both cases, the correlation ID is:

- Injected as a request attribute (`correlationId`) for downstream handlers.
- Added to the response as the `Correlation-Id` header.

<div id='installation'></div>

## Installation

```bash
composer require skedli/http-middleware
```

<div id='how-to-use'></div>

## How to use

### Default usage

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

### Reusing an existing correlation ID

When the incoming request already contains the `Correlation-Id` header, the middleware preserves it instead of
generating a new one. This enables end-to-end traceability across service boundaries.

```
Client → BFF (generates Correlation-Id: req-abc-123)
       → Service X (reuses req-abc-123)
       → Service Y (reuses req-abc-123)
       → Service Z (reuses req-abc-123)
```

No additional configuration is needed. The middleware handles this automatically.

### Custom provider

Implement the `CorrelationIdProvider` interface to replace the default UUID v4 generation strategy:

```php
use Skedli\HttpMiddleware\CorrelationId;
use Skedli\HttpMiddleware\CorrelationIdProvider;
use Skedli\HttpMiddleware\Internal\UuidCorrelationId;

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

### Integration with tiny-blocks/logger

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

<div id='license'></div>

## License

HTTP Middleware is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/skedli/http-middleware/blob/main/CONTRIBUTING.md) to
contribute to the project.
