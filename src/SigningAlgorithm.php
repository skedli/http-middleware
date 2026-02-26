<?php

declare(strict_types=1);

namespace Skedli\HttpMiddleware;

/**
 * Defines the supported signing algorithms for token validation.
 */
enum SigningAlgorithm: string
{
    case RS256 = 'RS256';
    case RS384 = 'RS384';
    case RS512 = 'RS512';
    case HS256 = 'HS256';
    case HS384 = 'HS384';
    case HS512 = 'HS512';
    case ES256 = 'ES256';
    case ES384 = 'ES384';
}
