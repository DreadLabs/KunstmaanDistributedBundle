<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\HttpCache;

use DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\PredisStore;
use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\CacheInvalidationInterface;
use FOS\HttpCache\SymfonyCache\Events;
use FOS\HttpCache\SymfonyCache\PurgeSubscriber;
use Predis\ClientInterface;
use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache as FrameworkHttpCache;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class HttpCache extends FrameworkHttpCache implements CacheInvalidationInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(HttpKernelInterface $kernel, $cacheDir = null)
    {
        parent::__construct($kernel, $cacheDir);

        $kernel->boot();

        $this->addSubscriber(
            new PurgeSubscriber(
                [
                    'purge_client_ips' => $kernel->getContainer()->getParameter('http_cache_purge_client_ips'),
                ]
            )
        );
    }

    protected function createStore()
    {
        try {
            return new PredisStore($this->getPredisCacheClient());
        } catch (\Exception $e) {
            return parent::createStore();
        }
    }

    /**
     * @return ClientInterface
     */
    private function getPredisCacheClient()
    {
        $this->kernel->boot();

        return $this->kernel->getContainer()->get('snc_redis.http_cache_client');
    }

    /**
     * Get event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        if (null === $this->eventDispatcher) {
            $this->eventDispatcher = new EventDispatcher();
        }

        return $this->eventDispatcher;
    }

    /**
     * Add subscriber.
     *
     * @param EventSubscriberInterface $subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->getEventDispatcher()->addSubscriber($subscriber);
    }

    /**
     * {@inheritdoc}
     *
     * Adding the Events::PRE_HANDLE event.
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if ($this->getEventDispatcher()->hasListeners(Events::PRE_HANDLE)) {
            $event = new CacheEvent($this, $request);
            $this->getEventDispatcher()->dispatch(Events::PRE_HANDLE, $event);
            if ($event->getResponse()) {
                return $event->getResponse();
            }
        }

        return parent::handle($request, $type, $catch);
    }

    /**
     * Made public to allow event subscribers to do refresh operations.
     *
     * {@inheritdoc}
     */
    public function fetch(Request $request, $catch = false)
    {
        return parent::fetch($request, $catch);
    }

    /**
     * {@inheritdoc}
     *
     * Adding the Events::PRE_INVALIDATE event.
     */
    protected function invalidate(Request $request, $catch = false)
    {
        if ($this->getEventDispatcher()->hasListeners(Events::PRE_INVALIDATE)) {
            $event = new CacheEvent($this, $request);
            $this->getEventDispatcher()->dispatch(Events::PRE_INVALIDATE, $event);
            if ($event->getResponse()) {
                return $event->getResponse();
            }
        }

        return parent::invalidate($request, $catch);
    }
}
