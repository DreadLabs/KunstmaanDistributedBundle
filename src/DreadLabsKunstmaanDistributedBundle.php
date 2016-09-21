<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle;

use DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler\OverrideAdminCachePass;
use DreadLabs\KunstmaanDistributedBundle\DependencyInjection\Compiler\PageCacheInvalidationSubscriberPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DreadLabsKunstmaanDistributedBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new OverrideAdminCachePass());
        $container->addCompilerPass(new PageCacheInvalidationSubscriberPass());
    }
}
