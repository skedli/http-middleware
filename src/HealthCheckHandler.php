<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\Health\HealthCheckHandlerBuilder;
use Skedli\HttpMiddleware\Internal\Health\HealthCheckRunner;
use TinyBlocks\EnvironmentVariable\EnvironmentVariable;
use TinyBlocks\Http\Code;
use TinyBlocks\Http\Response;

final readonly class HealthCheckHandler implements RequestHandlerInterface
{
    /** @param HealthCheck[] $checks */
    private function __construct(private array $checks)
    {
    }

    public static function create(): HealthCheckHandlerBuilder
    {
        return new HealthCheckHandlerBuilder();
    }

    /** @param HealthCheck[] $checks */
    public static function build(array $checks): HealthCheckHandler
    {
        return new HealthCheckHandler(checks: $checks);
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

        return Response::from(code: $code, body: $body);
    }
}
