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

use DOMDocument;
use DOMNode;
use DOMNodeList;
use Exception;
use Jiannei\LaravelCrawler\Support\Dom\DOMDocumentWrapper;

/**
 * Static namespace for phpQuery functions.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 */
abstract class Dom
{
    /**
     * XXX: Workaround for mbstring problems.
     *
     * @var bool
     */
    public static $debug = false;
    public static $documents = [];
    public static $defaultDocumentID = null;

    /**
     * Multi-purpose function.
     * Use pq() as shortcut.
     *
     * In below examples, $pq is any result of pq(); function.
     *
     * 1. Import markup into existing document (without any attaching):
     * - Import into selected document:
     *   pq('<div/>')                // DOESNT accept text nodes at beginning of input string !
     * - Import into document with ID from $pq->getDocumentID():
     *   pq('<div/>', $pq->getDocumentID())
     * - Import into same document as DOMNode belongs to:
     *   pq('<div/>', DOMNode)
     * - Import into document from phpQuery object:
     *   pq('<div/>', $pq)
     *
     * 2. Run query:
     * - Run query on last selected document:
     *   pq('div.myClass')
     * - Run query on document with ID from $pq->getDocumentID():
     *   pq('div.myClass', $pq->getDocumentID())
     * - Run query on same document as DOMNode belongs to and use node(s)as root for query:
     *   pq('div.myClass', DOMNode)
     * - Run query on document from phpQuery object
     *   and use object's stack as root node(s) for query:
     *   pq('div.myClass', $pq)
     *
     * @param string|DOMNode|DOMNodeList|array $arg1    HTML markup, CSS Selector, DOMNode or array of DOMNodes
     * @param string|Parser|DOMNode            $context DOM ID from $pq->getDocumentID(), phpQuery object (determines also query root) or DOMNode (determines also query root)
     *
     * @return Parser|false
     *                      phpQuery object or false in case of error
     */
    public static function pq($arg1, $context = null)
    {
        if ($arg1 instanceof DOMNode && !isset($context)) {
            foreach (self::$documents as $documentWrapper) {
                $compare = $arg1 instanceof DOMDocument ? $arg1 : $arg1->ownerDocument;
                if ($documentWrapper->document->isSameNode($compare)) {
                    $context = $documentWrapper->id;
                }
            }
        }

        if (!$context) {
            $domId = self::$defaultDocumentID;
            if (!$domId) {
                throw new Exception("Can't use last created DOM, because there isn't any. Use phpQuery::newDocument() first.");
            }
        } else {
            if ($context instanceof Parser) {
                $domId = $context->getDocumentID();
            } else {
                if ($context instanceof DOMDocument) {
                    $domId = self::getDocumentID($context);
                    if (!$domId) {
                        $domId = self::newDocument($context)->getDocumentID();
                    }
                } else {
                    if ($context instanceof DOMNode) {
                        $domId = self::getDocumentID($context);
                        if (!$domId) {
                            throw new Exception('Orphaned DOMNode');
                        }
                    } else {
                        $domId = $context;
                    }
                }
            }
        }

        if ($arg1 instanceof Parser) {
            /**
             * Return $arg1 or import $arg1 stack if document differs:
             * pq(pq('<div/>')).
             */
            if ($arg1->getDocumentID() == $domId) {
                return $arg1;
            }
            $class = get_class($arg1);
            // support inheritance by passing old object to overloaded constructor
            $phpQuery = 'phpQuery' != $class ? new $class($arg1, $domId) : new Parser($domId);
            $phpQuery->elements = [];
            foreach ($arg1->elements as $node) {
                $phpQuery->elements[] = $phpQuery->document->importNode($node, true);
            }

            return $phpQuery;
        }

        if ($arg1 instanceof DOMNode || (is_array($arg1) && isset($arg1[0]) && $arg1[0] instanceof DOMNode)) {
            /*
         * Wrap DOM nodes with phpQuery object, import into document when needed:
         * pq(array($domNode1, $domNode2))
         */
            $phpQuery = new Parser($domId);
            if (!($arg1 instanceof DOMNODELIST) && !is_array($arg1)) {
                $arg1 = [$arg1];
            }
            $phpQuery->elements = [];
            foreach ($arg1 as $node) {
                $sameDocument = $node->ownerDocument instanceof DOMDocument
                    && !$node->ownerDocument->isSameNode($phpQuery->document);
                $phpQuery->elements[] = $sameDocument
                    ? $phpQuery->document->importNode($node, true)
                    : $node;
            }

            return $phpQuery;
        }

        if (self::isMarkup($arg1)) {
            /**
             * Import HTML:
             * pq('<div/>').
             */
            $phpQuery = new Parser($domId);

            return $phpQuery->newInstance($phpQuery->documentWrapper->import($arg1));
        }

        /**
         * Run CSS query:
         * pq('div.myClass').
         */
        $phpQuery = new Parser($domId);
        if ($context && $context instanceof Parser) {
            $phpQuery->elements = $context->elements;
        } else {
            if ($context && $context instanceof DOMNODELIST) {
                $phpQuery->elements = [];
                foreach ($context as $node) {
                    $phpQuery->elements[] = $node;
                }
            } else {
                if ($context && $context instanceof DOMNode) {
                    $phpQuery->elements = [$context];
                }
            }
        }

        return $phpQuery->find($arg1);
    }

