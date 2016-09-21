<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\tests\DependencyInjection\Compiler;

use DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler\PageCacheInvalidationSubscriberPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class PageCacheInvalidationSubscriberPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $container;

    public function setUp()
    {
        $this->container = $this->getMockBuilder(ContainerBuilder::class)->getMock();
    }

    /**
     * @test
     */
    public function itChecksForCacheManagerAvailability()
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.cache_manager'));

        $pass = new PageCacheInvalidationSubscriberPass();
        $pass->process($this->container);
    }

    /**
     * @test
     */
    public function itWillNotSetDefinitionIfCacheManagerIsUnavailable()
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.cache_manager'))
            ->willReturn(false);
        $this->container
            ->expects($this->never())
            ->method('setDefinition');

        $pass = new PageCacheInvalidationSubscriberPass();
        $pass->process($this->container);
    }

    /**
     * @test
     */
    public function itWillSetDefinitionIfCacheManagerIsAvailable()
    {
        $this->container
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.cache_manager'))
            ->willReturn(true);

        $this->container
            ->expects($this->once())
            ->method('setDefinition')
            ->with(
                $this->equalTo('dreadlabs_kunstmaan_distributed.cache_page_events.subscriber'),
                $this->isInstanceOf(Definition::class)
            );

        $pass = new PageCacheInvalidationSubscriberPass();
        $pass->process($this->container);
    }
}
