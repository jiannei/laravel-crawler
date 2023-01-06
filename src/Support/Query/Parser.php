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

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Exception;
use Iterator;
use Jiannei\LaravelCrawler\Support\Dom\DOMDocumentWrapper;
use Jiannei\LaravelCrawler\Support\Dom\DOMEvent;

class Parser implements Iterator
{
    public $documentID = null;
    /**
     * DOMDocument class.
     *
     * @var DOMDocument
     */
    public $document = null;
    public $charset = null;
    /**
     * @var DOMDocumentWrapper
     */
    public $documentWrapper = null;
    /**
     * XPath interface.
     *
     * @var DOMXPath
     */
    public $xpath = null;
    /**
     * Stack of selected elements.
     *
     * @TODO refactor to ->nodes
     *
     * @var array
     */
    public $elements = [];

    protected $elementsBackup = [];

    protected $previous = null;
    /**
     * @TODO deprecate
     */
    protected $root = [];
    /**
     * Indicated if doument is just a fragment (no <html> tag).
     *
     * Every document is realy a full document, so even documentFragments can
     * be queried against <html>, but getDocument(id)->htmlOuter() will return
     * only contents of <body>.
     *
     * @var bool
     */
    public $documentFragment = true;
    /**
     * Iterator interface helper.
     */
    protected $elementsInterator = [];
    /**
     * Iterator interface helper.
     */
    protected $valid = false;
    /**
     * Iterator interface helper.
     */
    protected $current = null;

    /**
     * Enter description here...
     *
     * @return Parser
     *
     * @throws Exception
     */
    public function __construct($documentID)
    {
        $id = $documentID instanceof self ? $documentID->getDocumentID() : $documentID;
        if (!isset(Dom::$documents[$id])) {
            throw new Exception("Document with ID '{$id}' isn't loaded. Use phpQuery::newDocument(\$html) or phpQuery::newDocumentFile(\$file) first.");
        }

        $this->documentID = $id;
        $this->documentWrapper = Dom::$documents[$id];
        $this->document = $this->documentWrapper->document;
        $this->xpath = $this->documentWrapper->xpath;
        $this->charset = $this->documentWrapper->charset;
        $this->documentFragment = $this->documentWrapper->isDocumentFragment;
        $this->root = $this->documentWrapper->root;
        $this->elements = [$this->root];
    }

    /**
     * @TODO documentWrapper
     */
    protected function isRoot($node)
    {
        return $node instanceof DOMDocument
            || ($node instanceof DOMElement && 'html' == $node->tagName)
            || $this->root->isSameNode($node);
    }

    protected function stackIsRoot()
    {
        return 1 == $this->size() && $this->isRoot($this->elements[0]);
    }

    /**
     * Enter description here...
     * NON JQUERY METHOD.
     *
     * Watch out, it doesn't create new instance, can be reverted with end().
     *
     * @return Parser
     */
    public function toRoot()
    {
        $this->elements = [$this->root];

        return $this;
        //		return $this->newInstance(array($this->root));
    }

    /**
     * Returns object with stack set to document root.
     *
     * @return Parser
     */
    public function getDocument()
    {
        return Dom::getDocument($this->getDocumentID());
    }

    /**
     * @return DOMDocument
     */
    public function getDOMDocument()
    {
        return $this->document;
    }

    /**
     * Get object's Document ID.
     *
     * @return Parser
     */
    public function getDocumentID()
    {
        return $this->documentID;
    }

    /**
     * Unloads whole document from memory.
     * CAUTION! None further operations will be possible on this document.
     * All objects refering to it will be useless.
     *
     * @return Parser
     */
    public function unloadDocument()
    {
        Dom::unloadDocuments($this->getDocumentID());
    }

    protected function debug($in)
    {
        debug(json_encode($in));
    }

    protected function isRegexp($pattern)
    {
        return in_array($pattern[mb_strlen($pattern) - 1], ['^', '*', '$']);
    }

    /**
     * Determines if $char is really a char.
     *
     * @param string $char
     *
     * @return bool
     *
     * @todo rewrite me to charcode range ! ;)
     */
    protected function isChar($char)
    {
        return extension_loaded('mbstring') ? mb_eregi('\w', $char) : preg_match('@\w@', $char);
    }