    /**
     * Sets default document to $id. Document has to be loaded prior
     * to using this method.
     * $id can be retrived via getDocumentID() or getDocumentIDRef().
     *
     * @param $id
     */
    public static function selectDocument($id)
    {
        $id = self::getDocumentID($id);
        debug("Selecting document '$id' as default one");
        self::$defaultDocumentID = self::getDocumentID($id);
    }

    /**
     * Returns document with id $id or last used as phpQueryObject.
     * $id can be retrived via getDocumentID() or getDocumentIDRef().
     * Chainable.
     *
     * @param $id
     *
     * @return Parser
     *
     * @see Dom::selectDocument()
     */
    public static function getDocument($id = null)
    {
        if ($id) {
            Dom::selectDocument($id);
        } else {
            $id = Dom::$defaultDocumentID;
        }

        return new Parser($id);
    }

    /**
     * Creates new document from markup.
     * Chainable.
     *
     * @param $markup
     *
     * @return Parser
     */
    public static function newDocument($markup = null)
    {
        $documentID = Dom::createDocumentWrapper($markup ?? '');

        return new Parser($documentID);
    }

    /**
     * Creates new document from file $file.
     * Chainable.
     *
     * @param string $file URLs allowed. See File wrapper page at php.net for more supported sources.
     *
     * @return Parser
     */
    public static function newDocumentFile($file)
    {
        return self::newDocument(file_get_contents($file));
    }

    /**
     * Enter description here...
     *
     * @param $html
     * @param $domId
     *
     * @return New DOM ID
     *
     * @todo support PHP tags in input
     * @todo support passing DOMDocument object from self::loadDocument
     */
    protected static function createDocumentWrapper($html)
    {
        $wrapper = new DOMDocumentWrapper($html);

        // bind document
        Dom::$documents[$wrapper->id] = $wrapper;
        // remember last loaded document
        Dom::selectDocument($wrapper->id);

        return $wrapper->id;
    }

    /**
     * Unloades all or specified document from memory.
     *
     * @param mixed $documentID @see phpQuery::getDocumentID() for supported types
     */
    public static function unloadDocuments($id = null)
    {
        if (isset($id)) {
            if ($id = self::getDocumentID($id)) {
                unset(Dom::$documents[$id]);
            }
        } else {
            foreach (Dom::$documents as $k => $v) {
                unset(Dom::$documents[$k]);
            }
        }
    }

    /**
     * Checks if $input is HTML string, which has to start with '<'.
     *
     * @param string $input
     *
     * @return bool
     *
     * @deprecated
     *
     * @todo still used ?
     */
    public static function isMarkup($input)
    {
        return !is_array($input) && '<' == substr(trim($input), 0, 1);
    }

