<?php

/*
 * This file is part of the `Dreadlabs/kunstmaan-distributed-bundle` project.
 *
 * (c) https://github.com/Dreadlabs/kunstmaan-distributed-bundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

class SymfonyClientPass implements CompilerPassInterface
{
    /**
     * Number of default arguments from FOSHttpCacheBundle::symfony.xml
     *
     * @var int
     */
    private $nbOfDefaultArguments = 3;

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('fos_http_cache.proxy_client.symfony')) {
            return;
        }
        if (!$container->hasParameter('http_cache_proxy_options')) {
            return;
        }

        $proxyClientDefinition = $container->getDefinition('fos_http_cache.proxy_client.symfony');

        if (count($proxyClientDefinition->getArguments()) > $this->nbOfDefaultArguments) {
            return;
        }

        $proxyClientDefinition->addArgument(new Parameter('http_cache_proxy_options'));
    }
}
