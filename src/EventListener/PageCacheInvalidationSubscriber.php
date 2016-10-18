<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\EventListener;

use FOS\HttpCacheBundle\CacheManager;
use Kunstmaan\NodeBundle\Event\Events;
use Kunstmaan\NodeBundle\Event\NodeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * PageCacheInvalidationSubscriber.
 *
 * Subscribes to relevant events to invalidate the page ("Node") cache
 * from the Kunstmaan backend.
 */
class PageCacheInvalidationSubscriber implements EventSubscriberInterface
{
    /**
     * @var CacheManager
     */
    private $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::POST_UNPUBLISH => 'onUnpublishPage',
            Events::POST_DELETE => 'onDeletePage',
            Events::POST_PERSIST => 'onUpdatePage',
        ];
    }

    public function onUnpublishPage(NodeEvent $event)
    {
        $this->cacheManager->invalidatePath(
            $event->getNodeTranslation()->getUrl()
        )->flush();
    }

    public function onDeletePage(NodeEvent $event)
    {
        $this->cacheManager->invalidatePath(
            $event->getNodeTranslation()->getUrl()
        )->flush();
    }

    public function onUpdatePage(NodeEvent $event)
    {
        $this->cacheManager->invalidatePath(
            $event->getNodeTranslation()->getUrl()
        )->flush();
    }
}
