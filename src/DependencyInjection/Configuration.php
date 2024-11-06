<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('job_queue');

        /** @noinspection NullPointerExceptionInspection */
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('jobs_table')
                    ->info('The table where jobs store in')
                    ->defaultValue('orm_jobs')
                ->end() // jobs_table
            ->end()
        ;

        return $treeBuilder;
    }
}
