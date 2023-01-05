<?php

namespace Jiannei\LaravelCrawler;
use Closure;
use Illuminate\Support\Collection;
use Jiannei\LaravelCrawler\Services\MultiRequestService;
use Jiannei\LaravelCrawler\Support\Dom\Query;
use Jiannei\LaravelCrawler\Support\Query\phpQuery;
use Jiannei\LaravelCrawler\Support\Query\phpQueryObject;


/**
 * Class QueryList
 * @package QL
 *
 * @method string getHtml($rel = true)
 * @method QueryList setHtml($html)
 * @method QueryList html($html)
 * @method phpQueryObject find($selector)
 * @method QueryList rules(array $rules)
 * @method QueryList range($range)
 * @method QueryList removeHead()
 * @method QueryList query(Closure $callback = null)
 * @method Collection getData(Closure $callback = null)
 * @method Array queryData(Closure $callback = null)
 * @method QueryList setData(Collection $data)
 * @method QueryList encoding(string $outputEncoding,string $inputEncoding = null)
 * @method QueryList get($url,$args = null,$otherArgs = [])
 * @method QueryList post($url,$args = null,$otherArgs = [])
 * @method QueryList postJson($url,$args = null,$otherArgs = [])
 * @method MultiRequestService multiGet($urls)
 * @method MultiRequestService multiPost($urls)
 * @method QueryList use($plugins,...$opt)
 * @method QueryList pipe(Closure $callback = null)
 */
class QueryList
{
    protected $query;
    protected $kernel;
    protected static $instance = null;

    /**
     * QueryList constructor.
     */
    public function __construct()
    {
        $this->query = new Query($this);
        $this->kernel = (new Kernel($this))->bootstrap();
        Config::getInstance()->bootstrap($this);
    }

    public function __call($name, $arguments)
    {
        if(method_exists($this->query,$name)){
            $result = $this->query->$name(...$arguments);
        }else{
            $result = $this->kernel->getService($name)->call($this,...$arguments);
        }
       return $result;
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = new self();
        return $instance->$name(...$arguments);
    }

    public function __destruct()
    {
        unset($this->query);
        unset($this->kernel);
    }

    /**
     * Get the QueryList single instance
     *
     * @return QueryList
     */
    public static function getInstance()
    {
        self::$instance || self::$instance = new self();
        return self::$instance;
    }

    /**
     * Get the Config instance
     * @return null|Config
     */
    public static function config()
    {
        return Config::getInstance();
    }

    /**
     * Destroy all documents
     */
    public static function destructDocuments()
    {
        phpQuery::$documents = [];
    }

    /**
     * Bind a custom method to the QueryList object
     *
     * @param string $name Invoking the name
     * @param Closure $provide Called method
     * @return $this
     */
    public function bind(string $name,Closure $provide)
    {
        $this->kernel->bind($name,$provide);
        return $this;
    }

}