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


use Jiannei\LaravelCrawler\Support\Dom\DOMEvent;

/**
 * Event handling class.
 *
 * @author Tobiasz Cudnik
 * @static
 */
abstract class QueryEvents
{
    /**
     * Trigger a type of event on every matched element.
     *
     * @param DOMNode|Parser|string $document
     * @param                   $type
     * @param                   $data
     *
     * @TODO exclusive events (with !)
     * @TODO global events (test)
     * @TODO support more than event in $type (space-separated)
     */
    public static function trigger($document, $type, $data = [], $node = null)
    {
        // trigger: function(type, data, elem, donative, extra) {
        $documentID = phpQuery::getDocumentID($document);
        $namespace = null;
        if (false !== strpos($type, '.')) {
            list($name, $namespace) = explode('.', $type);
        } else {
            $name = $type;
        }
        if (!$node) {
            if (self::issetGlobal($documentID, $type)) {
                $pq = phpQuery::getDocument($documentID);
                // TODO check add($pq->document)
                $pq->find('*')->add($pq->document)
                    ->trigger($type, $data);
            }
        } else {
            if (isset($data[0]) && $data[0] instanceof DOMEvent) {
                $event = $data[0];
                $event->relatedTarget = $event->target;
                $event->target = $node;
                $data = array_slice($data, 1);
            } else {
                $event = new DOMEvent([
                    'type' => $type,
                    'target' => $node,
                    'timeStamp' => time(),
                ]);
            }
            $i = 0;
            while ($node) {
                phpQuery::debug(
                    'Triggering '.($i ? 'bubbled ' : '')."event '{$type}' on "
                    ."node \n"
                ); // .phpQueryObject::whois($node)."\n");
                $event->currentTarget = $node;
                $eventNode = self::getNode($documentID, $node);
                if (isset($eventNode->eventHandlers)) {
                    foreach ($eventNode->eventHandlers as $eventType => $handlers) {
                        $eventNamespace = null;
                        if (false !== strpos($type, '.')) {
                            list($eventName, $eventNamespace) = explode('.', $eventType);
                        } else {
                            $eventName = $eventType;
                        }
                        if ($name != $eventName) {
                            continue;
                        }
                        if ($namespace && $eventNamespace && $namespace != $eventNamespace) {
                            continue;
                        }
                        foreach ($handlers as $handler) {
                            phpQuery::debug("Calling event handler\n");
                            $event->data = $handler['data']
                                ? $handler['data']
                                : null;
                            $params = array_merge([$event], $data);
                            $return = phpQuery::callbackRun($handler['callback'], $params);
                            if (false === $return) {
                                $event->bubbles = false;
                            }
                        }
                    }
                }
                // to bubble or not to bubble...
                if (!$event->bubbles) {
                    break;
                }
                $node = $node->parentNode;
                ++$i;
            }
        }
    }

    protected static function getNode($documentID, $node)
    {
        foreach (phpQuery::$documents[$documentID]->eventsNodes as $eventNode) {
            if ($node->isSameNode($eventNode)) {
                return $eventNode;
            }
        }
    }

    protected static function setNode($documentID, $node)
    {
        phpQuery::$documents[$documentID]->eventsNodes[] = $node;

        return phpQuery::$documents[$documentID]->eventsNodes[count(phpQuery::$documents[$documentID]->eventsNodes) - 1];
    }

    protected static function issetGlobal($documentID, $type)
    {
        return isset(phpQuery::$documents[$documentID]) ? in_array($type, phpQuery::$documents[$documentID]->eventsGlobal) : false;
    }
}
