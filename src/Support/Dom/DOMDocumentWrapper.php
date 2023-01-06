<?php

/*
 * This file is part of the jiannei/laravel-crawler.
 *
 * (c) jiannei <longjian.huang@foxmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Jiannei\LaravelCrawler\Support\Dom;

use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Exception;
use Jiannei\LaravelCrawler\Support\Query\Dom;

class DOMDocumentWrapper
{
    /**
     * @var DOMDocument
     */
    public $document;
    public $id;

    public $xpath;
    public $uuid = 0;
    public $data = [];
    public $eventsNodes = [];
    public $eventsGlobal = [];

    public $root;
    public $isDocumentFragment;
    public $charset;

    public static $defaultDoctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">';
    public static $defaultCharset = 'UTF-8';

    public function __construct($markup = null)
    {
        $this->load($markup);
        $this->id = md5(microtime());
    }

    protected function load($markup)
    {
        if ($this->loadMarkupHTML($markup)) {
            $this->charset = $this->document->encoding;
            $this->document->formatOutput = true;
            $this->document->preserveWhiteSpace = true;
            $this->root = $this->document;

            $this->xpath = new DOMXPath($this->document);
        }
    }

    protected function loadMarkupHTML($markup)
    {
        if (!isset($this->isDocumentFragment)) {
            $this->isDocumentFragment = $this->isDocumentFragmentHTML($markup);
        }

        $charset = null;
        $documentCharset = $this->charsetFromHTML($markup);
        if ($documentCharset) {
            $charset = $documentCharset;
            $markup = $this->charsetFixHTML($markup);
        }

        if (!$charset) {
            $charset = self::$defaultCharset;
        }

        if ($this->isDocumentFragment) {// 部分 dom
            debug("Full markup load (HTML), DocumentFragment detected, using charset '$charset'");
            $return = $this->documentFragmentLoadMarkup($this, $charset, $markup);
        } else {// 完整 dom
            if (!$documentCharset) {
                debug("Full markup load (HTML), appending charset: '$charset'");
                $markup = $this->charsetAppendToHTML($markup, $charset);
            }

            debug("Full markup load (HTML), documentCreate('$charset')");
            $this->document = new DOMDocument('1.0', $charset);
            $return = @$this->document->loadHTML($markup);
        }

        return $return;
    }

    /**
     * @param $markup
     *
     * @return array contentType, charset
     */
    protected function contentTypeFromHTML($markup)
    {
        $matches = [];
        // find meta tag
        preg_match(
            '@<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup,
            $matches
        );
        if (!isset($matches[0])) {
            return [null, null];
        }
        // get attr 'content'
        preg_match('@content\\s*=\\s*(["|\'])(.+?)\\1@', $matches[0], $matches);
        if (!isset($matches[0])) {
            return [null, null];
        }

        $contentType = $matches[2];
        $matches = explode(';', trim(strtolower($contentType)));
        if (isset($matches[1])) {
            $matches[1] = explode('=', $matches[1]);
            // strip 'charset='
            $matches[1] = isset($matches[1][1]) && trim($matches[1][1]) ? $matches[1][1] : $matches[1][0];
        } else {
            $matches[1] = null;
        }

        return $matches;
    }

    protected function charsetFromHTML($markup)
    {
        $contentType = $this->contentTypeFromHTML($markup);

        return $contentType[1];
    }

    /**
     * Repositions meta[type=charset] at the start of head. Bypasses DOMDocument bug.
     *
     * @see http://code.google.com/p/phpquery/issues/detail?id=80
     *
     * @param $html
     */
    protected function charsetFixHTML($markup)
    {
        $matches = [];
        // find meta tag
        preg_match(
            '@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i',
            $markup,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if (!isset($matches[0])) {
            return;
        }
        $metaContentType = $matches[0][0];
        $markup = substr($markup, 0, $matches[0][1]).substr($markup, $matches[0][1] + strlen($metaContentType));
        $headStart = stripos($markup, '<head>');

        return substr($markup, 0, $headStart + 6).$metaContentType.substr($markup, $headStart + 6);
    }

    protected function charsetAppendToHTML($html, $charset, $xhtml = false)
    {
        // remove existing meta[type=content-type]
        $html = preg_replace('@\s*<meta[^>]+http-equiv\\s*=\\s*(["|\'])Content-Type\\1([^>]+?)>@i', '', $html);
        $meta = '<meta http-equiv="Content-Type" content="text/html;charset='.$charset.'" '.($xhtml ? '/' : '').'>';
        if (false === strpos($html, '<head')) {
            if (false === strpos($html, '<html')) {
                return $meta.$html;
            }

            return preg_replace('@<html(.*?)(?(?<!\?)>)@s', "<html\\1><head>{$meta}</head>", $html);
        }

        return preg_replace('@<head(.*?)(?(?<!\?)>)@s', '<head\\1>'.$meta, $html);
    }

    protected function isDocumentFragmentHTML($markup)
    {
        return false === stripos($markup, '<html') && false === stripos($markup, '<!doctype');
    }

    /**
     * @param $source
     * @param $target
     * @param $sourceCharset
     *
     * @return array array of imported nodes
     */
    public function import($source, $sourceCharset = null)
    {
        // TODO charset conversions
        $return = [];
        if ($source instanceof DOMNode && !($source instanceof DOMNodeList)) {
            $source = [$source];
        }

        if (is_array($source) || $source instanceof DOMNodeList) {
            // dom nodes
            debug('Importing nodes to document');
            foreach ($source as $node) {
                $return[] = $this->document->importNode($node, true);
            }
        } else {
            // string markup
            $fake = $this->documentFragmentCreate($source, $sourceCharset);
            if (false === $fake) {
                throw new Exception('Error loading documentFragment markup');
            }

            return $this->import($fake->root->childNodes);
        }

        return $return;
    }

    /**
     * Creates new document fragment.
     *
     * @param $source
     *
     * @return DOMDocumentWrapper
     */
    protected function documentFragmentCreate($source, $charset = null)
    {
        $fake = new DOMDocumentWrapper();
        $fake->root = $fake->document;
        if (!$charset) {
            $charset = $this->charset;
        }
        //	$fake->documentCreate($this->charset);
        if ($source instanceof DOMNode && !($source instanceof DOMNodeList)) {
            $source = [$source];
        }

        if (is_array($source) || $source instanceof DOMNodeList) {
            // dom nodes
            // load fake document
            if (!$this->documentFragmentLoadMarkup($fake, $charset)) {
                return false;
            }
            $nodes = $fake->import($source);
            foreach ($nodes as $node) {
                $fake->root->appendChild($node);
            }
        } else {
            // string markup
            $this->documentFragmentLoadMarkup($fake, $charset, $source);
        }

        return $fake;
    }

    /**
     * @param $fragment DOMDocumentWrapper
     * @param $charset
     * @param null $markup
     *
     * @return bool $document
     */
    private function documentFragmentLoadMarkup($fragment, $charset, $markup = null)
    {
        $fragment->isDocumentFragment = false;
        $markup2 = self::$defaultDoctype.'<html><head><meta http-equiv="Content-Type" content="text/html;charset='.$charset.'"></head>';
        if (null == $markup) {
            $markup = '';
        }
        $noBody = false === strpos($markup, '<body');
        if ($noBody) {
            $markup2 .= '<body>';
        }
        $markup2 .= $markup;
        if ($noBody) {
            $markup2 .= '</body>';
        }
        $markup2 .= '</html>';

        $fragment->loadMarkupHTML($markup2);

        // TODO resolv body tag merging issue
        $fragment->root = $fragment->document->firstChild->nextSibling->firstChild->nextSibling;
        if (!$fragment->root) {
            return false;
        }

        $fragment->isDocumentFragment = true;

        return true;
    }

    protected function documentFragmentToMarkup($fragment)
    {
        debug('documentFragmentToMarkup');
        $tmp = $fragment->isDocumentFragment;
        $fragment->isDocumentFragment = false;
        $markup = $fragment->markup();

        $markup = substr($markup, strpos($markup, '<body>') + 6);
        $markup = substr($markup, 0, strrpos($markup, '</body>'));
        $fragment->isDocumentFragment = $tmp;
        if (Dom::$debug) {
            debug('documentFragmentToMarkup: '.substr($markup, 0, 150));
        }

        return $markup;
    }

    /**
     * Return document markup, starting with optional $nodes as root.
     *
     * @param $nodes    DOMNode|DOMNodeList
     *
     * @return string
     */
    public function markup($nodes = null, $innerMarkup = false)
    {
        if (isset($nodes) && 1 == count($nodes) && $nodes[0] instanceof DOMDocument) {
            $nodes = null;
        }

        if (isset($nodes)) {
            if (!is_array($nodes) && !($nodes instanceof DOMNodeList)) {
                $nodes = [$nodes];
            }
            if ($this->isDocumentFragment && !$innerMarkup) {
                foreach ($nodes as $i => $node) {
                    if ($node->isSameNode($this->root)) {
                        $nodes = array_slice($nodes, 0, $i) + $this->DOMNodeListToArray($node->childNodes) + array_slice($nodes, $i + 1);
                    }
                }
            }

            $loop = [];
            if ($innerMarkup) {
                foreach ($nodes as $node) {
                    if ($node->childNodes) {
                        foreach ($node->childNodes as $child) {
                            $loop[] = $child;
                        }
                    } else {
                        $loop[] = $node;
                    }
                }
            } else {
                $loop = $nodes;
            }
            debug('Getting markup, moving selected nodes ('.count($loop).') to new DocumentFragment');
            $fake = $this->documentFragmentCreate($loop);
            $markup = $this->documentFragmentToMarkup($fake);

            debug('Markup: '.substr($markup, 0, 250));

            return $markup;
        }

        if ($this->isDocumentFragment) {
            debug('Getting markup, DocumentFragment detected');

            return $this->documentFragmentToMarkup($this);
        }

        debug('Getting markup ('.'HTML'."), final with charset '{$this->charset}'");
        $markup = $this->document->saveHTML();

        debug('Markup: '.substr($markup, 0, 250));

        return $markup;
    }

    protected function DOMNodeListToArray($DOMNodeList)
    {
        $array = [];
        if (!$DOMNodeList) {
            return $array;
        }
        foreach ($DOMNodeList as $node) {
            $array[] = $node;
        }

        return $array;
    }
}
