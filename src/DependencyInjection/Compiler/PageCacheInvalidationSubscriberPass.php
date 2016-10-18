<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler;

use DreadLabs\KunstmaanDistributedBundle\EventListener\PageCacheInvalidationSubscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * PageCacheInvalidationSubscriberPass.
 *
 * Registers the PageCacheInvalidationSubscriber in the DIC if the
 * `fos_http_cache.cache_manager` is available.
 */
class PageCacheInvalidationSubscriberPass implements CompilerPassInterface
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        $this->registerPageCacheInvalidationEventSubscriber();
    }

    private function registerPageCacheInvalidationEventSubscriber()
    {
        if (!$this->container->has('fos_http_cache.cache_manager')) {
            return;
        }

        $definition = new Definition(
            PageCacheInvalidationSubscriber::class,
            [
                new Reference('fos_http_cache.cache_manager'),
                new Reference('kunstmaan_admin.domain_configuration'),
                new Reference('router'),
            ]
        );
        $definition->addTag('kernel.event_subscriber');

        $this->container->setDefinition(
            'dreadlabs_kunstmaan_distributed.cache_page_events.subscriber',
            $definition
        );
    }
}
