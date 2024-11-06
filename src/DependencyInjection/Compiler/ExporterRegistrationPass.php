<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExporterRegistrationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
    }
}