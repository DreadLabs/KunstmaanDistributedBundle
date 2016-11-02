<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DreadLabsKunstmaanDistributedExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $registeredExtensions = $container->getExtensions();

        foreach ($registeredExtensions as $extension) {
            $alias = $extension->getAlias();

            switch ($alias) {
                case 'snc_redis':
                    $container->prependExtensionConfig($alias, $this->getSncRedisConfiguration($container));

                    break;
                case 'doctrine_cache':
                    $container->prependExtensionConfig($alias, $this->getDoctrineCacheConfiguration());

                    break;
                case 'framework':
                    $container->prependExtensionConfig($alias, $this->getFrameworkConfiguration($container));

                    break;
            }
        }
    }

    /**
     * Builds the configuration for the snc_redis extension.
     *
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getSncRedisConfiguration(ContainerBuilder $container)
    {
        $config = [
            'clients' => [
                'doctrine_cache' => [
                    'type' => 'predis',
                    'alias' => 'doctrine_cache',
                    'dsns' => ['redis://%redis_host%/%redis_db_cache%'],
                ],
                'framework_annotations' => [
                    'type' => 'predis',
                    'alias' => 'framework_annotations',
                    'dsns' => ['redis://%redis_host%/%redis_db_annotations%'],
                ],
                'http_cache' => [
                    'type' => 'predis',
                    'alias' => 'http_cache',
                    'dsns' => ['redis://%redis_host%/%redis_db_http_cache%'],
                ],
                'kunstmaanadmin_cache' => [
                    'type' => 'predis',
                    'alias' => 'kunstmaanadmin_cache',
                    'dsns' => ['redis://%redis_host%/%redis_db_kunstmaanadmin_cache%'],
                ],
            ],
        ];

        if ($container->getParameter('kernel.environment') == 'prod') {
            $config = array_merge($config, [
                'doctrine' => [
                    'metadata_cache' => [
                        'client' => 'doctrine_cache',
                        'entity_manager' => 'default',
                        'document_manager' => 'default',
                    ],
                    'result_cache' => [
                        'client' => 'doctrine_cache',
                        'entity_manager' => 'default',
                    ],
                    'query_cache' => [
                        'client' => 'doctrine_cache',
                        'entity_manager' => 'default',
                    ],
                    'second_level_cache' => [
                        'client' => 'doctrine_cache',
                        'entity_manager' => 'default',
                    ],
                ],
            ]);
        }

        return $config;
    }

    /**
     * Builds the configuration for the doctrine_cache extension.
     *
     * @return array
     */
    private function getDoctrineCacheConfiguration()
    {
        $config = [
            'providers' => [
                'framework_annotations_cache' => [
                    'type' => 'predis',
                    'predis' => [
                        'client_id' => 'snc_redis.framework_annotations_client',
                    ],
                ],
            ],
        ];

        return $config;
    }

    /**
     * Builds the configuration for the framework extension.
     *
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function getFrameworkConfiguration(ContainerBuilder $container)
    {
        $config = [];

        if ($container->getParameter('kernel.environment') == 'prod') {
            $config = [
                'annotations' => [
                    'cache' => 'doctrine_cache.providers.framework_annotations_cache',
                ],
            ];
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
