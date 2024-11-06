<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Repository;

use CoreExtensions\JobQueueBundle\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @lib
 */
final class JobRepository extends ServiceEntityRepository
{
    public const CRITERIA_JOB_ID_EQ = 'jobId';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function findNextChained(string $chainId, int $currPosition): ?Job
    {
        return null;
    }

    public function findByCriteria(array $criteria): ?Job
    {
        // применяем CRITERIA_*
        return [];
    }

}
