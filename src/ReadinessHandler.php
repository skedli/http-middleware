<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Skedli\HttpMiddleware\Internal\HealthCheck\ChecksRunner;
use Skedli\HttpMiddleware\Internal\HealthCheck\DrainMarker;
use Skedli\HttpMiddleware\Internal\HealthCheck\HealthPayload;
use Skedli\HttpMiddleware\Internal\HealthCheck\ReadinessHandlerBuilder;
use TinyBlocks\EnvironmentVariable\EnvironmentVariable;
use TinyBlocks\Http\Server\Response;

final readonly class ReadinessHandler implements RequestHandlerInterface
{
    private function __construct(private array $checks, private ?DrainMarker $drainMarker = null)
    {
    }

    public static function create(): ReadinessHandlerBuilder
    {
        return new ReadinessHandlerBuilder();
    }

    public static function build(array $checks, ?DrainMarker $drainMarker = null): ReadinessHandler
    {
        return new ReadinessHandler(checks: $checks, drainMarker: $drainMarker);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $serviceName = EnvironmentVariable::fromOrDefault(
            name: 'APP_NAME',
            defaultValueIfNotFound: 'app'
        )->toString();

        if (!is_null($this->drainMarker) && $this->drainMarker->isDraining()) {
            return Response::serviceUnavailable(
                body: HealthPayload::draining(service: $serviceName)->jsonSerialize()
            );
        }

        $report = ChecksRunner::from(checks: $this->checks)->run();

        if ($report->hasCriticalFailure) {
            return Response::serviceUnavailable(
                body: HealthPayload::unready(service: $serviceName, checks: $report)->jsonSerialize()
            );
        }

        return Response::ok(
            body: HealthPayload::ready(service: $serviceName, checks: $report)->jsonSerialize()
        );
    }
}
