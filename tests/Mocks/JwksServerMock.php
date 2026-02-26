<?php

declare(strict_types=1);

namespace Test\Skedli\HttpMiddleware\Mocks;

use RuntimeException;

final class JwksServerMock
{
    private const string HOST = '127.0.0.1';
    private const int PORT = 18999;

    /** @var resource|null */
    private static $process = null;

    public static function start(string $publicKeyPem): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($publicKeyPem));
        $rsa = $details['rsa'];

        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'n'   => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                    'e'   => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
                ]
            ]
        ];

        return self::startWithRawJson(json: json_encode($jwks, JSON_UNESCAPED_SLASHES));
    }

    public static function startWithRawJson(string $json): string
    {
        $documentRoot = sys_get_temp_dir() . '/fake-jwks-' . getmypid();

        if (!is_dir($documentRoot)) {
            mkdir(directory: $documentRoot, recursive: true);
        }

        file_put_contents($documentRoot . '/jwks.json', $json);

        $routerScript = $documentRoot . '/router.php';
        file_put_contents(
            $routerScript,
            <<<'PHP'
            <?php
            header('Content-Type: application/json');
            echo file_get_contents(__DIR__ . '/jwks.json');
            PHP
        );

        $command = sprintf(
            'php -S %s:%d -t %s %s',
            self::HOST,
            self::PORT,
            $documentRoot,
            $routerScript
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException(
                sprintf('Failed to start JwksServerMock on %s:%d', self::HOST, self::PORT)
            );
        }

        self::$process = $process;

        self::waitUntilReady();

        return sprintf('http://%s:%d/v1/keys/public', self::HOST, self::PORT);
    }

    public static function stop(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }

    private static function waitUntilReady(): void
    {
        $maxAttempts = 50;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $connection = @fsockopen(self::HOST, self::PORT, $errno, $errstr, 0.1);

            if ($connection !== false) {
                fclose($connection);
                return;
            }

            usleep(20_000);
        }

        self::stop();

        throw new RuntimeException(
            sprintf('JwksServerMock failed to start on %s:%d', self::HOST, self::PORT)
        );
    }
}
