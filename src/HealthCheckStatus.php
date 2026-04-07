<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

enum HealthCheckStatus: string
{
    case UP = 'UP';
    case DOWN = 'DOWN';
}
