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

use ArrayAccess;
use Countable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Exception;
use Iterator;

class phpQueryObject implements Iterator, Countable, ArrayAccess
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
     * @return phpQueryObject
     *
     * @throws Exception
     */
    public function __construct($documentID)
    {
        //		if ($documentID instanceof self)
        //			var_dump($documentID->getDocumentID());
        $id = $documentID instanceof self
            ? $documentID->getDocumentID()
            : $documentID;
        //		var_dump($id);
        if (!isset(phpQuery::$documents[$id])) {
            //			var_dump(phpQuery::$documents);
            throw new Exception("Document with ID '{$id}' isn't loaded. Use phpQuery::newDocument(\$html) or phpQuery::newDocumentFile(\$file) first.");
        }
        $this->documentID = $id;
        $this->documentWrapper = &phpQuery::$documents[$id];
        $this->document = &$this->documentWrapper->document;
        $this->xpath = &$this->documentWrapper->xpath;
        $this->charset = &$this->documentWrapper->charset;
        $this->documentFragment = &$this->documentWrapper->isDocumentFragment;
        // TODO check $this->DOM->documentElement;
        //		$this->root = $this->document->documentElement;
        $this->root = &$this->documentWrapper->root;
        //		$this->toRoot();
        $this->elements = [$this->root];
    }

    /**
     * @param $attr
     *
     * @return
     */
    public function __get($attr)
    {
        switch ($attr) {
            // FIXME doesnt work at all ?
            case 'length':
                return $this->size();
                break;
            default:
                return $this->$attr;
        }
    }

    /**
     * Saves actual object to $var by reference.
     * Useful when need to break chain.
     *
     * @param phpQueryObject $var
     *
     * @return phpQueryObject
     */
    public function toReference(&$var)
    {
        return $var = $this;
    }

    public function documentFragment($state = null)
    {
        if ($state) {
            phpQuery::$documents[$this->getDocumentID()]['documentFragment'] = $state;

            return $this;
        }

        return $this->documentFragment;
    }

    /**
     * @TODO documentWrapper
     */
    protected function isRoot($node)
    {
        //		return $node instanceof DOMDOCUMENT || $node->tagName == 'html';
        return $node instanceof DOMDOCUMENT
            || ($node instanceof DOMELEMENT && 'html' == $node->tagName)
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
     * Watch out, it doesn't creates new instance, can be reverted with end().
     *
     * @return phpQueryObject
     */
    public function toRoot()
    {
        $this->elements = [$this->root];

        return $this;
        //		return $this->newInstance(array($this->root));
    }

    /**
     * Saves object's DocumentID to $var by reference.
     * <code>
     * $myDocumentId;
     * phpQuery::newDocument('<div/>')
     *     ->getDocumentIDRef($myDocumentId)
     *     ->find('div')->...
     * </code>.
     *
     * @param $domId
     *
     * @return phpQueryObject
     *
     * @see phpQuery::newDocumentFile
     * @see phpQuery::newDocument
     */
    public function getDocumentIDRef(&$documentID)
    {
        $documentID = $this->getDocumentID();

        return $this;
    }

    /**
     * Returns object with stack set to document root.
     *
     * @return phpQueryObject
     */
    public function getDocument()
    {
        return phpQuery::getDocument($this->getDocumentID());
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
     * @return phpQueryObject
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
     * @return phpQueryObject
     */
    public function unloadDocument()
    {
        phpQuery::unloadDocuments($this->getDocumentID());
    }

    public function isHTML()
    {
        return $this->documentWrapper->isHTML;
    }

    public function isXHTML()
    {
        return $this->documentWrapper->isXHTML;
    }

    public function isXML()
    {
        return $this->documentWrapper->isXML;
    }

    /**
     * Enter description here...
     *
     * @see http://docs.jquery.com/Ajax/serialize
     *
     * @return string
     */
    public function serialize()
    {
        return phpQuery::param($this->serializeArray());
    }

    /**
     * Enter description here...
     *
     * @see http://docs.jquery.com/Ajax/serializeArray
     *
     * @return array
     */
    public function serializeArray($submit = null)
    {
        $source = $this->filter('form, input, select, textarea')
            ->find('input, select, textarea')
            ->andSelf()
            ->not('form');
        $return = [];
        //		$source->dumpDie();
        foreach ($source as $input) {
            $input = phpQuery::pq($input);
            if ($input->is('[disabled]')) {
                continue;
            }
            if (!$input->is('[name]')) {
                continue;
            }
            if ($input->is('[type=checkbox]') && !$input->is('[checked]')) {
                continue;
            }
            // jquery diff
            if ($submit && $input->is('[type=submit]')) {
                if ($submit instanceof DOMELEMENT && !$input->elements[0]->isSameNode($submit)) {
                    continue;
                } else {
                    if (is_string($submit) && $input->attr('name') != $submit) {
                        continue;
                    }
                }
            }
            $return[] = [
                'name' => $input->attr('name'),
                'value' => $input->val(),
            ];
        }

        return $return;
    }

    protected function debug($in)
    {
        if (!phpQuery::$debug) {
            return;
        }

        phpQuery::debug(json_encode($in));
        // print('<pre>');
        // print_r($in);
        // file debug
        //		file_put_contents(dirname(__FILE__).'/phpQuery.log', print_r($in, true)."\n", FILE_APPEND);
        // quite handy debug trace
        //		if ( is_array($in))
        //			print_r(array_slice(debug_backtrace(), 3));
        // print("</pre>\n");
    }

    protected function isRegexp($pattern)
    {
        return in_array(
            $pattern[mb_strlen($pattern) - 1],
            ['^', '*', '$']
        );
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
        return extension_loaded('mbstring') && phpQuery::$mbstringSupport
            ? mb_eregi('\w', $char)
            : preg_match('@\w@', $char);
    }

    protected function parseSelector($query)
    {
        // clean spaces
        // TODO include this inside parsing ?
        $query = trim(
            preg_replace(
                '@\s+@',
                ' ',
                preg_replace('@\s*(>|\\+|~)\s*@', '\\1', $query)
            )
        );
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
                while (
                    isset($query[$i])
                    && ($this->isChar($query[$i]) || in_array($query[$i], $tagChars))
                ) {
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
                    $return[$k] = phpQuery::callbackRun($callback, [$v]);
                }
            } else {
                $return = phpQuery::callbackRun($callback, [$return]);
            }
        }

        return $return;
    }

    /**
     * Return matched DOM nodes.
     * jQuery difference.
     *
     * @param int $index
     *
     * @return array|string Returns string if $index != null
     *
     * @todo implement callbacks
     * @todo return only arrays ?
     * @todo maybe other name...
     */
    public function getString($index = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if (!is_null($index) && is_int($index)) {
            $return = $this->eq($index)->text();
        } else {
            $return = [];
            for ($i = 0; $i < $this->size(); ++$i) {
                $return[] = $this->eq($i)->text();
            }
        }
        // pass thou callbacks
        $args = func_get_args();
        $args = array_slice($args, 1);
        foreach ($args as $callback) {
            $return = phpQuery::callbackRun($callback, [$return]);
        }

        return $return;
    }

    /**
     * Return matched DOM nodes.
     * jQuery difference.
     *
     * @param int $index
     *
     * @return array|string Returns string if $index != null
     *
     * @todo implement callbacks
     * @todo return only arrays ?
     * @todo maybe other name...
     */
    public function getStrings($index = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        if (!is_null($index) && is_int($index)) {
            $return = $this->eq($index)->text();
        } else {
            $return = [];
            for ($i = 0; $i < $this->size(); ++$i) {
                $return[] = $this->eq($i)->text();
            }
            // pass thou callbacks
            $args = func_get_args();
            $args = array_slice($args, 1);
        }
        foreach ($args as $callback) {
            if (is_array($return)) {
                foreach ($return as $k => $v) {
                    $return[$k] = phpQuery::callbackRun($callback, [$v]);
                }
            } else {
                $return = phpQuery::callbackRun($callback, [$return]);
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
        $class = get_class($this);
        // support inheritance by passing old object to overloaded constructor
        $new = 'phpQuery' != $class
            ? new $class($this, $this->getDocumentID())
            : new phpQueryObject($this->getDocumentID());
        $new->previous = $this;
        if (is_null($newStack)) {
            $new->elements = $this->elements;
            if ($this->elementsBackup) {
                $this->elements = $this->elementsBackup;
            }
        } else {
            if (is_string($newStack)) {
                $new->elements = phpQuery::pq($newStack, $this->getDocumentID())->stack();
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
        //		var_dump($this->elements[0]->nodeType);
        // element, document
        foreach ($this->stack([1, 9, 13]) as $k => $stackNode) {
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
                $testNode = isset($testNode->parentNode)
                    ? $testNode->parentNode
                    : null;
            }
            // XXX tmp ?
            $xpath = $this->documentWrapper->isXHTML
                ? $this->getNodeXpath($stackNode, 'html')
                : $this->getNodeXpath($stackNode);
            // FIXME pseudoclasses-only query, support XML
            $query = '//' == $XQuery && '/html[1]' == $xpath
                ? '//*'
                : $xpath.$XQuery;
            $this->debug("XPATH: {$query}");
            // run query, get elements
            $nodes = $this->xpath->query($query);
            $this->debug('QUERY FETCHED');
            if (!$nodes->length) {
                $this->debug('Nothing found');
            }
            $debug = [];
            foreach ($nodes as $node) {
                $matched = false;
                if ($compare) {
                    phpQuery::$debug ?
                        $this->debug('Found: '.$this->whois($node).", comparing with {$compare}()")
                        : null;
                    $phpQueryDebug = phpQuery::$debug;
                    phpQuery::$debug = false;
                    // TODO ??? use phpQuery::callbackRun()
                    if (call_user_func_array([$this, $compare], [$selector, $node])) {
                        $matched = true;
                    }
                    phpQuery::$debug = $phpQueryDebug;
                } else {
                    $matched = true;
                }
                if ($matched) {
                    if (phpQuery::$debug) {
                        $debug[] = $this->whois($node);
                    }
                    $stack[] = $node;
                }
            }
            if (phpQuery::$debug) {
                $this->debug('Matched '.count($debug).': '.implode(', ', $debug));
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
     * @return phpQueryObject
     */
    public function find($selectors, $context = null, $noHistory = false)
    {
        if (!$noHistory) { // backup last stack /for end()/
            $this->elementsBackup = $this->elements;
        }
        // allow to define context
        // TODO combine code below with phpQuery::pq() context guessing code
        //   as generic function
        if ($context) {
            if (!is_array($context) && $context instanceof DOMELEMENT) {
                $this->elements = [$context];
            } else {
                if (is_array($context)) {
                    $this->elements = [];
                    foreach ($context as $c) {
                        if ($c instanceof DOMELEMENT) {
                            $this->elements[] = $c;
                        }
                    }
                } else {
                    if ($context instanceof self) {
                        $this->elements = $context->elements;
                    }
                }
            }
        }
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
                $isTag = extension_loaded('mbstring') && phpQuery::$mbstringSupport
                    ? mb_ereg_match('^[\w|\||-]+$', $s) || '*' == $s
                    : preg_match('@^[\w|\||-]+$@', $s) || '*' == $s;
                if ($isTag) {
                    if ($this->isXML()) {
                        // namespace support
                        if (false !== \mb_strpos($s, '|')) {
                            $ns = $tag = null;
                            list($ns, $tag) = explode('|', $s);
                            $XQuery .= "$ns:$tag";
                        } else {
                            if ('*' == $s) {
                                $XQuery .= '*';
                            } else {
                                $XQuery .= "*[local-name()='$s']";
                            }
                        }
                    } else {
                        $XQuery .= $s;
                    }
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
                                            while ($test && !($test instanceof DOMELEMENT)) {
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
                                                    phpQuery::debug("Unrecognized token '$s'");
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
                $this->elements = phpQuery::merge(
                    $this->map(
                        [$this, 'is'],
                        "input[type=$class]",
                        new CallbackParam()
                    ),
                    $this->map(
                        [$this, 'is'],
                        "button[type=$class]",
                        new CallbackParam()
                    )
                );
                break;
            //				$stack = array();
            //				foreach($this->elements as $node)
            //					if ($node->is('input[type=submit]') || $node->is('button[type=submit]'))
            //						$stack[] = $el;
            //				$this->elements = $stack;
            case 'input':
                $this->elements = $this->map(
                    [$this, 'is'],
                    'input',
                    new CallbackParam()
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
                    new CallbackParam()
                )->elements;
                break;
            case 'parent':
                $this->elements = $this->map(
                    function ($node) {
                        return $node instanceof DOMELEMENT && $node->childNodes->length
                            ? $node : null;
                    }
                )->elements;
                break;
            case 'empty':
                $this->elements = $this->map(
                    function ($node) {
                        return $node instanceof DOMELEMENT && $node->childNodes->length
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
                    new CallbackParam()
                )->elements;
                break;
            case 'enabled':
                $this->elements = $this->map(
                    function ($node) {
                        return phpQuery::pq($node)->not(':disabled') ? $node : null;
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
                        return 0 == phpQuery::pq($node)->siblings()->size() ? $node : null;
                    }
                )->elements;
                break;
            case 'first-child':
                $this->elements = $this->map(
                    function ($node) {
                        return 0 == phpQuery::pq($node)->prevAll()->size() ? $node : null;
                    }
                )->elements;
                break;
            case 'last-child':
                $this->elements = $this->map(
                    function ($node) {
                        return 0 == phpQuery::pq($node)->nextAll()->size() ? $node : null;
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
                            $index = phpQuery::pq($node)->prevAll()->size() + 1;
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
                        new CallbackParam(),
                        $param
                    );
                } else {
                    if (mb_strlen($param) > 1 && 1 === preg_match('/^(\d*)n([-+]?)(\d*)/', $param)) { // an+b
                        $mapped = $this->map(
                            function ($node, $param) {
                                $prevs = phpQuery::pq($node)->prevAll()->size();
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
                                    phpQuery::debug($a.'*'.floor($index / $a)."+$b-1 == ".($a * floor($index / $a) + $b - 1)." ?= $prevs");

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
                            new CallbackParam(),
                            $param
                        );
                    } else { // index
                        $mapped = $this->map(
                            function ($node, $index) {
                                $prevs = phpQuery::pq($node)->prevAll()->size();
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
                            new CallbackParam(),
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

    protected function __pseudoClassParam($paramsString)
    {
        // TODO;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function is($selector, $nodes = null)
    {
        phpQuery::debug(['Is:', $selector]);
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
     * @return phpQueryObject
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
            $result = phpQuery::callbackRun($callback, [$index, $node]);
            if (is_null($result) || (!is_null($result) && $result)) {
                $newStack[] = $node;
            }
        }
        $this->elements = $newStack;

        return $_skipHistory
            ? $this
            : $this->newInstance();
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
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
                    if (!($node instanceof DOMELEMENT)) {
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
                                                $val = extension_loaded('mbstring') && phpQuery::$mbstringSupport
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
                                                $isMatch = extension_loaded('mbstring') && phpQuery::$mbstringSupport
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
        return '\'' == $value[0] || '"' == $value[0]
            ? substr($value, 1, -1)
            : $value;
    }

    /**
     * Enter description here...
     *
     * @see http://docs.jquery.com/Ajax/load
     *
     * @return phpQuery
     *
     * @todo Support $selector
     */
    public function load($url, $data = null, $callback = null)
    {
        if ($data && !is_array($data)) {
            $callback = $data;
            $data = null;
        }
        if (false !== \mb_strpos($url, ' ')) {
            $matches = null;
            if (extension_loaded('mbstring') && phpQuery::$mbstringSupport) {
                mb_ereg('^([^ ]+) (.*)$', $url, $matches);
            } else {
                preg_match('^([^ ]+) (.*)$', $url, $matches);
            }
            $url = $matches[1];
            $selector = $matches[2];
            // FIXME this sucks, pass as callback param
            $this->_loadSelector = $selector;
        }
        $ajax = [
            'url' => $url,
            'type' => $data ? 'POST' : 'GET',
            'data' => $data,
            'complete' => $callback,
            'success' => [$this, '__loadSuccess'],
        ];
        phpQuery::ajax($ajax);

        return $this;
    }

    /**
     * @param $html
     *
     * @return
     */
    public function __loadSuccess($html)
    {
        if ($this->_loadSelector) {
            $html = phpQuery::newDocument($html)->find($this->_loadSelector);
            unset($this->_loadSelector);
        }
        foreach ($this->stack(1) as $node) {
            phpQuery::pq($node, $this->getDocumentID())
                ->markup($html);
        }
    }

    /**
     * Enter description here...
     *
     * @return phpQuery
     *
     * @todo
     */
    public function css()
    {
        // TODO
        return $this;
    }

    /**
     * @todo
     */
    public function show()
    {
        // TODO
        return $this;
    }

    /**
     * @todo
     */
    public function hide()
    {
        // TODO
        return $this;
    }

    /**
     * Trigger a type of event on every matched element.
     *
     * @param $type
     * @param $data
     *
     * @return phpQueryObject
     * @TODO support more than event in $type (space-separated)
     */
    public function trigger($type, $data = [])
    {
        foreach ($this->elements as $node) {
            phpQueryEvents::trigger($this->getDocumentID(), $type, $data, $node);
        }

        return $this;
    }

    /**
     * This particular method triggers all bound event handlers on an element (for a specific event type) WITHOUT executing the browsers default actions.
     *
     * @param $type
     * @param $data
     *
     * @return phpQueryObject
     * @TODO
     */
    public function triggerHandler($type, $data = [])
    {
        // TODO;
    }

    /**
     * Binds a handler to one or more events (like click) for each matched element.
     * Can also bind custom events.
     *
     * @param $type
     * @param $data     Optional
     * @param $callback
     *
     * @return phpQueryObject
     * @TODO support '!' (exclusive) events
     * @TODO support more than event in $type (space-separated)
     */
    public function bind($type, $data, $callback = null)
    {
        // TODO check if $data is callable, not using is_callable
        if (!isset($callback)) {
            $callback = $data;
            $data = null;
        }
        foreach ($this->elements as $node) {
            phpQueryEvents::add($this->getDocumentID(), $node, $type, $data, $callback);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @param $type
     * @param $callback
     *
     * @return unknown
     * @TODO namespace events
     * @TODO support more than event in $type (space-separated)
     */
    public function unbind($type = null, $callback = null)
    {
        foreach ($this->elements as $node) {
            phpQueryEvents::remove($this->getDocumentID(), $node, $type, $callback);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function change($callback = null)
    {
        if ($callback) {
            return $this->bind('change', $callback);
        }

        return $this->trigger('change');
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function submit($callback = null)
    {
        if ($callback) {
            return $this->bind('submit', $callback);
        }

        return $this->trigger('submit');
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function click($callback = null)
    {
        if ($callback) {
            return $this->bind('click', $callback);
        }

        return $this->trigger('click');
    }

    /**
     * Enter description here...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrapAllOld($wrapper)
    {
        $wrapper = phpQuery::pq($wrapper)->_clone();
        if (!$wrapper->length() || !$this->length()) {
            return $this;
        }
        $wrapper->insertBefore($this->elements[0]);
        $deepest = $wrapper->elements[0];
        while ($deepest->firstChild && $deepest->firstChild instanceof DOMELEMENT) {
            $deepest = $deepest->firstChild;
        }
        phpQuery::pq($deepest)->append($this);

        return $this;
    }

    /**
     * Enter description here...
     *
     * TODO testme...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrapAll($wrapper)
    {
        if (!$this->length()) {
            return $this;
        }

        return phpQuery::pq($wrapper, $this->getDocumentID())
            ->clone()
            ->insertBefore($this->get(0))
            ->map([$this, '___wrapAllCallback'])
            ->append($this);
    }

    /**
     * @param $node
     *
     * @return
     */
    public function ___wrapAllCallback($node)
    {
        $deepest = $node;
        while ($deepest->firstChild && $deepest->firstChild instanceof DOMELEMENT) {
            $deepest = $deepest->firstChild;
        }

        return $deepest;
    }

    /**
     * Enter description here...
     * NON JQUERY METHOD.
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrapAllPHP($codeBefore, $codeAfter)
    {
        return $this
            ->slice(0, 1)
            ->beforePHP($codeBefore)
            ->end()
            ->slice(-1)
            ->afterPHP($codeAfter)
            ->end();
    }

    /**
     * Enter description here...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrap($wrapper)
    {
        foreach ($this->stack() as $node) {
            phpQuery::pq($node, $this->getDocumentID())->wrapAll($wrapper);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrapPHP($codeBefore, $codeAfter)
    {
        foreach ($this->stack() as $node) {
            phpQuery::pq($node, $this->getDocumentID())->wrapAllPHP($codeBefore, $codeAfter);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrapInner($wrapper)
    {
        foreach ($this->stack() as $node) {
            phpQuery::pq($node, $this->getDocumentID())->contents()->wrapAll($wrapper);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function wrapInnerPHP($codeBefore, $codeAfter)
    {
        foreach ($this->stack(1) as $node) {
            phpQuery::pq($node, $this->getDocumentID())->contents()
                ->wrapAllPHP($codeBefore, $codeAfter);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     * @testme Support for text nodes
     */
    public function contents()
    {
        $stack = [];
        foreach ($this->stack(1) as $el) {
            // FIXME (fixed) http://code.google.com/p/phpquery/issues/detail?id=56
            //			if (! isset($el->childNodes))
            //				continue;
            foreach ($el->childNodes as $node) {
                $stack[] = $node;
            }
        }

        return $this->newInstance($stack);
    }

    /**
     * Enter description here...
     *
     * jQuery difference.
     *
     * @return phpQueryObject
     */
    public function contentsUnwrap()
    {
        foreach ($this->stack(1) as $node) {
            if (!$node->parentNode) {
                continue;
            }
            $childNodes = [];
            // any modification in DOM tree breaks childNodes iteration, so cache them first
            foreach ($node->childNodes as $chNode) {
                $childNodes[] = $chNode;
            }
            foreach ($childNodes as $chNode) { //				$node->parentNode->appendChild($chNode);
                $node->parentNode->insertBefore($chNode, $node);
            }
            $node->parentNode->removeChild($node);
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * jQuery difference.
     */
    public function switchWith($markup)
    {
        $markup = phpQuery::pq($markup, $this->getDocumentID());
        $content = null;
        foreach ($this->stack(1) as $node) {
            phpQuery::pq($node)
                ->contents()->toReference($content)->end()
                ->replaceWith($markup->clone()->append($content));
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
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
     * @return phpQueryObject
     */
    public function size()
    {
        return count($this->elements);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     *
     * @deprecated Use length as attribute
     */
    public function length()
    {
        return $this->size();
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->size();
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     *
     * @todo $level
     */
    public function end($level = 1)
    {
        //		$this->elements = array_pop( $this->history );
        //		return $this;
        //		$this->previous->DOM = $this->DOM;
        //		$this->previous->XPath = $this->XPath;
        return $this->previous
            ? $this->previous
            : $this;
    }

    /**
     * Enter description here...
     * Normal use ->clone() .
     *
     * @return phpQueryObject
     */
    public function _clone()
    {
        $newStack = [];
        // pr(array('copy... ', $this->whois()));
        // $this->dumpHistory('copy');
        $this->elementsBackup = $this->elements;
        foreach ($this->elements as $node) {
            $newStack[] = $node->cloneNode(true);
        }
        $this->elements = $newStack;

        return $this->newInstance();
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function replaceWithPHP($code)
    {
        return $this->replaceWith(phpQuery::php($code));
    }

    /**
     * Enter description here...
     *
     * @param string|phpQuery $content
     *
     * @return phpQueryObject
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
     * @param string $selector
     *
     * @return phpQueryObject
     *
     * @todo this works ?
     */
    public function replaceAll($selector)
    {
        foreach (phpQuery::pq($selector, $this->getDocumentID()) as $node) {
            phpQuery::pq($node, $this->getDocumentID())
                ->after($this->_clone())
                ->remove();
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
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
            phpQueryEvents::trigger(
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
            phpQueryEvents::trigger(
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
        if ($this->documentWrapper->isXML) {
            return call_user_func_array([$this, 'xml'], $args);
        } else {
            return call_user_func_array([$this, 'html'], $args);
        }
    }

    /**
     * jQuey difference.
     *
     * @param $markup
     *
     * @return
     */
    public function markupOuter($callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();
        if ($this->documentWrapper->isXML) {
            return call_user_func_array([$this, 'xmlOuter'], $args);
        } else {
            return call_user_func_array([$this, 'htmlOuter'], $args);
        }
    }

    /**
     * Enter description here...
     *
     * @param $html
     *
     * @return string|phpQuery
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
                if (($this->isXHTML() || $this->isHTML()) && 'textarea' == $node->tagName) {
                    $oldHtml = phpQuery::pq($node, $this->getDocumentID())->markup();
                }
                foreach ($nodes as $newNode) {
                    $node->appendChild(
                        $alreadyAdded
                            ? $newNode->cloneNode(true)
                            : $newNode
                    );
                }
                // for now, limit events for textarea
                if (($this->isXHTML() || $this->isHTML()) && 'textarea' == $node->tagName) {
                    $this->markupEvents($html, $oldHtml, $node);
                }
            }

            return $this;
        } else {
            // FETCH
            $return = $this->documentWrapper->markup($this->elements, true);
            $args = func_get_args();
            foreach (array_slice($args, 1) as $callback) {
                $return = phpQuery::callbackRun($callback, [$return]);
            }

            return $return;
        }
    }

    /**
     * @TODO force xml result
     */
    public function xml($xml = null, $callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();

        return call_user_func_array([$this, 'html'], $args);
    }

    /**
     * Enter description here...
     *
     * @TODO force html result
     *
     * @return string
     */
    public function htmlOuter($callback1 = null, $callback2 = null, $callback3 = null)
    {
        $markup = $this->documentWrapper->markup($this->elements);
        // pass thou callbacks
        $args = func_get_args();
        foreach ($args as $callback) {
            $markup = phpQuery::callbackRun($callback, [$markup]);
        }

        return $markup;
    }

    /**
     * @TODO force xml result
     */
    public function xmlOuter($callback1 = null, $callback2 = null, $callback3 = null)
    {
        $args = func_get_args();

        return call_user_func_array([$this, 'htmlOuter'], $args);
    }

    public function __toString()
    {
        return $this->markupOuter();
    }

    /**
     * Just like html(), but returns markup with VALID (dangerous) PHP tags.
     *
     * @return phpQueryObject
     *
     * @todo support returning markup with PHP tags when called without param
     */
    public function php($code = null)
    {
        return $this->markupPHP($code);
    }

    /**
     * Enter description here...
     *
     * @param $code
     *
     * @return
     */
    public function markupPHP($code = null)
    {
        return isset($code)
            ? $this->markup(phpQuery::php($code))
            : phpQuery::markupToPHP($this->markup());
    }

    /**
     * Enter description here...
     *
     * @param $code
     *
     * @return
     */
    public function markupOuterPHP()
    {
        return phpQuery::markupToPHP($this->markupOuter());
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
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
     * @return phpQueryObject
     */
    public function ancestors($selector = null)
    {
        return $this->children($selector);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function append($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function appendPHP($content)
    {
        return $this->insert("<php><!-- {$content} --></php>", 'append');
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function appendTo($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function prepend($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     *
     * @todo accept many arguments, which are joined, arrays maybe also
     */
    public function prependPHP($content)
    {
        return $this->insert("<php><!-- {$content} --></php>", 'prepend');
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function prependTo($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function before($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function beforePHP($content)
    {
        return $this->insert("<php><!-- {$content} --></php>", 'before');
    }

    /**
     * Enter description here...
     *
     * @param  string|phpQuery
     *
     * @return phpQueryObject
     */
    public function insertBefore($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function after($content)
    {
        return $this->insert($content, __FUNCTION__);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function afterPHP($content)
    {
        return $this->insert("<php><!-- {$content} --></php>", 'after');
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function insertAfter($seletor)
    {
        return $this->insert($seletor, __FUNCTION__);
    }

    /**
     * Internal insert method. Don't use it.
     *
     * @param $target
     * @param $type
     *
     * @return phpQueryObject
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
                    if (phpQuery::isMarkup($target)) {
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
        phpQuery::debug('From '.count($insertFrom).'; To '.count($insertTo).' nodes');
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
                phpQueryEvents::trigger(
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
        $subject = $subject instanceof phpQueryObject
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
     * @return phpQueryObject
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
     * @return phpQueryObject
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
                $text = phpQuery::callbackRun($callback, [$text]);
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
     * @return phpQueryObject
     */
    public function plugin($class, $file = null)
    {
        phpQuery::plugin($class, $file);

        return $this;
    }

    /**
     * Deprecated, use $pq->plugin() instead.
     *
     * @param $class
     * @param $file
     *
     * @return
     *
     * @deprecated
     */
//    public static function extend($class, $file = null)
//    {
//        return $this->plugin($class, $file);
//    }

    /**
     * @param $method
     * @param $args
     *
     * @return
     */
    public function __call($method, $args)
    {
        $aliasMethods = ['clone', 'empty'];
        if (isset(phpQuery::$extendMethods[$method])) {
            array_unshift($args, $this);

            return phpQuery::callbackRun(
                phpQuery::$extendMethods[$method],
                $args
            );
        } else {
            if (isset(phpQuery::$pluginsMethods[$method])) {
                array_unshift($args, $this);
                $class = phpQuery::$pluginsMethods[$method];
                $realClass = "phpQueryObjectPlugin_$class";
                $return = call_user_func_array(
                    [$realClass, $method],
                    $args
                );

                // XXX deprecate ?
                return is_null($return)
                    ? $this
                    : $return;
            } else {
                if (in_array($method, $aliasMethods)) {
                    return call_user_func_array([$this, '_'.$method], $args);
                } else {
                    throw new Exception("Method '{$method}' doesnt exist");
                }
            }
        }
    }

    /**
     * Safe rename of next().
     *
     * Use it ONLY when need to call next() on an iterated object (in same time).
     * Normaly there is no need to do such thing ;)
     *
     * @return phpQueryObject
     */
    public function _next($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('nextSibling', $selector, true)
        );
    }

    /**
     * Use prev() and next().
     *
     * @return phpQueryObject
     *
     * @deprecated
     */
    public function _prev($selector = null)
    {
        return $this->prev($selector);
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
     */
    public function prev($selector = null)
    {
        return $this->newInstance(
            $this->getElementSiblings('previousSibling', $selector, true)
        );
    }

    /**
     * @return phpQueryObject
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
     * @return phpQueryObject
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
                if (!$test instanceof DOMELEMENT) {
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
     * @return phpQueryObject
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
     * @return phpQueryObject
     */
    public function not($selector = null)
    {
        if (is_string($selector)) {
            phpQuery::debug(['not', $selector]);
        } else {
            phpQuery::debug('not');
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
     * @param  string|phpQueryObject
     *
     * @return phpQueryObject
     */
    public function add($selector = null)
    {
        if (!$selector) {
            return $this;
        }
        $stack = [];
        $this->elementsBackup = $this->elements;
        $found = phpQuery::pq($selector, $this->getDocumentID());
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
        $loop = !is_null($elementsStack)
            ? $elementsStack
            : $this->elements;
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
     * @return phpQueryObject
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
     * @return phpQueryObject
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
        if (!$this->isXHTML() && !$this->isHTML()) {
            return;
        }
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
            phpQueryEvents::trigger(
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
                    return $node->hasAttribute($attr)
                        ? $node->getAttribute($attr)
                        : null;
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
     * @return phpQueryObject
     *
     * @todo check CDATA ???
     */
    public function attrPHP($attr, $code)
    {
        if (!is_null($code)) {
            $value = '<'.'?php '.$code.' ?'.'>';
            // TODO tempolary solution
            // http://code.google.com/p/phpquery/issues/detail?id=17
            //			if (function_exists('mb_detect_encoding') && mb_detect_encoding($value) == 'ASCII')
            //				$value	= mb_convert_encoding($value, 'UTF-8', 'HTML-ENTITIES');
        }
        foreach ($this->stack(1) as $node) {
            if (!is_null($code)) {
                //				$attrNode = $this->DOM->createAttribute($attr);
                $node->setAttribute($attr, $value);
            //				$attrNode->value = $value;
                //				$node->appendChild($attrNode);
            } else {
                if ('*' == $attr) {
                    // jQuery diff
                    $return = [];
                    foreach ($node->attributes as $n => $v) {
                        $return[$n] = $v->value;
                    }

                    return $return;
                } else {
                    return $node->getAttribute($attr);
                }
            }
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
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
                $node = phpQuery::pq($node, $this->getDocumentID());
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
                            $option = phpQuery::pq($option, $this->getDocumentID());
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
     * @return phpQueryObject
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
     * @return phpQueryObject
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
     * @return phpQueryObject
     */
    public function addClassPHP($className)
    {
        foreach ($this->stack(1) as $node) {
            $classes = $node->getAttribute('class');
            $newValue = $classes
                ? $classes.' <'.'?php '.$className.' ?'.'>'
                : '<'.'?php '.$className.' ?'.'>';
            $node->setAttribute('class', $newValue);
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
     * @return phpQueryObject
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
     * @return phpQueryObject
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
     * @return phpQueryObject
     */
    public function _empty()
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
     * @return phpQueryObject
     */
    public function each($callback, $param1 = null, $param2 = null, $param3 = null)
    {
        $paramStructure = null;
        if (func_num_args() > 1) {
            $paramStructure = func_get_args();
            $paramStructure = array_slice($paramStructure, 1);
        }
        foreach ($this->elements as $v) {
            phpQuery::callbackRun($callback, [$v], $paramStructure);
        }

        return $this;
    }

    /**
     * Run callback on actual object.
     *
     * @return phpQueryObject
     */
    public function callback($callback, $param1 = null, $param2 = null, $param3 = null)
    {
        $params = func_get_args();
        $params[0] = $this;
        phpQuery::callbackRun($callback, $params);

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return phpQueryObject
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

        return $this->newInstance(
            call_user_func_array(['phpQuery', 'map'], $params)
        //			phpQuery::map($this->elements, $callback)
        );
    }

    /**
     * Enter description here...
     *
     * @param  <type>  $key
     * @param  <type>  $value
     */
    public function data($key, $value = null)
    {
        if (!isset($value)) {
            // TODO? implement specific jQuery behavior od returning parent values
            // is child which we look up doesn't exist
            return phpQuery::data($this->get(0), $key, $value, $this->getDocumentID());
        } else {
            foreach ($this as $node) {
                phpQuery::data($node, $key, $value, $this->getDocumentID());
            }

            return $this;
        }
    }

    /**
     * Enter description here...
     *
     * @param  <type>  $key
     */
    public function removeData($key)
    {
        foreach ($this as $node) {
            phpQuery::removeData($node, $key, $this->getDocumentID());
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
     * @return phpQueryObject
     *
     * @see phpQueryObject::_next()
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
                return $this->_next($cssSelector);
            }
        }
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->valid;
    }

    // ITERATOR INTERFACE END
    // ARRAYACCESS INTERFACE

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->find($offset)->size() > 0;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->find($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        //		$this->find($offset)->replaceWith($value);
        $this->find($offset)->html($value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        // empty
        throw new Exception("Can't do unset, use array interface only for calling queries and replacing HTML.");
    }
    // ARRAYACCESS INTERFACE END

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
        $loop = $oneNode
            ? [$oneNode]
            : $this->elements;
        //		if ($namespace)
        //			$namespace .= ':';
        foreach ($loop as $node) {
            if ($node instanceof DOMDOCUMENT) {
                $return[] = '';
                continue;
            }
            $xpath = [];
            while (!($node instanceof DOMDOCUMENT)) {
                $i = 1;
                $sibling = $node;
                while ($sibling->previousSibling) {
                    $sibling = $sibling->previousSibling;
                    $isElement = $sibling instanceof DOMELEMENT;
                    if ($isElement && $sibling->tagName == $node->tagName) {
                        ++$i;
                    }
                }
                $xpath[] = $this->isXML()
                    ? "*[local-name()='{$node->tagName}'][{$i}]"
                    : "{$node->tagName}[{$i}]";
                $node = $node->parentNode;
            }
            $xpath = implode('/', array_reverse($xpath));
            $return[] = '/'.$xpath;
        }

        return $oneNode
            ? $return[0]
            : $return;
    }

    // HELPERS
    public function whois($oneNode = null)
    {
        $return = [];
        $loop = $oneNode
            ? [$oneNode]
            : $this->elements;
        foreach ($loop as $node) {
            if (isset($node->tagName)) {
                $tag = in_array($node->tagName, ['php', 'js'])
                    ? strtoupper($node->tagName)
                    : $node->tagName;
                $return[] = $tag
                    .($node->getAttribute('id')
                        ? '#'.$node->getAttribute('id') : '')
                    .($node->getAttribute('class')
                        ? '.'.implode('.', explode(' ', $node->getAttribute('class'))) : '')
                    .($node->getAttribute('name')
                        ? '[name="'.$node->getAttribute('name').'"]' : '')
                    .($node->getAttribute('value') && false === strpos($node->getAttribute('value'), '<'.'?php')
                        ? '[value="'.substr(str_replace("\n", '', $node->getAttribute('value')), 0, 15).'"]' : '')
                    .($node->getAttribute('value') && false !== strpos($node->getAttribute('value'), '<'.'?php')
                        ? '[value=PHP]' : '')
                    .($node->getAttribute('selected')
                        ? '[selected]' : '')
                    .($node->getAttribute('checked')
                        ? '[checked]' : '');
            } else {
                if ($node instanceof DOMTEXT) {
                    if (trim($node->textContent)) {
                        $return[] = 'Text:'.substr(str_replace("\n", ' ', $node->textContent), 0, 15);
                    }
                } else {
                }
            }
        }

        return $oneNode && isset($return[0])
            ? $return[0]
            : $return;
    }

    /**
     * Dump htmlOuter and preserve chain. Usefull for debugging.
     *
     * @return phpQueryObject
     */
    public function dump()
    {
        echo 'DUMP #'.(phpQuery::$dumpCount++).' ';
//        $debug = phpQuery::$debug;
//        phpQuery::$debug = false;
        //		print __FILE__.':'.__LINE__."\n";
        phpQuery::debug($this->htmlOuter());

        return $this;
    }

    public function dumpWhois()
    {
        echo 'DUMP #'.(phpQuery::$dumpCount++).' ';
//        $debug = phpQuery::$debug;
//        phpQuery::$debug = false;
        //		print __FILE__.':'.__LINE__."\n";
        // var_dump('whois', $this->whois());

        phpQuery::debug($this->whois());

        // phpQuery::$debug = $debug;

        return $this;
    }

    public function dumpLength()
    {
        echo 'DUMP #'.(phpQuery::$dumpCount++).' ';
//        $debug = phpQuery::$debug;
//        phpQuery::$debug = false;
        //		print __FILE__.':'.__LINE__."\n";
//        var_dump('length', $this->length());
//        phpQuery::$debug = $debug;

        phpQuery::debug($this->length());

        return $this;
    }

    public function dumpTree($html = true, $title = true)
    {
        $output = $title
            ? 'DUMP #'.(phpQuery::$dumpCount++)." \n" : '';
        $debug = phpQuery::$debug;
        phpQuery::$debug = false;
        foreach ($this->stack() as $node) {
            $output .= $this->__dumpTree($node);
        }
        phpQuery::$debug = $debug;
        echo $html
            ? nl2br(str_replace(' ', '&nbsp;', $output))
            : $output;

        return $this;
    }

    private function __dumpTree($node, $intend = 0)
    {
        $whois = $this->whois($node);
        $return = '';
        if ($whois) {
            $return .= str_repeat(' - ', $intend).$whois."\n";
        }
        if (isset($node->childNodes)) {
            foreach ($node->childNodes as $chNode) {
                $return .= $this->__dumpTree($chNode, $intend + 1);
            }
        }

        return $return;
    }

    /**
     * Dump htmlOuter and stop script execution. Usefull for debugging.
     */
    public function dumpDie()
    {
//        print __FILE__.':'.__LINE__;

//        var_dump($this->htmlOuter());

        exit();
    }
}
