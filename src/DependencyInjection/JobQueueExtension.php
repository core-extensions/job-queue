<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class JobQueueExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // foreach ($config as $key => $value) {
        //     $container->setParameter('security_headers.'.$key, $value);
        // }

        // $container->setParameter('core-extensions_job_queue_bundle.doctrine_mappings', [
        //     Job::class => [
        //         'type' => 'xml',
        //         'dir' => __DIR__.'/../Resources/config/doctrine',
        //         'prefix' => 'CoreExtensions\JobQueueBundle\Entity',
        //         'alias' => 'Job',
        //     ],
        // ]);

        // $definition = $container->getDefinition('core-extensions.job_queue.job_repository');
        // $definition->replaceArgument(0, $config['twitter']['client_id']); // ManagerRegistry
    }
}
