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
 * Callback type which on execution returns reference passed during creation.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
class CallbackReturnReference extends Callback implements ICallbackNamed
{
    protected $reference;

    public function __construct(&$reference, $name = null)
    {
        $this->reference = &$reference;
        $this->callback = [$this, 'callback'];
    }

    public function callback()
    {
        return $this->reference;
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
