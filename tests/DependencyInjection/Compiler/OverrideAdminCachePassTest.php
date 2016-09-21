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

use Doctrine\Common\Cache\PredisCache;
use DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler\OverrideAdminCachePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class OverrideAdminCachePassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $container;

    /**
     * @var Definition|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $definition;

    public function setUp()
    {
        $this->container = $this->getMockBuilder(ContainerBuilder::class)->getMock();
        $this->definition = $this->getMockBuilder(Definition::class)->getMock();
    }

    /**
     * @test
     */
    public function itFetchesTheCurrentKunstmaanAdminCacheDefinition()
    {
        $this->expectDefinition();

        $compilerPass = new OverrideAdminCachePass();
        $compilerPass->process($this->container);
    }

    protected function expectDefinition()
    {
        $this->container
            ->expects($this->once())
            ->method('getDefinition')
            ->with($this->equalTo('kunstmaan_admin.cache'))
            ->willReturn($this->definition);
    }

    /**
     * @test
     */
    public function itOverridesTheClassOfTheKunstmaanAdminCacheDefinition()
    {
        $this->expectDefinition();
        $this->expectClassOverride();

        $compilerPass = new OverrideAdminCachePass();
        $compilerPass->process($this->container);
    }

    protected function expectClassOverride()
    {
        $this->definition
            ->expects($this->once())
            ->method('setClass')
            ->with($this->equalTo(PredisCache::class));
    }

    /**
     * @test
     */
    public function itOverridesTheArgumentsOfTheKunstmaanAdminCacheDefinition()
    {
        $this->expectDefinition();
        $this->expectClassOverride();

        $this->definition
            ->expects($this->once())
            ->method('setArguments')
            ->with($this->countOf(1));

        $compilerPass = new OverrideAdminCachePass();
        $compilerPass->process($this->container);
    }
}
