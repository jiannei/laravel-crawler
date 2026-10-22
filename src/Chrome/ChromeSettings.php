<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Chrome;

final readonly class ChromeSettings
{
    public function __construct(
        public string $serverUrl,
        public int $port,
        public array $arguments,
        public int $waitTimeoutInSeconds,
        public int $waitIntervalInMilliseconds,
        public int $connectionTimeoutInMilliseconds,
        public int $requestTimeoutInMilliseconds,
        public string $binary,
        public string $logPath,
        public string $logLevel,
    ) {
    }

    public static function fromConfig(array $config): self
    {
        $server = $config['server'] ?? [];

        if (!is_array($server)) {
            throw new \InvalidArgumentException('The [crawler.chrome.server] setting must be an array.');
        }

        $url = $server['url'] ?? null;

        if (!is_string($url) || '' === trim($url) || false === filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('The [crawler.chrome.server.url] setting must be a valid URL.');
        }

        $url = trim($url);
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('The [crawler.chrome.server.url] setting must contain a scheme and host.');
        }

        if (isset($parts['port']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('The [crawler.chrome.server.url] setting must not contain a port, credentials, query, or fragment; use [crawler.chrome.server.port].');
        }

        $arguments = $config['arguments'] ?? [];

        if (!is_array($arguments)) {
            throw new \InvalidArgumentException('The [crawler.chrome.arguments] setting must be an array.');
        }

        foreach ($arguments as $argument) {
            if (!is_string($argument) || '' === $argument) {
                throw new \InvalidArgumentException('Every [crawler.chrome.arguments] item must be a non-empty string.');
            }
        }

        $wait = $config['wait'] ?? [];

        if (!is_array($wait)) {
            throw new \InvalidArgumentException('The [crawler.chrome.wait] setting must be an array.');
        }

        $log = $config['log'] ?? [];

        if (!is_array($log)) {
            throw new \InvalidArgumentException('The [crawler.chrome.log] setting must be an array.');
        }

        $binary = $config['binary'] ?? 'chromedriver';

        if (!is_string($binary) || '' === trim($binary)) {
            throw new \InvalidArgumentException('The [crawler.chrome.binary] setting must be a non-empty string.');
        }

        $logPath = $log['path'] ?? storage_path('logs/crawler-server.log');

        if (!is_string($logPath) || '' === trim($logPath)) {
            throw new \InvalidArgumentException('The [crawler.chrome.log.path] setting must be a non-empty string.');
        }

        $logLevel = $log['level'] ?? 'INFO';

        if (!is_string($logLevel) || '' === trim($logLevel)) {
            throw new \InvalidArgumentException('The [crawler.chrome.log.level] setting must be a non-empty string.');
        }

        return new self(
            serverUrl: $url,
            port: self::integer($server['port'] ?? null, 'crawler.chrome.server.port', 4444, 1, 65535),
            arguments: array_values($arguments),
            waitTimeoutInSeconds: self::integer($wait['timeout_in_second'] ?? null, 'crawler.chrome.wait.timeout_in_second', 30),
            waitIntervalInMilliseconds: self::integer($wait['interval_in_millisecond'] ?? null, 'crawler.chrome.wait.interval_in_millisecond', 250),
            connectionTimeoutInMilliseconds: self::integer($server['connection_timeout_in_ms'] ?? null, 'crawler.chrome.server.connection_timeout_in_ms', 30000),
            requestTimeoutInMilliseconds: self::integer($server['request_timeout_in_ms'] ?? null, 'crawler.chrome.server.request_timeout_in_ms', 30000),
            binary: trim($binary),
            logPath: trim($logPath),
            logLevel: trim($logLevel),
        );
    }

    public function endpoint(): string
    {
        $parts = parse_url($this->serverUrl);

        return $parts['scheme'].'://'.$parts['host'].':'.$this->port.($parts['path'] ?? '');
    }

    private static function integer(mixed $value, string $key, int $default, int $min = 1, int $max = PHP_INT_MAX): int
    {
        if (null === $value) {
            return $default;
        }

        if (is_string($value) && '' !== $value && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("The [$key] setting must be an integer between $min and $max.");
        }

        return $value;
    }
}
