<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Support\Query;

/**
 * Callback type which on execution returns value passed during creation.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackReturnValue extends Callback implements ICallbackNamed
{
    protected $value;
    protected $name;

    public function __construct($value, $name = null)
    {
        $this->value = &$value;
        $this->name = $name;
        $this->callback = [$this, 'callback'];
    }

    public function callback()
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getName()
    {
        return 'Callback: '.$this->name;
    }

    public function hasName()
    {
        return isset($this->name) && $this->name;
    }
}