    /**
     * Returns source's document ID.
     *
     * @param $source DOMNode|Parser
     *
     * @return string
     */
    public static function getDocumentID($source)
    {
        if ($source instanceof DOMDocument) {
            foreach (Dom::$documents as $id => $document) {
                if ($source->isSameNode($document->document)) {
                    return $id;
                }
            }
        } else {
            if ($source instanceof DOMNode) {
                foreach (Dom::$documents as $id => $document) {
                    if ($source->ownerDocument->isSameNode($document->document)) {
                        return $id;
                    }
                }
            } else {
                if ($source instanceof Parser) {
                    return $source->getDocumentID();
                } else {
                    if (is_string($source) && isset(Dom::$documents[$source])) {
                        return $source;
                    }
                }
            }
        }
    }

    /**
     * @param $callback Callback
     * @param $params
     * @param $paramStructure
     *
     * @return
     */
    public static function callbackRun($callback, $params = [], $paramStructure = null)
    {
        if (!$callback) {
            return;
        }

        if ($callback instanceof Callback) {
            $paramStructure = $callback->params;
            $callback = $callback->callback;
        }

        if (!$paramStructure) {
            return call_user_func_array($callback, $params);
        }

        foreach ($paramStructure as $i => $v) {
            $paramStructure[$i] = $v;
        }

        return call_user_func_array($callback, $paramStructure);
    }

    /**
     * Merge 2 phpQuery objects.
     *
     * @param array $one
     * @param array $two
     * @protected
     *
     * @todo node lists, phpQueryObject
     */
    public static function merge($one, $two)
    {
        $elements = $one->elements;
        foreach ($two->elements as $node) {
            $exists = false;
            foreach ($elements as $node2) {
                if ($node2->isSameNode($node)) {
                    $exists = true;
                }
            }
            if (!$exists) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    protected static function dataSetupNode($node, $documentID)
    {
        // search are return if alredy exists
        foreach (Dom::$documents[$documentID]->dataNodes as $dataNode) {
            if ($node->isSameNode($dataNode)) {
                return $dataNode;
            }
        }
        // if doesn't, add it
        Dom::$documents[$documentID]->dataNodes[] = $node;

        return $node;
    }

    protected static function dataRemoveNode($node, $documentID)
    {
        // search are return if alredy exists
        foreach (Dom::$documents[$documentID]->dataNodes as $k => $dataNode) {
            if ($node->isSameNode($dataNode)) {
                unset(self::$documents[$documentID]->dataNodes[$k]);
                unset(self::$documents[$documentID]->data[$dataNode->dataID]);
            }
        }
    }

    public static function data($node, $name, $data, $documentID = null)
    {
        if (!$documentID) { // TODO check if this works
            $documentID = self::getDocumentID($node);
        }
        $document = Dom::$documents[$documentID];
        $node = self::dataSetupNode($node, $documentID);
        if (!isset($node->dataID)) {
            $node->dataID = ++Dom::$documents[$documentID]->uuid;
        }
        $id = $node->dataID;
        if (!isset($document->data[$id])) {
            $document->data[$id] = [];
        }
        if (!is_null($data)) {
            $document->data[$id][$name] = $data;
        }
        if ($name) {
            if (isset($document->data[$id][$name])) {
                return $document->data[$id][$name];
            }
        } else {
            return $id;
        }
    }

    public static function removeData($node, $name, $documentID)
    {
        if (!$documentID) { // TODO check if this works
            $documentID = self::getDocumentID($node);
        }
        $document = Dom::$documents[$documentID];
        $node = self::dataSetupNode($node, $documentID);
        $id = $node->dataID;
        if ($name) {
            if (isset($document->data[$id][$name])) {
                unset($document->data[$id][$name]);
            }
            $name = null;
            foreach ($document->data[$id] as $name) {
                break;
            }
            if (!$name) {
                self::removeData($node, $name, $documentID);
            }
        } else {
            self::dataRemoveNode($node, $documentID);
        }
    }
}