    protected function parseSelector($query)
    {
        // clean spaces
        // TODO include this inside parsing ?
        $query = trim(preg_replace('@\s+@', ' ', preg_replace('@\s*(>|\\+|~)\s*@', '\\1', $query)));
        $queries = [[]];
        if (!$query) {
            return $queries;
        }
        $return = &$queries[0];
        $specialChars = ['>', ' '];
        //		$specialCharsMapping = array('/' => '>');
        $specialCharsMapping = [];
        $strlen = \mb_strlen($query);
        $classChars = ['.', '-'];
        $pseudoChars = ['-'];
        $tagChars = ['*', '|', '-'];
        // split multibyte string
        // http://code.google.com/p/phpquery/issues/detail?id=76
        $_query = [];
        for ($i = 0; $i < $strlen; ++$i) {
            $_query[] = \mb_substr($query, $i, 1);
        }
        $query = $_query;

        // it works, but i dont like it...
        $i = 0;
        while ($i < $strlen) {
            $c = $query[$i];
            $tmp = '';
            // TAG
            if ($this->isChar($c) || in_array($c, $tagChars)) {
                while (isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $tagChars))) {
                    $tmp .= $query[$i];
                    ++$i;
                }
                $return[] = $tmp;
            // IDs
            } else {
                if ('#' == $c) {
                    ++$i;
                    while (isset($query[$i]) && ($this->isChar($query[$i]) || '-' == $query[$i])) {
                        $tmp .= $query[$i];
                        ++$i;
                    }
                    $return[] = '#'.$tmp;
                // SPECIAL CHARS
                } else {
                    if (in_array($c, $specialChars)) {
                        $return[] = $c;
                        ++$i;
                    // MAPPED SPECIAL MULTICHARS
                        //			} else if ( $c.$query[$i+1] == '//') {
                        //				$return[] = ' ';
                        //				$i = $i+2;
                        // MAPPED SPECIAL CHARS
                    } else {
                        if (isset($specialCharsMapping[$c])) {
                            $return[] = $specialCharsMapping[$c];
                            ++$i;
                        // COMMA
                        } else {
                            if (',' == $c) {
                                $queries[] = [];
                                $return = &$queries[count($queries) - 1];
                                ++$i;
                                while (isset($query[$i]) && ' ' == $query[$i]) {
                                    ++$i;
                                }
                                // CLASSES
                            } else {
                                if ('.' == $c) {
                                    while (isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $classChars))) {
                                        $tmp .= $query[$i];
                                        ++$i;
                                    }
                                    $return[] = $tmp;
                                // ~ General Sibling Selector
                                } else {
                                    if ('~' == $c) {
                                        $spaceAllowed = true;
                                        $tmp .= $query[$i++];
                                        while (
                                            isset($query[$i])
                                            && ($this->isChar($query[$i])
                                                || in_array($query[$i], $classChars)
                                                || '*' == $query[$i]
                                                || (' ' == $query[$i] && $spaceAllowed)
                                            )
                                        ) {
                                            if (' ' != $query[$i]) {
                                                $spaceAllowed = false;
                                            }
                                            $tmp .= $query[$i];
                                            ++$i;
                                        }
                                        $return[] = $tmp;
                                    // + Adjacent sibling selectors
                                    } else {
                                        if ('+' == $c) {
                                            $spaceAllowed = true;
                                            $tmp .= $query[$i++];
                                            while (
                                                isset($query[$i])
                                                && ($this->isChar($query[$i])
                                                    || in_array($query[$i], $classChars)
                                                    || '*' == $query[$i]
                                                    || ($spaceAllowed && ' ' == $query[$i])
                                                )
                                            ) {
                                                if (' ' != $query[$i]) {
                                                    $spaceAllowed = false;
                                                }
                                                $tmp .= $query[$i];
                                                ++$i;
                                            }
                                            $return[] = $tmp;
                                        // ATTRS
                                        } else {
                                            if ('[' == $c) {
                                                $stack = 1;
                                                $tmp .= $c;
                                                while (isset($query[++$i])) {
                                                    $tmp .= $query[$i];
                                                    if ('[' == $query[$i]) {
                                                        ++$stack;
                                                    } else {
                                                        if (']' == $query[$i]) {
                                                            --$stack;
                                                            if (!$stack) {
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }
                                                $return[] = $tmp;
                                                ++$i;
                                            // PSEUDO CLASSES
                                            } else {
                                                if (':' == $c) {
                                                    $stack = 1;
                                                    $tmp .= $query[$i++];
                                                    while (isset($query[$i]) && ($this->isChar($query[$i]) || in_array($query[$i], $pseudoChars))) {
                                                        $tmp .= $query[$i];
                                                        ++$i;
                                                    }
                                                    // with arguments ?
                                                    if (isset($query[$i]) && '(' == $query[$i]) {
                                                        $tmp .= $query[$i];
                                                        $stack = 1;
                                                        while (isset($query[++$i])) {
                                                            $tmp .= $query[$i];
                                                            if ('(' == $query[$i]) {
                                                                ++$stack;
                                                            } else {
                                                                if (')' == $query[$i]) {
                                                                    --$stack;
                                                                    if (!$stack) {
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        $return[] = $tmp;
                                                        ++$i;
                                                    } else {
                                                        $return[] = $tmp;
                                                    }
                                                } else {
                                                    ++$i;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($queries as $k => $q) {
            if (isset($q[0])) {
                if (isset($q[0][0]) && ':' == $q[0][0]) {
                    array_unshift($queries[$k], '*');
                }
                if ('>' != $q[0]) {
                    array_unshift($queries[$k], ' ');
                }
            }
        }

        return $queries;
    }

    /**
     * Return matched DOM nodes.
     *
     * @param int $index
     *
     * @return array|DOMElement single DOMElement or array of DOMElement
     */
    public function get($index = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $return = isset($index)
            ? (isset($this->elements[$index]) ? $this->elements[$index] : null)
            : $this->elements;
        // pass thou callbacks
        $args = func_get_args();
        $args = array_slice($args, 1);
        foreach ($args as $callback) {
            if (is_array($return)) {
                foreach ($return as $k => $v) {
                    dump(Dom::callbackRun($callback, [$v]), 'aaaa');
                    $return[$k] = Dom::callbackRun($callback, [$v]);
                }
            } else {
                $return = Dom::callbackRun($callback, [$return]);
            }
        }

        return $return;
    }

    /**
     * Returns new instance of actual class.
     *
     * @param array $newStack Optional. Will replace old stack with new and move old one to history.c
     */
    public function newInstance($newStack = null)
    {
        $new = new static($this);
        $new->previous = $this;
        if (is_null($newStack)) {
            $new->elements = $this->elements;
            if ($this->elementsBackup) {
                $this->elements = $this->elementsBackup;
            }
        } else {
            if (is_string($newStack)) {
                $new->elements = Dom::pq($newStack, $this->getDocumentID())->stack();
            } else {
                $new->elements = $newStack;
            }
        }

        return $new;
    }

    /**
     * Enter description here...
     *
     * In the future, when PHP will support XLS 2.0, then we would do that this way:
     * contains(tokenize(@class, '\s'), "something")
     *
     * @param $class
     * @param $node
     *
     * @return bool
     */
    protected function matchClasses($class, $node)
    {
        // multi-class
        if (\mb_strpos($class, '.', 1)) {
            $classes = explode('.', substr($class, 1));
            $classesCount = count($classes);
            $nodeClasses = explode(' ', $node->getAttribute('class'));
            $nodeClassesCount = count($nodeClasses);
            if ($classesCount > $nodeClassesCount) {
                return false;
            }
            $diff = count(
                array_diff(
                    $classes,
                    $nodeClasses
                )
            );
            if (!$diff) {
                return true;
            }
            // single-class
        } else {
            return in_array(
            // strip leading dot from class name
                substr($class, 1),
                // get classes for element as array
                explode(' ', $node->getAttribute('class'))
            );
        }
    }

    protected function runQuery($XQuery, $selector = null, $compare = null)
    {
        if ($compare && !method_exists($this, $compare)) {
            return false;
        }

        $stack = [];
        if (!$this->elements) {
            $this->debug('Stack empty, skipping...');
        }

        foreach ($this->stack([1, 9, 13]) as $stackNode) {
            $detachAfter = false;
            // to work on detached nodes we need temporary place them somewhere
            // thats because context xpath queries sucks ;]
            $testNode = $stackNode;
            while ($testNode) {
                if (!$testNode->parentNode && !$this->isRoot($testNode)) {
                    $this->root->appendChild($testNode);
                    $detachAfter = $testNode;
                    break;
                }
                $testNode = $testNode->parentNode ?? null;
            }
            // XXX tmp ?
            $xpath = $this->getNodeXpath($stackNode);
            // FIXME pseudoclasses-only query, support XML
            $query = '//' == $XQuery && '/html[1]' == $xpath ? '//*' : $xpath.$XQuery;
            $this->debug("XPATH: {$query}");
            // run query, get elements
            $nodes = $this->xpath->query($query);
            $this->debug('QUERY FETCHED');
            if (!$nodes->length) {
                $this->debug('Nothing found');
            }

            foreach ($nodes as $node) {
                $matched = false;
                if ($compare) {
                    // TODO ??? use phpQuery::callbackRun()
                    if (call_user_func_array([$this, $compare], [$selector, $node])) {
                        $matched = true;
                    }
                } else {
                    $matched = true;
                }
                if ($matched) {
                    $stack[] = $node;
                }
            }

            if ($detachAfter) {
                $this->root->removeChild($detachAfter);
            }
        }
        $this->elements = $stack;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function find($selectors)
    {
        // backup last stack /for end()/
        $this->elementsBackup = $this->elements;

        $queries = $this->parseSelector($selectors);

        $this->debug(['FIND', $selectors, $queries]);

        $XQuery = '';
        // remember stack state because of multi-queries
        $oldStack = $this->elements;
        // here we will be keeping found elements
        $stack = [];

        foreach ($queries as $selector) {
            $this->elements = $oldStack;
            $delimiterBefore = false;
            foreach ($selector as $s) {
                // TAG
                $isTag = extension_loaded('mbstring') ? mb_ereg_match('^[\w|\||-]+$', $s) || '*' == $s : preg_match('@^[\w|\||-]+$@', $s) || '*' == $s;
                if ($isTag) {
                    $XQuery .= $s;
                // ID
                } else {
                    if ('#' == $s[0]) {
                        if ($delimiterBefore) {
                            $XQuery .= '*';
                        }
                        $XQuery .= "[@id='".substr($s, 1)."']";
                    // ATTRIBUTES
                    } else {
                        if ('[' == $s[0]) {
                            if ($delimiterBefore) {
                                $XQuery .= '*';
                            }
                            // strip side brackets
                            $attr = trim($s, '][');
                            $execute = false;
                            // attr with specifed value
                            if (\mb_strpos($s, '=')) {
                                $value = null;
                                list($attr, $value) = explode('=', $attr);
                                $value = trim($value, "'\"");
                                if ($this->isRegexp($attr)) {
                                    // cut regexp character
                                    $attr = substr($attr, 0, -1);
                                    $execute = true;
                                    $XQuery .= "[@{$attr}]";
                                } else {
                                    $XQuery .= "[@{$attr}='{$value}']";
                                }
                                // attr without specified value
                            } else {
                                $XQuery .= "[@{$attr}]";
                            }
                            if ($execute) {
                                $this->runQuery($XQuery, $s, 'is');
                                $XQuery = '';
                                if (!$this->length()) {
                                    break;
                                }
                            }
                            // CLASSES
                        } else {
                            if ('.' == $s[0]) {
                                // TODO use return $this->find("./self::*[contains(concat(\" \",@class,\" \"), \" $class \")]");
                                // thx wizDom ;)
                                if ($delimiterBefore) {
                                    $XQuery .= '*';
                                }
                                $XQuery .= '[@class]';
                                $this->runQuery($XQuery, $s, 'matchClasses');
                                $XQuery = '';
                                if (!$this->length()) {
                                    break;
                                }
                                // ~ General Sibling Selector
                            } else {
                                if ('~' == $s[0]) {
                                    $this->runQuery($XQuery);
                                    $XQuery = '';
                                    $this->elements = $this
                                        ->siblings(
                                            substr($s, 1)
                                        )->elements;
                                    if (!$this->length()) {
                                        break;
                                    }
                                    // + Adjacent sibling selectors
                                } else {
                                    if ('+' == $s[0]) {
                                        // TODO /following-sibling::
                                        $this->runQuery($XQuery);
                                        $XQuery = '';
                                        $subSelector = substr($s, 1);
                                        $subElements = $this->elements;
                                        $this->elements = [];
                                        foreach ($subElements as $node) {
                                            // search first DOMElement sibling
                                            $test = $node->nextSibling;
                                            while ($test && !($test instanceof DOMElement)) {
                                                $test = $test->nextSibling;
                                            }
                                            if ($test && $this->is($subSelector, $test)) {
                                                $this->elements[] = $test;
                                            }
                                        }
                                        if (!$this->length()) {
                                            break;
                                        }
                                        // PSEUDO CLASSES
                                    } else {
                                        if (':' == $s[0]) {
                                            // TODO optimization for :first :last
                                            if ($XQuery) {
                                                $this->runQuery($XQuery);
                                                $XQuery = '';
                                            }
                                            if (!$this->length()) {
                                                break;
                                            }
                                            $this->pseudoClasses($s);
                                            if (!$this->length()) {
                                                break;
                                            }
                                            // DIRECT DESCENDANDS
                                        } else {
                                            if ('>' == $s) {
                                                $XQuery .= '/';
                                                $delimiterBefore = 2;
                                            // ALL DESCENDANDS
                                            } else {
                                                if (' ' == $s) {
                                                    $XQuery .= '//';
                                                    $delimiterBefore = 2;
                                                // ERRORS
                                                } else {
                                                    debug("Unrecognized token '$s'");
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $delimiterBefore = 2 === $delimiterBefore;
            }
            // run query if any
            if ($XQuery && '//' != $XQuery) {
                $this->runQuery($XQuery);
                $XQuery = '';
            }
            foreach ($this->elements as $node) {
                if (!$this->elementsContainsNode($node, $stack)) {
                    $stack[] = $node;
                }
            }
        }
        $this->elements = $stack;

        return $this->newInstance();
    }

    /**
     * @todo create API for classes with pseudoselectors
     */
    protected function pseudoClasses($class)
    {
        // TODO clean args parsing ?
        $class = ltrim($class, ':');
        $haveArgs = \mb_strpos($class, '(');
        if (false !== $haveArgs) {
            $args = substr($class, $haveArgs + 1, -1);
            $class = substr($class, 0, $haveArgs);
        }
        switch ($class) {
            case 'even':
            case 'odd':
                $stack = [];
                foreach ($this->elements as $i => $node) {
                    if ('even' == $class && ($i % 2) == 0) {
                        $stack[] = $node;
                    } else {
                        if ('odd' == $class && $i % 2) {
                            $stack[] = $node;
                        }
                    }
                }
                $this->elements = $stack;
                break;
            case 'eq':
                $k = intval($args);
                if ($k < 0) {
                    $this->elements = [$this->elements[count($this->elements) + $k]];
                } else {
                    $this->elements = isset($this->elements[$k])
                        ? [$this->elements[$k]]
                        : [];
                }
                break;
            case 'gt':
                $this->elements = array_slice($this->elements, $args + 1);
                break;
            case 'lt':
                $this->elements = array_slice($this->elements, 0, $args + 1);
                break;
            case 'first':
                if (isset($this->elements[0])) {
                    $this->elements = [$this->elements[0]];
                }
                break;
            case 'last':
                if ($this->elements) {
                    $this->elements = [$this->elements[count($this->elements) - 1]];
                }
                break;
            /*case 'parent':
                $stack = array();
                foreach($this->elements as $node) {
                    if ( $node->childNodes->length )
                        $stack[] = $node;
                }
                $this->elements = $stack;
                break;*/
            case 'contains':
                $text = trim($args, "\"'");
                $stack = [];
                foreach ($this->elements as $node) {
                    if (false === mb_stripos($node->textContent, $text)) {
                        continue;
                    }
                    $stack[] = $node;
                }
                $this->elements = $stack;
                break;
            case 'not':
                $selector = self::unQuote($args);
                $this->elements = $this->not($selector)->stack();
                break;
            case 'slice':
                // TODO jQuery difference ?
                $args = explode(
                    ',',
                    str_replace(', ', ',', trim($args, "\"'"))
                );
                $start = $args[0];
                $end = isset($args[1])
                    ? $args[1]
                    : null;
                if ($end > 0) {
                    $end = $end - $start;
                }
                $this->elements = array_slice($this->elements, $start, $end);
                break;
            case 'has':
                $selector = trim($args, "\"'");
                $stack = [];
                foreach ($this->stack(1) as $el) {
                    if ($this->find($selector, $el, true)->length) {
                        $stack[] = $el;
                    }
                }
                $this->elements = $stack;
                break;
            case 'submit':
            case 'reset':
                $this->elements = Dom::merge(
                    $this->map(
                        [$this, 'is'],
                        "input[type=$class]"
                    ),
                    $this->map(
                        [$this, 'is'],
                        "button[type=$class]"
                    )
                );
                break;
            case 'input':
                $this->elements = $this->map(
                    [$this, 'is'],
                    'input',
                )->elements;
                break;
            case 'password':
            case 'checkbox':
            case 'radio':
            case 'hidden':
            case 'image':
            case 'file':
                $this->elements = $this->map(
                    [$this, 'is'],
                    "input[type=$class]",
                )->elements;
                break;
            case 'parent':
                $this->elements = $this->map(
                    function ($node) {
                        return $node instanceof DOMElement && $node->childNodes->length
                            ? $node : null;
                    }
                )->elements;
                break;
            case 'empty':
                $this->elements = $this->map(
                    function ($node) {
                        return $node instanceof DOMElement && $node->childNodes->length
                            ? null : $node;
                    }
                )->elements;
                break;
            case 'disabled':
            case 'selected':
            case 'checked':
                $this->elements = $this->map(
                    [$this, 'is'],
                    "[$class]",
                )->elements;
                break;
            case 'enabled':
                $this->elements = $this->map(
                    function ($node) {
                        return Dom::pq($node)->not(':disabled') ? $node : null;
                    }
                )->elements;
                break;
            case 'header':
                $this->elements = $this->map(
                    function ($node) {
                        $isHeader = isset($node->tagName) && in_array($node->tagName, [
                                'h1',
                                'h2',
                                'h3',
                                'h4',
                                'h5',
                                'h6',
                                'h7',
                            ]);

                        return $isHeader
                            ? $node
                            : null;
                    }
                )->elements;
                //				$this->elements = $this->map(
                //					create_function('$node', '$node = pq($node);
                //						return $node->is("h1")
                //							|| $node->is("h2")
                //							|| $node->is("h3")
                //							|| $node->is("h4")
                //							|| $node->is("h5")
                //							|| $node->is("h6")
                //							|| $node->is("h7")
                //							? $node
                //							: null;')
                //				)->elements;
                break;
            case 'only-child':
                $this->elements = $this->map(
                    function ($node) {
                        return 0 == Dom::pq($node)->siblings()->size() ? $node : null;
                    }
                )->elements;
                break;
            case 'first-child':
                $this->elements = $this->map(
                    function ($node) {
                        return 0 == Dom::pq($node)->prevAll()->size() ? $node : null;
                    }
                )->elements;
                break;
            case 'last-child':
                $this->elements = $this->map(
                    function ($node) {
                        return 0 == Dom::pq($node)->nextAll()->size() ? $node : null;
                    }
                )->elements;
                break;
            case 'nth-child':
                $param = trim($args, "\"'");
                if (!$param) {
                    break;
                }
                // nth-child(n+b) to nth-child(1n+b)
                if ('n' == $param[0]) {
                    $param = '1'.$param;
                }
                // :nth-child(index/even/odd/equation)
                if ('even' == $param || 'odd' == $param) {
                    $mapped = $this->map(
                        function ($node, $param) {
                            $index = Dom::pq($node)->prevAll()->size() + 1;
                            if ('even' == $param && ($index % 2) == 0) {
                                return $node;
                            } else {
                                if ('odd' == $param && 1 == $index % 2) {
                                    return $node;
                                } else {
                                    return null;
                                }
                            }
                        },
                        $param
                    );
                } else {
                    if (mb_strlen($param) > 1 && 1 === preg_match('/^(\d*)n([-+]?)(\d*)/', $param)) { // an+b
                        $mapped = $this->map(
                            function ($node, $param) {
                                $prevs = Dom::pq($node)->prevAll()->size();
                                $index = 1 + $prevs;

                                preg_match("/^(\d*)n([-+]?)(\d*)/", $param, $matches);
                                $a = intval($matches[1]);
                                $b = intval($matches[3]);
                                if ('-' === $matches[2]) {
                                    $b = -$b;
                                }

                                if ($a > 0) {
                                    return ($index - $b) % $a == 0
                                        ? $node
                                        : null;
                                    debug($a.'*'.floor($index / $a)."+$b-1 == ".($a * floor($index / $a) + $b - 1)." ?= $prevs");

                                    return $a * floor($index / $a) + $b - 1 == $prevs
                                        ? $node
                                        : null;
                                } else {
                                    if (0 == $a) {
                                        return $index == $b
                                            ? $node
                                            : null;
                                    } else { // negative value
                                        return $index <= $b
                                            ? $node
                                            : null;
                                    }
                                }
                                //							if (! $b)
                                //								return $index%$a == 0
                                //									? $node
                                //									: null;
                                //							else
                                //								return ($index-$b)%$a == 0
                                //									? $node
                                //									: null;
                            },
                            $param
                        );
                    } else { // index
                        $mapped = $this->map(
                            function ($node, $index) {
                                $prevs = Dom::pq($node)->prevAll()->size();
                                if ($prevs && $prevs == $index - 1) {
                                    return $node;
                                } else {
                                    if (!$prevs && 1 == $index) {
                                        return $node;
                                    } else {
                                        return null;
                                    }
                                }
                            },
                            $param
                        );
                    }
                }
                $this->elements = $mapped->elements;
                break;
            default:
                $this->debug("Unknown pseudoclass '{$class}', skipping...");
        }
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function is($selector, $nodes = null)
    {
        debug(['Is:', $selector]);
        if (!$selector) {
            return false;
        }
        $oldStack = $this->elements;
        $returnArray = false;
        if ($nodes && is_array($nodes)) {
            $this->elements = $nodes;
        } else {
            if ($nodes) {
                $this->elements = [$nodes];
            }
        }
        $this->filter($selector, true);
        $stack = $this->elements;
        $this->elements = $oldStack;
        if ($nodes) {
            return $stack ? $stack : null;
        }

        return (bool) count($stack);
    }

    /**
     * Enter description here...
     * jQuery difference.
     *
     * Callback:
     * - $index int
     * - $node DOMNode
     *
     * @return Parser
     *
     * @see http://docs.jquery.com/Traversing/filter
     */
    public function filterCallback($callback, $_skipHistory = false)
    {
        if (!$_skipHistory) {
            $this->elementsBackup = $this->elements;
            $this->debug('Filtering by callback');
        }
        $newStack = [];
        foreach ($this->elements as $index => $node) {
            $result = Dom::callbackRun($callback, [$index, $node]);
            if (is_null($result) || (!is_null($result) && $result)) {
                $newStack[] = $node;
            }
        }
        $this->elements = $newStack;

        return $_skipHistory ? $this : $this->newInstance();
    }

    /**
     * Enter description here...
     *
     * @return Parser
     *
     * @see http://docs.jquery.com/Traversing/filter
     */
    public function filter($selectors, $_skipHistory = false)
    {
        if ($selectors instanceof Callback or $selectors instanceof Closure) {
            return $this->filterCallback($selectors, $_skipHistory);
        }
        if (!$_skipHistory) {
            $this->elementsBackup = $this->elements;
        }
        $notSimpleSelector = [' ', '>', '~', '+', '/'];
        if (!is_array($selectors)) {
            $selectors = $this->parseSelector($selectors);
        }
        if (!$_skipHistory) {
            $this->debug(['Filtering:', $selectors]);
        }
        $finalStack = [];
        foreach ($selectors as $selector) {
            $stack = [];
            if (!$selector) {
                break;
            }
            // avoid first space or /
            if (in_array($selector[0], $notSimpleSelector)) {
                $selector = array_slice($selector, 1);
            }
            // PER NODE selector chunks
            foreach ($this->stack() as $node) {
                $break = false;
                foreach ($selector as $s) {
                    if (!($node instanceof DOMElement)) {
                        // all besides DOMElement
                        if ('[' == $s[0]) {
                            $attr = trim($s, '[]');
                            if (\mb_strpos($attr, '=')) {
                                list($attr, $val) = explode('=', $attr);
                                if ('nodeType' == $attr && $node->nodeType != $val) {
                                    $break = true;
                                }
                            }
                        } else {
                            $break = true;
                        }
                    } else {
                        // DOMElement only
                        // ID
                        if ('#' == $s[0]) {
                            if ($node->getAttribute('id') != substr($s, 1)) {
                                $break = true;
                            }
                            // CLASSES
                        } else {
                            if ('.' == $s[0]) {
                                if (!$this->matchClasses($s, $node)) {
                                    $break = true;
                                }
                                // ATTRS
                            } else {
                                if ('[' == $s[0]) {
                                    // strip side brackets
                                    $attr = trim($s, '[]');
                                    if (\mb_strpos($attr, '=')) {
                                        list($attr, $val) = explode('=', $attr);
                                        $val = self::unQuote($val);
                                        if ('nodeType' == $attr) {
                                            if ($val != $node->nodeType) {
                                                $break = true;
                                            }
                                        } else {
                                            if ($this->isRegexp($attr)) {
                                                $val = extension_loaded('mbstring')
                                                    ? quotemeta(trim($val, '"\''))
                                                    : preg_quote(trim($val, '"\''), '@');
                                                // switch last character
                                                switch (substr($attr, -1)) {
                                                    // quotemeta used insted of preg_quote
                                                    // http://code.google.com/p/phpquery/issues/detail?id=76
                                                    case '^':
                                                        $pattern = '^'.$val;
                                                        break;
                                                    case '*':
                                                        $pattern = '.*'.$val.'.*';
                                                        break;
                                                    case '$':
                                                        $pattern = '.*'.$val.'$';
                                                        break;
                                                }
                                                // cut last character
                                                $attr = substr($attr, 0, -1);
                                                $isMatch = extension_loaded('mbstring')
                                                    ? mb_ereg_match($pattern, $node->getAttribute($attr))
                                                    : preg_match("@{$pattern}@", $node->getAttribute($attr));
                                                if (!$isMatch) {
                                                    $break = true;
                                                }
                                            } else {
                                                if ($node->getAttribute($attr) != $val) {
                                                    $break = true;
                                                }
                                            }
                                        }
                                    } else {
                                        if (!$node->hasAttribute($attr)) {
                                            $break = true;
                                        }
                                    }
                                    // PSEUDO CLASSES
                                } else {
                                    if (':' == $s[0]) {
                                        // skip
                                        // TAG
                                    } else {
                                        if (trim($s)) {
                                            if ('*' != $s) {
                                                // TODO namespaces
                                                if (isset($node->tagName)) {
                                                    if ($node->tagName != $s) {
                                                        $break = true;
                                                    }
                                                } else {
                                                    if ('html' == $s && !$this->isRoot($node)) {
                                                        $break = true;
                                                    }
                                                }
                                            }
                                            // AVOID NON-SIMPLE SELECTORS
                                        } else {
                                            if (in_array($s, $notSimpleSelector)) {
                                                $break = true;
                                                $this->debug(['Skipping non simple selector', $selector]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($break) {
                        break;
                    }
                }
                // if element passed all chunks of selector - add it to new stack
                if (!$break) {
                    $stack[] = $node;
                }
            }
            $tmpStack = $this->elements;
            $this->elements = $stack;
            // PER ALL NODES selector chunks
            foreach ($selector as $s) { // PSEUDO CLASSES
                if (':' == $s[0]) {
                    $this->pseudoClasses($s);
                }
            }
            foreach ($this->elements as $node) {
                // XXX it should be merged without duplicates
                // but jQuery doesnt do that
                $finalStack[] = $node;
            }
            $this->elements = $tmpStack;
        }
        $this->elements = $finalStack;
        if ($_skipHistory) {
            return $this;
        } else {
            $this->debug('Stack length after filter(): '.count($finalStack));

            return $this->newInstance();
        }
    }

    /**
     * @param $value
     *
     * @return
     * @TODO implement in all methods using passed parameters
     */
    protected static function unQuote($value)
    {
        return '\'' == $value[0] || '"' == $value[0] ? substr($value, 1, -1) : $value;
    }

    /**
     * Trigger a type of event on every matched element.
     *
     * @param $type
     * @param $data
     *
     * @return Parser
     * @TODO support more than event in $type (space-separated)
     */
    public function trigger($type, $data = [])
    {
        foreach ($this->elements as $node) {
            Events::trigger($this->getDocumentID(), $type, $data, $node);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function eq($num)
    {
        $oldStack = $this->elements;
        $this->elementsBackup = $this->elements;
        $this->elements = [];
        if (isset($oldStack[$num])) {
            $this->elements[] = $oldStack[$num];
        }

        return $this->newInstance();
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function size()
    {
        return count($this->elements);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     *
     * @deprecated Use length as attribute
     */
    public function length()
    {
        return $this->size();
    }

    /**
     * Enter description here...
     *
     * @param string|dom $content
     *
     * @return Parser
     *
     * @see http://docs.jquery.com/Manipulation/replaceWith#content
     */
    public function replaceWith($content)
    {
        return $this->after($content)->remove();
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function remove($selector = null)
    {
        $loop = $selector
            ? $this->filter($selector)->elements
            : $this->elements;
        foreach ($loop as $node) {
            if (!$node->parentNode) {
                continue;
            }
            if (isset($node->tagName)) {
                $this->debug("Removing '{$node->tagName}'");
            }
            $node->parentNode->removeChild($node);
            // Mutation event
            $event = new DOMEvent([
                'target' => $node,
                'type' => 'DOMNodeRemoved',
            ]);
            Events::trigger(
                $this->getDocumentID(),
                $event->type,
                [$event],
                $node
            );
        }

        return $this;
    }

    protected function markupEvents($newMarkup, $oldMarkup, $node)
    {
        if ('textarea' == $node->tagName && $newMarkup != $oldMarkup) {
            $event = new DOMEvent([
                'target' => $node,
                'type' => 'change',
            ]);
            Events::trigger(
                $this->getDocumentID(),
                $event->type,
                [$event],
                $node
            );
        }
    }

    /**
     * jQuey difference.
     *
     * @param $markup
     *
     * @return
     * @TODO trigger change event for textarea
     */
    public function markup($markup = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();

        return call_user_func_array([$this, 'html'], $args);
    }

    /**
     * Enter description here...
     *
     * @param $html
     *
     * @return string|dom
     * @TODO force html result
     */
    public function html($html = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if (isset($html)) {
            // INSERT
            $nodes = $this->documentWrapper->import($html);
            $this->empty();
            foreach ($this->stack(1) as $alreadyAdded => $node) {
                // for now, limit events for textarea
                if ('textarea' == $node->tagName) {
                    $oldHtml = Dom::pq($node, $this->getDocumentID())->markup();
                }
                foreach ($nodes as $newNode) {
                    $node->appendChild(
                        $alreadyAdded
                            ? $newNode->cloneNode(true)
                            : $newNode
                    );
                }
                // for now, limit events for textarea
                if ('textarea' == $node->tagName) {
                    $this->markupEvents($html, $oldHtml, $node);
                }
            }

            return $this;
        } else {
            // FETCH
            $return = $this->documentWrapper->markup($this->elements, true);
            $args = func_get_args();
            foreach (array_slice($args, 1) as $callback) {
                $return = Dom::callbackRun($callback, [$return]);
            }

            return $return;
        }
    }

    /**
     * Enter description here...
     *
     * @TODO force html result
     *
     * @return string
     */
    public function htmlOuter()
    {
        return $this->documentWrapper->markup($this->elements);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function children($selector = null)
    {
        $stack = [];
        foreach ($this->stack(1) as $node) {
            //			foreach($node->getElementsByTagName('*') as $newNode) {
            foreach ($node->childNodes as $newNode) {
                if (1 != $newNode->nodeType) {
                    continue;
                }
                if ($selector && !$this->is($selector, $newNode)) {
                    continue;
                }
                if ($this->elementsContainsNode($newNode, $stack)) {
                    continue;
                }
                $stack[] = $newNode;
            }
        }
        $this->elementsBackup = $this->elements;
        $this->elements = $stack;

        return $this->newInstance();
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function ancestors($selector = null)
    {
        return $this->children($selector);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function append($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function appendTo($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function prepend($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function prependTo($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function before($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @param  string|dom
     *
     * @return Parser
     */
    public function insertBefore($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function after($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Internal insert method. Don't use it.
     *
     * @param $target
     * @param $type
     *
     * @return Parser
     */
    public function insert($target, $type)
    {
        $this->debug("Inserting data with '{$type}'");
        $to = false;
        switch ($type) {
            case 'appendTo':
            case 'prependTo':
            case 'insertBefore':
            case 'insertAfter':
                $to = true;
        }
        switch (gettype($target)) {
            case 'string':
                $insertFrom = $insertTo = [];
                if ($to) {
                    // INSERT TO
                    $insertFrom = $this->elements;
                    if (Dom::isMarkup($target)) {
                        // $target is new markup, import it
                        $insertTo = $this->documentWrapper->import($target);
                    // insert into selected element
                    } else {
                        // $tagret is a selector
                        $thisStack = $this->elements;
                        $this->toRoot();
                        $insertTo = $this->find($target)->elements;
                        $this->elements = $thisStack;
                    }
                } else {
                    // INSERT FROM
                    $insertTo = $this->elements;
                    $insertFrom = $this->documentWrapper->import($target);
                }
                break;
            case 'object':
                $insertFrom = $insertTo = [];
                // phpQuery
                if ($target instanceof self) {
                    if ($to) {
                        $insertTo = $target->elements;
                        if ($this->documentFragment && $this->stackIsRoot()) {
                            // get all body children
                            //							$loop = $this->find('body > *')->elements;
                            // TODO test it, test it hard...
                            //							$loop = $this->newInstance($this->root)->find('> *')->elements;
                            $loop = $this->root->childNodes;
                        } else {
                            $loop = $this->elements;
                        }
                        // import nodes if needed
                        $insertFrom = $this->getDocumentID() == $target->getDocumentID()
                            ? $loop
                            : $target->documentWrapper->import($loop);
                    } else {
                        $insertTo = $this->elements;
                        if ($target->documentFragment && $target->stackIsRoot()) {
                            // get all body children
                            //							$loop = $target->find('body > *')->elements;
                            $loop = $target->root->childNodes;
                        } else {
                            $loop = $target->elements;
                        }
                        // import nodes if needed
                        $insertFrom = $this->getDocumentID() == $target->getDocumentID()
                            ? $loop
                            : $this->documentWrapper->import($loop);
                    }
                    // DOMNode
                } elseif ($target instanceof DOMNode) {
                    // import node if needed
                    //					if ( $target->ownerDocument != $this->DOM )
                    //						$target = $this->DOM->importNode($target, true);
                    if ($to) {
                        $insertTo = [$target];
                        if ($this->documentFragment && $this->stackIsRoot()) { // get all body children
                            $loop = $this->root->childNodes;
                        } //							$loop = $this->find('body > *')->elements;
                        else {
                            $loop = $this->elements;
                        }
                        foreach ($loop as $fromNode) { // import nodes if needed
                            $insertFrom[] = !$fromNode->ownerDocument->isSameNode($target->ownerDocument)
                                ? $target->ownerDocument->importNode($fromNode, true)
                                : $fromNode;
                        }
                    } else {
                        // import node if needed
                        if (!$target->ownerDocument->isSameNode($this->document)) {
                            $target = $this->document->importNode($target, true);
                        }
                        $insertTo = $this->elements;
                        $insertFrom[] = $target;
                    }
                }
                break;
        }
        debug('From '.count($insertFrom).'; To '.count($insertTo).' nodes');
        foreach ($insertTo as $insertNumber => $toNode) {
            // we need static relative elements in some cases
            switch ($type) {
                case 'prependTo':
                case 'prepend':
                    $firstChild = $toNode->firstChild;
                    break;
                case 'insertAfter':
                case 'after':
                    $nextSibling = $toNode->nextSibling;
                    break;
            }
            foreach ($insertFrom as $fromNode) {
                // clone if inserted already before
                $insert = $insertNumber
                    ? $fromNode->cloneNode(true)
                    : $fromNode;
                switch ($type) {
                    case 'appendTo':
                    case 'append':
                        //						$toNode->insertBefore(
                        //							$fromNode,
                        //							$toNode->lastChild->nextSibling
                        //						);
                        $toNode->appendChild($insert);
                        $eventTarget = $insert;
                        break;
                    case 'prependTo':
                    case 'prepend':
                        $toNode->insertBefore(
                            $insert,
                            $firstChild
                        );
                        break;
                    case 'insertBefore':
                    case 'before':
                        if (!$toNode->parentNode) {
                            throw new Exception("No parentNode, can't do {$type}()");
                        } else {
                            $toNode->parentNode->insertBefore(
                                $insert,
                                $toNode
                            );
                        }
                        break;
                    case 'insertAfter':
                    case 'after':
                        if (!$toNode->parentNode) {
                            throw new Exception("No parentNode, can't do {$type}()");
                        } else {
                            $toNode->parentNode->insertBefore(
                                $insert,
                                $nextSibling
                            );
                        }
                        break;
                }
                // Mutation event
                $event = new DOMEvent([
                    'target' => $insert,
                    'type' => 'DOMNodeInserted',
                ]);
                Events::trigger(
                    $this->getDocumentID(),
                    $event->type,
                    [$event],
                    $insert
                );
            }
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return int
     */
    public function index($subject)
    {
        $index = -1;
        $subject = $subject instanceof Parser
            ? $subject->elements[0]
            : $subject;
        foreach ($this->newInstance() as $k => $node) {
            if ($node->isSameNode($subject)) {
                $index = $k;
            }
        }

        return $index;
    }

    /**
     * Enter description here...
     *
     * @param $start
     * @param $end
     *
     * @return Parser
     * @testme
     */
    public function slice($start, $end = null)
    {
        //		$last = count($this->elements)-1;
        //		$end = $end
        //			? min($end, $last)
        //			: $last;
        //		if ($start < 0)
        //			$start = $last+$start;
        //		if ($start > $last)
        //			return array();
        if ($end > 0) {
            $end = $end - $start;
        }

        return $this->newInstance(
            array_slice($this->elements, $start, $end)
        );
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function reverse()
    {
        $this->elementsBackup = $this->elements;
        $this->elements = array_reverse($this->elements);

        return $this->newInstance();
    }

    /**
     * Return joined text content.
     *
     * @return string
     */
    public function text($text = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if (isset($text)) {
            return $this->html(htmlspecialchars($text));
        }
        $args = func_get_args();
        $args = array_slice($args, 1);
        $return = '';
        foreach ($this->elements as $node) {
            $text = $node->textContent;
            if (count($this->elements) > 1 && $text) {
                $text .= "\n";
            }
            foreach ($args as $callback) {
                $text = Dom::callbackRun($callback, [$text]);
            }
            $return .= $text;
        }

        return $return;
    }

    /**
     * @return The text content of each matching element, like
     *             text() but returns an array with one entry per matched element.
     *             Read only.
     */
    public function texts($attr = null)
    {
        $results = [];
        foreach ($this->elements as $node) {
            $results[] = $node->textContent;
        }

        return $results;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function prev($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('previousSibling', $selector, true)
        );
    }

    /**
     * @return Parser
     *
     * @todo
     */
    public function prevAll($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('previousSibling', $selector)
        );
    }

    /**
     * @return Parser
     *
     * @todo FIXME: returns source elements insted of next siblings
     */
    public function nextAll($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('nextSibling', $selector)
        );
    }

    protected function getElementSiblings($direction, $selector = null, $limitToOne = false)
    {
        $stack = [];
        $count = 0;
        foreach ($this->stack() as $node) {
            $test = $node;
            while (isset($test->{$direction}) && $test->{$direction}) {
                $test = $test->{$direction};
                if (!$test instanceof DOMElement) {
                    continue;
                }
                $stack[] = $test;
                if ($limitToOne) {
                    break;
                }
            }
        }
        if ($selector) {
            $stackOld = $this->elements;
            $this->elements = $stack;
            $stack = $this->filter($selector, true)->stack();
            $this->elements = $stackOld;
        }

        return $stack;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function siblings($selector = null)
    {
        $stack = [];
        $siblings = array_merge(
            $this->getElementSiblings('previousSibling', $selector),
            $this->getElementSiblings('nextSibling', $selector)
        );
        foreach ($siblings as $node) {
            if (!$this->elementsContainsNode($node, $stack)) {
                $stack[] = $node;
            }
        }

        return $this->newInstance($stack);
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function not($selector = null)
    {
        if (is_string($selector)) {
            debug(['not', $selector]);
        } else {
            debug('not');
        }
        $stack = [];
        if ($selector instanceof self || $selector instanceof DOMNode) {
            foreach ($this->stack() as $node) {
                if ($selector instanceof self) {
                    $matchFound = false;
                    foreach ($selector->stack() as $notNode) {
                        if ($notNode->isSameNode($node)) {
                            $matchFound = true;
                        }
                    }
                    if (!$matchFound) {
                        $stack[] = $node;
                    }
                } else {
                    if ($selector instanceof DOMNode) {
                        if (!$selector->isSameNode($node)) {
                            $stack[] = $node;
                        }
                    } else {
                        if (!$this->is($selector)) {
                            $stack[] = $node;
                        }
                    }
                }
            }
        } else {
            $orgStack = $this->stack();
            $matched = $this->filter($selector, true)->stack();
            //			$matched = array();
            //			// simulate OR in filter() instead of AND 5y
            //			foreach($this->parseSelector($selector) as $s) {
            //				$matched = array_merge($matched,
            //					$this->filter(array($s))->stack()
            //				);
            //			}
            foreach ($orgStack as $node) {
                if (!$this->elementsContainsNode($node, $matched)) {
                    $stack[] = $node;
                }
            }
        }

        return $this->newInstance($stack);
    }

    /**
     * Enter description here...
     *
     * @param  string|Parser
     *
     * @return Parser
     */
    public function add($selector = null)
    {
        if (!$selector) {
            return $this;
        }

        $this->elementsBackup = $this->elements;
        $found = Dom::pq($selector, $this->getDocumentID());
        $this->merge($found->elements);

        return $this->newInstance();
    }

    protected function merge()
    {
        foreach (func_get_args() as $nodes) {
            foreach ($nodes as $newNode) {
                if (!$this->elementsContainsNode($newNode)) {
                    $this->elements[] = $newNode;
                }
            }
        }
    }

    protected function elementsContainsNode($nodeToCheck, $elementsStack = null)
    {
        $loop = !is_null($elementsStack) ? $elementsStack : $this->elements;

        foreach ($loop as $node) {
            if ($node->isSameNode($nodeToCheck)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function parent($selector = null)
    {
        $stack = [];
        foreach ($this->elements as $node) {
            if ($node->parentNode && !$this->elementsContainsNode($node->parentNode, $stack)) {
                $stack[] = $node->parentNode;
            }
        }
        $this->elementsBackup = $this->elements;
        $this->elements = $stack;
        if ($selector) {
            $this->filter($selector, true);
        }

        return $this->newInstance();
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function parents($selector = null)
    {
        $stack = [];
        if (!$this->elements) {
            $this->debug('parents() - stack empty');
        }
        foreach ($this->elements as $node) {
            $test = $node;
            while ($test->parentNode) {
                $test = $test->parentNode;
                if ($this->isRoot($test)) {
                    break;
                }
                if (!$this->elementsContainsNode($test, $stack)) {
                    $stack[] = $test;
                    continue;
                }
            }
        }
        $this->elementsBackup = $this->elements;
        $this->elements = $stack;
        if ($selector) {
            $this->filter($selector, true);
        }

        return $this->newInstance();
    }

    /**
     * Internal stack iterator.
     */
    public function stack($nodeTypes = null)
    {
        if (!isset($nodeTypes)) {
            return $this->elements;
        }
        if (!is_array($nodeTypes)) {
            $nodeTypes = [$nodeTypes];
        }
        $return = [];
        foreach ($this->elements as $node) {
            if (in_array($node->nodeType, $nodeTypes)) {
                $return[] = $node;
            }
        }

        return $return;
    }

    // TODO phpdoc; $oldAttr is result of hasAttribute, before any changes
    protected function attrEvents($attr, $oldAttr, $oldValue, $node)
    {
        // skip events for XML documents
        $event = null;
        // identify
        $isInputValue = 'input' == $node->tagName
            && (in_array(
                    $node->getAttribute('type'),
                    ['text', 'password', 'hidden']
                )
                || !$node->getAttribute('type')
            );
        $isRadio = 'input' == $node->tagName
            && 'radio' == $node->getAttribute('type');
        $isCheckbox = 'input' == $node->tagName
            && 'checkbox' == $node->getAttribute('type');
        $isOption = 'option' == $node->tagName;
        if ($isInputValue && 'value' == $attr && $oldValue != $node->getAttribute($attr)) {
            $event = new DOMEvent([
                'target' => $node,
                'type' => 'change',
            ]);
        } else {
            if (($isRadio || $isCheckbox) && 'checked' == $attr && (
                    // check
                    (!$oldAttr && $node->hasAttribute($attr))
                    // un-check
                    || (!$node->hasAttribute($attr) && $oldAttr)
                )) {
                $event = new DOMEvent([
                    'target' => $node,
                    'type' => 'change',
                ]);
            } else {
                if ($isOption && $node->parentNode && 'selected' == $attr && (
                        // select
                        (!$oldAttr && $node->hasAttribute($attr))
                        // un-select
                        || (!$node->hasAttribute($attr) && $oldAttr)
                    )) {
                    $event = new DOMEvent([
                        'target' => $node->parentNode,
                        'type' => 'change',
                    ]);
                }
            }
        }
        if ($event) {
            Events::trigger(
                $this->getDocumentID(),
                $event->type,
                [$event],
                $node
            );
        }
    }

    public function attr($attr = null, $value = null)
    {
        foreach ($this->stack(1) as $node) {
            if (!is_null($value)) {
                $loop = '*' == $attr
                    ? $this->getNodeAttrs($node)
                    : [$attr];
                foreach ($loop as $a) {
                    $oldValue = $node->getAttribute($a);
                    $oldAttr = $node->hasAttribute($a);
                    // TODO raises an error when charset other than UTF-8
                    // while document's charset is also not UTF-8
                    @$node->setAttribute($a, $value);
                    $this->attrEvents($a, $oldAttr, $oldValue, $node);
                }
            } else {
                if ('*' == $attr) {
                    // jQuery difference
                    $return = [];
                    foreach ($node->attributes as $n => $v) {
                        $return[$n] = $v->value;
                    }

                    return $return;
                } else {
                    return $node->hasAttribute($attr) ? $node->getAttribute($attr) : null;
                }
            }
        }

        return is_null($value)
            ? '' : $this;
    }

    /**
     * @return The same attribute of each matching element, like
     *             attr() but returns an array with one entry per matched element.
     *             Read only.
     */
    public function attrs($attr = null)
    {
        $results = [];
        foreach ($this->stack(1) as $node) {
            $results[] = $node->hasAttribute($attr)
                ? $node->getAttribute($attr)
                : null;
        }

        return $results;
    }

    protected function getNodeAttrs($node)
    {
        $return = [];
        foreach ($node->attributes as $n => $o) {
            $return[] = $n;
        }

        return $return;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function removeAttr($attr)
    {
        foreach ($this->stack(1) as $node) {
            $loop = '*' == $attr
                ? $this->getNodeAttrs($node)
                : [$attr];
            foreach ($loop as $a) {
                $oldValue = $node->getAttribute($a);
                $node->removeAttribute($a);
                $this->attrEvents($a, $oldValue, null, $node);
            }
        }

        return $this;
    }

    /**
     * Return form element value.
     *
     * @return string fields value
     */
    public function val($val = null)
    {
        if (!isset($val)) {
            if ($this->eq(0)->is('select')) {
                $selected = $this->eq(0)->find('option[selected=selected]');
                if ($selected->is('[value]')) {
                    return $selected->attr('value');
                } else {
                    return $selected->text();
                }
            } else {
                if ($this->eq(0)->is('textarea')) {
                    return $this->eq(0)->markup();
                } else {
                    return $this->eq(0)->attr('value');
                }
            }
        } else {
            $_val = null;
            foreach ($this->stack(1) as $node) {
                $node = Dom::pq($node, $this->getDocumentID());
                if (is_array($val) && in_array($node->attr('type'), ['checkbox', 'radio'])) {
                    $isChecked = in_array($node->attr('value'), $val)
                        || in_array($node->attr('name'), $val);
                    if ($isChecked) {
                        $node->attr('checked', 'checked');
                    } else {
                        $node->removeAttr('checked');
                    }
                } else {
                    if ('select' == $node->get(0)->tagName) {
                        if (!isset($_val)) {
                            $_val = [];
                            if (!is_array($val)) {
                                $_val = [(string) $val];
                            } else {
                                foreach ($val as $v) {
                                    $_val[] = $v;
                                }
                            }
                        }
                        foreach ($node['option']->stack(1) as $option) {
                            $option = Dom::pq($option, $this->getDocumentID());
                            $selected = false;
                            // XXX: workaround for string comparsion, see issue #96
                            // http://code.google.com/p/phpquery/issues/detail?id=96
                            $selected = is_null($option->attr('value'))
                                ? in_array($option->markup(), $_val)
                                : in_array($option->attr('value'), $_val);
                            //						$optionValue = $option->attr('value');
                            //						$optionText = $option->text();
                            //						$optionTextLenght = mb_strlen($optionText);
                            //						foreach($_val as $v)
                            //							if ($optionValue == $v)
                            //								$selected = true;
                            //							else if ($optionText == $v && $optionTextLenght == mb_strlen($v))
                            //								$selected = true;
                            if ($selected) {
                                $option->attr('selected', 'selected');
                            } else {
                                $option->removeAttr('selected');
                            }
                        }
                    } else {
                        if ('textarea' == $node->get(0)->tagName) {
                            $node->markup($val);
                        } else {
                            $node->attr('value', $val);
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function andSelf()
    {
        if ($this->previous) {
            $this->elements = array_merge($this->elements, $this->previous->elements);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function addClass($className)
    {
        if (!$className) {
            return $this;
        }
        foreach ($this->stack(1) as $node) {
            if (!$this->is(".$className", $node)) {
                $node->setAttribute(
                    'class',
                    trim($node->getAttribute('class').' '.$className)
                );
            }
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @param string $className
     *
     * @return bool
     */
    public function hasClass($className)
    {
        foreach ($this->stack(1) as $node) {
            if ($this->is(".$className", $node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function removeClass($className)
    {
        foreach ($this->stack(1) as $node) {
            $classes = explode(' ', $node->getAttribute('class'));
            if (in_array($className, $classes)) {
                $classes = array_diff($classes, [$className]);
                if ($classes) {
                    $node->setAttribute('class', implode(' ', $classes));
                } else {
                    $node->removeAttribute('class');
                }
            }
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     */
    public function toggleClass($className)
    {
        foreach ($this->stack(1) as $node) {
            if ($this->is($node, '.'.$className)) {
                $this->removeClass($className);
            } else {
                $this->addClass($className);
            }
        }

        return $this;
    }

    /**
     * Proper name without underscore (just ->empty()) also works.
     *
     * Removes all child nodes from the set of matched elements.
     *
     * Example:
     * pq("p")._empty()
     *
     * HTML:
     * <p>Hello, <span>Person</span> <a href="#">and person</a></p>
     *
     * Result:
     * [ <p></p> ]
     *
     * @return Parser
     */
    public function empty()
    {
        foreach ($this->stack(1) as $node) {
            // thx to 'dave at dgx dot cz'
            $node->nodeValue = '';
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @param array|string $callback Expects $node as first param, $index as second
     * @param array        $scope    External variables passed to callback. Use compact('varName1', 'varName2'...) and extract($scope)
     * @param array        $arg1     will ba passed as third and futher args to callback
     * @param array        $arg2     Will ba passed as fourth and futher args to callback, and so on...
     *
     * @return Parser
     */
    public function each($callback, $param1 = null, $param2 = null, $param3 = null)
    {
        $paramStructure = null;
        if (func_num_args() > 1) {
            $paramStructure = func_get_args();
            $paramStructure = array_slice($paramStructure, 1);
        }
        foreach ($this->elements as $v) {
            Dom::callbackRun($callback, [$v], $paramStructure);
        }

        return $this;
    }

    /**
     * Run callback on actual object.
     *
     * @return Parser
     */
    public function callback($callback, $param1 = null, $param2 = null, $param3 = null)
    {
        $params = func_get_args();
        $params[0] = $this;
        Dom::callbackRun($callback, $params);

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return Parser
     *
     * @todo add $scope and $args as in each() ???
     */
    public function map($callback, $param1 = null, $param2 = null, $param3 = null)
    {
        //		$stack = array();
        // //		foreach($this->newInstance() as $node) {
        //		foreach($this->newInstance() as $node) {
        //			$result = call_user_func($callback, $node);
        //			if ($result)
        //				$stack[] = $result;
        //		}
        $params = func_get_args();
        array_unshift($params, $this->elements);

        return $this->newInstance(call_user_func_array(['phpQuery', 'map'], $params));
    }

    /**
     * Enter description here...
     *
     * @param $key
     * @param $value
     */
    public function data($key, $value = null)
    {
        if (!isset($value)) {
            // TODO? implement specific jQuery behavior od returning parent values
            // is child which we look up doesn't exist
            return Dom::data($this->get(0), $key, $value, $this->getDocumentID());
        } else {
            foreach ($this as $node) {
                Dom::data($node, $key, $value, $this->getDocumentID());
            }

            return $this;
        }
    }

    /**
     * Enter description here...
     *
     * @param $key
     */
    public function removeData($key)
    {
        foreach ($this as $node) {
            Dom::removeData($node, $key, $this->getDocumentID());
        }

        return $this;
    }
    // INTERFACE IMPLEMENTATIONS

    // ITERATOR INTERFACE

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->debug('iterating foreach');
        //		phpQuery::selectDocument($this->getDocumentID());
        $this->elementsBackup = $this->elements;
        $this->elementsInterator = $this->elements;
        $this->valid = isset($this->elements[0])
            ? 1 : 0;
        // 		$this->elements = $this->valid
        // 			? array($this->elements[0])
        // 			: array();
        $this->current = 0;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->elementsInterator[$this->current];
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->current;
    }

    /**
     * Double-function method.
     *
     * First: main iterator interface method.
     * Second: Returning next sibling, alias for _next().
     *
     * Proper functionality is choosed automagicaly.
     *
     * @return Parser
     */
    #[\ReturnTypeWillChange]
    public function next($cssSelector = null)
    {
        //		if ($cssSelector || $this->valid)
        //			return $this->_next($cssSelector);
        $this->valid = isset($this->elementsInterator[$this->current + 1])
            ? true
            : false;
        if (!$this->valid && $this->elementsInterator) {
            $this->elementsInterator = null;
        } else {
            if ($this->valid) {
                ++$this->current;
            } else {
                return $this->newInstance(
                    $this->getElementSiblings('nextSibling', $cssSelector, true)
                );
            }
        }
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->valid;
    }

    // ITERATOR INTERFACE END

    /**
     * Returns node's XPath.
     *
     * @param $oneNode
     *
     * @return string
     * @TODO use native getNodePath is avaible
     */
    protected function getNodeXpath($oneNode = null, $namespace = null)
    {
        $return = [];
        $loop = $oneNode ? [$oneNode] : $this->elements;
        foreach ($loop as $node) {
            if ($node instanceof DOMDocument) {
                $return[] = '';
                continue;
            }
            $xpath = [];
            while (!($node instanceof DOMDocument)) {
                $i = 1;
                $sibling = $node;
                while ($sibling->previousSibling) {
                    $sibling = $sibling->previousSibling;
                    $isElement = $sibling instanceof DOMElement;
                    if ($isElement && $sibling->tagName == $node->tagName) {
                        ++$i;
                    }
                }
                $xpath[] = "{$node->tagName}[{$i}]";
                $node = $node->parentNode;
            }
            $xpath = implode('/', array_reverse($xpath));
            $return[] = '/'.$xpath;
        }

        return $oneNode ? $return[0] : $return;
    }
}
