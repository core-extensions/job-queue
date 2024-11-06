<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

abstract class AbstractJobHandler implements MessageHandlerInterface
{
    private JobRepository $jobRepository;
    private EntityManagerInterface $entityManager;

    public function __invoke(JobCommandInterface $jobCommand): void
    {

    }
}
