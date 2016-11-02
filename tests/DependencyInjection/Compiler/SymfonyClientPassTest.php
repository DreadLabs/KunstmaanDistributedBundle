<?php

/*
 * This file is part of the `Dreadlabs/kunstmaan-distributed-bundle` project.
 *
 * (c) https://github.com/Dreadlabs/kunstmaan-distributed-bundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\Tests\DependencyInjection\Compiler;

use DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler\SymfonyClientPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class SymfonyClientPassTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ContainerBuilder|\PHPUnit_Framework_MockObject_MockObject
     */
    private $containerBuilder;

    /**
     * @var Definition|\PHPUnit_Framework_MockObject_MockObject
     */
    private $definition;

    public function setUp()
    {
        $this->containerBuilder = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->definition = $this->getMockBuilder(Definition::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @test
     */
    public function itImplementsTheCompilerPassInterface()
    {
        $pass = new SymfonyClientPass();

        $this->assertInstanceOf(CompilerPassInterface::class, $pass);
    }

    /**
     * @test
     */
    public function itChecksIfTheSymfonyClientIsInjected()
    {
        $this->containerBuilder
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn(false);
        $this->containerBuilder
            ->expects($this->never())
            ->method('getDefinition');

        $pass = new SymfonyClientPass();
        $pass->process($this->containerBuilder);
    }

    /**
     * @test
     */
    public function itChecksIfTheOptionsParameterIsSet()
    {
        $this->containerBuilder
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('hasParameter')
            ->with($this->equalTo('http_cache_proxy_options'))
            ->willReturn(false);
        $this->containerBuilder
            ->expects($this->never())
            ->method('getDefinition');

        $pass = new SymfonyClientPass();
        $pass->process($this->containerBuilder);
    }

    /**
     * @test
     */
    public function itFetchesTheDefinitionIfTheSymfonyClientIsInjected()
    {
        $this->containerBuilder
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('hasParameter')
            ->with($this->equalTo('http_cache_proxy_options'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('getDefinition')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn($this->definition);

        $pass = new SymfonyClientPass();
        $pass->process($this->containerBuilder);
    }

    /**
     * @test
     */
    public function itChecksIfTheAmountOfArgumentsMatchTheDefaultConfiguration()
    {
        $this->containerBuilder
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('hasParameter')
            ->with($this->equalTo('http_cache_proxy_options'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('getDefinition')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn($this->definition);

        $this->definition
            ->expects($this->once())
            ->method('getArguments')
            ->willReturn(['some', 'deviating', 'dummy', 'arguments']);

        $this->definition
            ->expects($this->never())
            ->method('addArgument');

        $pass = new SymfonyClientPass();
        $pass->process($this->containerBuilder);
    }

    /**
     * @test
     */
    public function itAddsTheOptionsArgumentToTheDefinition()
    {
        $this->containerBuilder
            ->expects($this->once())
            ->method('has')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('hasParameter')
            ->with($this->equalTo('http_cache_proxy_options'))
            ->willReturn(true);
        $this->containerBuilder
            ->expects($this->once())
            ->method('getDefinition')
            ->with($this->equalTo('fos_http_cache.proxy_client.symfony'))
            ->willReturn($this->definition);

        $this->definition
            ->expects($this->once())
            ->method('getArguments')
            ->willReturn(['three', 'default', 'arguments']);

        $this->definition
            ->expects($this->once())
            ->method('addArgument');

        $pass = new SymfonyClientPass();
        $pass->process($this->containerBuilder);
    }
}
