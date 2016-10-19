<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\Tests\HttpCache;

use DreadLabs\KunstmaanDistributedBundle\HttpCache\HttpCache;
use Predis\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class HttpCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var HttpKernelInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $kernel;

    /**
     * @var ContainerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $container;

    /**
     * @var ClientInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $cacheClient;

    public function setUp()
    {
        $this->kernel = $this->getMockBuilder(HttpKernelInterface::class)
            ->setMethods(['handle', 'boot', 'getContainer', 'isDebug'])
            ->getMock();
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $this->cacheClient = $this->getMockBuilder(ClientInterface::class)
            ->getMock();
    }

    /**
     * @test
     */
    public function bootsKernelAndQueriesClientIpsAndCreatesPredisStore()
    {
        $this->kernel
            ->expects($this->exactly(2))
            ->method('boot');
        $this->kernel
            ->expects($this->exactly(2))
            ->method('getContainer')
            ->willReturn($this->container);

        $this->container
            ->expects($this->once())
            ->method('getParameter')
            ->with($this->equalTo('http_cache_purge_client_ips'));
        $this->container
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('snc_redis.http_cache_client'))
            ->willReturn($this->cacheClient);

        new HttpCache($this->kernel);
    }
}
