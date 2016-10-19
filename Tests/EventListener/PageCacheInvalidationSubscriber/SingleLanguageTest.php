<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\tests\EventListener\PageCacheInvalidationSubscriber;

use DreadLabs\KunstmaanDistributedBundle\EventListener\PageCacheInvalidationSubscriber;
use FOS\HttpCacheBundle\CacheManager;
use Kunstmaan\AdminBundle\Helper\DomainConfigurationInterface;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;
use Kunstmaan\NodeBundle\Event\Events;
use Kunstmaan\NodeBundle\Event\NodeEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SingleLanguageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CacheManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $cacheManager;

    /**
     * @var DomainConfigurationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $domainConfiguration;

    /**
     * @var UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $urlGenerator;

    /**
     * @var NodeEvent|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $nodeEvent;

    /**
     * @var NodeTranslation|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $nodeTranslation;

    public function setUp()
    {
        $this->cacheManager = $this->getMockBuilder(CacheManager::class)->disableOriginalConstructor()->getMock();
        $this->domainConfiguration = $this->getMockBuilder(DomainConfigurationInterface::class)->disableOriginalConstructor()->getMock();
        $this->urlGenerator = $this->getMockBuilder(UrlGeneratorInterface::class)->disableOriginalConstructor()->getMock();

        $this->nodeEvent = $this->getMockBuilder(NodeEvent::class)->disableOriginalConstructor()->getMock();
        $this->nodeTranslation = $this->getMockBuilder(NodeTranslation::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * @test
     */
    public function eventsSubscription()
    {
        $events = PageCacheInvalidationSubscriber::getSubscribedEvents();

        $this->assertCount(3, $events);

        $this->assertArrayHasKey(Events::POST_UNPUBLISH, $events);
        $this->assertArrayHasKey(Events::POST_DELETE, $events);
        $this->assertArrayHasKey(Events::POST_PERSIST, $events);
    }

    /**
     * @test
     */
    public function pathInvalidatonOnPageUnpublishing()
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('invalidatePath')
            ->with($this->equalTo('path/to/page'))
            ->willReturn($this->cacheManager);
        $this->domainConfiguration
            ->expects($this->once())
            ->method('isMultiLanguage')
            ->willReturn(false);
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('_slug'), $this->equalTo(['url' => 'path/to/page']))
            ->willReturn('path/to/page');

        $this->nodeEvent
            ->expects($this->once())
            ->method('getNodeTranslation')
            ->willReturn($this->nodeTranslation);

        $this->nodeTranslation
            ->expects($this->once())
            ->method('getUrl')
            ->willReturn('path/to/page');

        $this->cacheManager
            ->expects($this->once())
            ->method('flush');

        $subscriber = new PageCacheInvalidationSubscriber(
            $this->cacheManager,
            $this->domainConfiguration,
            $this->urlGenerator
        );
        $subscriber->onUnpublishPage($this->nodeEvent);
    }

    /**
     * @test
     */
    public function pathInvalidationOnPageDeletion()
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('invalidatePath')
            ->with($this->equalTo('path/to/another/page'))
            ->willReturn($this->cacheManager);
        $this->domainConfiguration
            ->expects($this->once())
            ->method('isMultiLanguage')
            ->willReturn(false);
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('_slug'), $this->equalTo(['url' => 'path/to/another/page']))
            ->willReturn('path/to/another/page');

        $this->nodeEvent
            ->expects($this->once())
            ->method('getNodeTranslation')
            ->willReturn($this->nodeTranslation);

        $this->nodeTranslation
            ->expects($this->once())
            ->method('getUrl')
            ->willReturn('path/to/another/page');

        $this->cacheManager
            ->expects($this->once())
            ->method('flush');

        $subscriber = new PageCacheInvalidationSubscriber(
            $this->cacheManager,
            $this->domainConfiguration,
            $this->urlGenerator
        );
        $subscriber->onDeletePage($this->nodeEvent);
    }

    /**
     * @test
     */
    public function pathInvalidationOnPageUpdate()
    {
        $this->cacheManager
            ->expects($this->once())
            ->method('invalidatePath')
            ->with($this->equalTo('path/to/page/three'))
            ->willReturn($this->cacheManager);
        $this->domainConfiguration
            ->expects($this->once())
            ->method('isMultiLanguage')
            ->willReturn(false);
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->equalTo('_slug'), $this->equalTo(['url' => 'path/to/page/three']))
            ->willReturn('path/to/page/three');

        $this->nodeEvent
            ->expects($this->once())
            ->method('getNodeTranslation')
            ->willReturn($this->nodeTranslation);

        $this->nodeTranslation
            ->expects($this->once())
            ->method('getUrl')
            ->willReturn('path/to/page/three');

        $this->cacheManager
            ->expects($this->once())
            ->method('flush');

        $subscriber = new PageCacheInvalidationSubscriber(
            $this->cacheManager,
            $this->domainConfiguration,
            $this->urlGenerator
        );
        $subscriber->onUpdatePage($this->nodeEvent);
    }
}
