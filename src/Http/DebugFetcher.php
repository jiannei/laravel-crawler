<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <jiannei@sinan.fun>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Http;

use Jiannei\LaravelCrawler\Contracts\Fetcher;
use Jiannei\LaravelCrawler\Data\FetchResponse;
use Psr\Log\LoggerInterface;

/**
 * Logs one structured summary for every crawler fetch.
 */
final class DebugFetcher implements Fetcher
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'api-key',
        'api_key',
        'apikey',
        'token',
        'access_token',
        'refresh_token',
        'password',
        'secret',
    ];

    public function __construct(
        private readonly Fetcher $fetcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetch(string $url, array|string|null $query = null, array $options = []): FetchResponse
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->fetcher->fetch($url, $query, $options);
        } catch (\Throwable $exception) {
            $this->logger->error('http', [
                'event' => 'fetch.failed',
                'url' => $url,
                'query' => $this->redact($query),
                'options' => $this->redact($options),
                'duration_ms' => $this->duration($startedAt),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logger->debug('http', [
            'event' => 'fetch.completed',
            'url' => $url,
            'query' => $this->redact($query),
            'options' => $this->redact($options),
            'status' => $response->status,
            'duration_ms' => $this->duration($startedAt),
            'response_headers' => $this->redact($response->headers),
            'body_length' => strlen($response->body),
        ]);

        return $response;
    }

    private function duration(int|float $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_object($value) || is_resource($value) ? '['.get_debug_type($value).']' : $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)
                ? '[redacted]'
                : $this->redact($item);
        }

        return $value;
    }
}
