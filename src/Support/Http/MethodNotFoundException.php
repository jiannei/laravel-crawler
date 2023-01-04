<?php

namespace Jiannei\LaravelCrawler\Support\Http;
use Exception;
use Throwable;

class MethodNotFoundException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}