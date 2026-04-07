<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\Health\HealthCheckRunner;
use TinyBlocks\EnvironmentVariable\EnvironmentVariable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\Response;

final readonly class HealthCheckHandler implements RequestHandlerInterface
{
    private array $checks;

    public function __construct(HealthCheck ...$checks)
    {
        $this->checks = $checks;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $report = HealthCheckRunner::from(checks: $this->checks)->run();

        $code = $report->hasCriticalFailure ? Code::SERVICE_UNAVAILABLE : Code::OK;

        $serviceName = EnvironmentVariable::fromOrDefault(
            name: 'APP_NAME',
            defaultValueIfNotFound: 'app'
        )->toString();

        $body = [
            'status'  => $code->message(),
            'service' => $serviceName,
        ];

        if (!empty($report->checks)) {
            $body['checks'] = $report->checks;
        }

        return $report->hasCriticalFailure
            ? Response::from(code: Code::SERVICE_UNAVAILABLE, body: $body)
            : Response::ok(body: $body);
    }
}
