<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

/**
 * Represents a single infrastructure or dependency health check.
 *
 * Implementations verify the availability of a specific external resource
 * (e.g., database, cache, message broker) and report its status.
 *
 * Each check is executed during the health endpoint request and contributes
 * to the overall service health status.
 */
interface HealthCheck
{
    /**
     * Returns the logical name of this health check (e.g., "database", "redis", "rabbitmq").
     *
     * @return string A short, unique, lowercase identifier for the checked component.
     */
    public function name(): string;

    /**
     * Executes the health check and returns the result.
     *
     * Implementations SHOULD catch any infrastructure exception internally
     * and return a DOWN result instead of propagating the exception.
     *
     * @return HealthCheckResult The outcome of the check.
     */
    public function check(): HealthCheckResult;
}
