<?php

namespace Jiannei\LaravelCrawler\Support\Traits;

trait Consumable
{
    public function process(array $pattern, array $content): bool
    {
        $this->before();

        $result = $this->resolveCallback($pattern)($pattern, $content);

        $this->after();

        return $result;
    }

    public function valid(array $pattern): bool
    {
        return method_exists($this, $this->resolveCallbackMethod($pattern)) && is_callable($this->resolveCallback($pattern));
    }

    public function resolveCallbackMethod(array $pattern): string
    {
        return $pattern['consume'] ?? 'defaultCallback';
    }

    protected function before()
    {

    }

    protected function after()
    {

    }

    protected function resolveCallback(array $pattern):\Closure
    {
        return $this->{$this->resolveCallbackMethod($pattern)}();
    }
}