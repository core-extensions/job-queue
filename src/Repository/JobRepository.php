<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Repository;

use CoreExtensions\JobQueueBundle\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Webmozart\Assert\Assert;

/**
 * @template-extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    public const SIMPLE_CRITERIA_JOB_ID_EQ = 'jobId';
    public const SIMPLE_CRITERIA_JOB_TYPE_EQ = 'jobType';
    public const SIMPLE_CRITERIA_CREATED_AT_LTE = 'createdAtLte';
    public const SIMPLE_CRITERIA_CREATED_AT_GTE = 'createdAtGte';
    public const SIMPLE_CRITERIA_LAST_DISPATCHED_AT_LTE = 'lastDispatchedAtLte';
    public const SIMPLE_CRITERIA_LAST_DISPATCHED_AT_GTE = 'lastDispatchedAtGte';
    public const SIMPLE_CRITERIA_LAST_ACCEPTED_AT_LTE = 'lastAcceptedAtLte';
    public const SIMPLE_CRITERIA_LAST_ACCEPTED_AT_GTE = 'lastAcceptedAtGte';
    public const SIMPLE_CRITERIA_REVOKED_AT_LTE = 'revokedAtLte';
    public const SIMPLE_CRITERIA_REVOKED_AT_GTE = 'revokedAtGte';
    public const SIMPLE_CRITERIA_RESOLVED_AT_LTE = 'resolvedAtLte';
    public const SIMPLE_CRITERIA_RESOLVED_AT_GTE = 'resolvedAtGte';
    public const SIMPLE_CRITERIA_SEALED_AT_LTE = 'sealedAtLte';
    public const SIMPLE_CRITERIA_SEALED_AT_GTE = 'sealedAtGte';
    public const SIMPLE_CRITERIA_HAS_ERRORS = 'hasErrors';
    public const SIMPLE_CRITERIA_HAS_ATTEMPTS = 'hasAttempts';
    public const SIMPLE_CRITERIA_CHAIN_ID_EQ = 'chainIdEq';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function findNextChained(string $chainId, int $currPosition): ?Job
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->findOneBy(['chainId' => $chainId, 'chainPosition' => $currPosition + 1]);
    }

    /**
     * Our way to determine search options.
     *
     * @param array<string, mixed>                $criteria
     * @param ?array<array{string, 'ASC'|'DESC'}> $orderBy
     *
     * @return ?Job[]
     */
    public function findBySimpleCriteria(
        array $criteria,
        ?array $orderBy = null,
        int $limit = null,
        int $offset = null
    ): ?array {
        $qb = $this->createQueryBuilder('jobs');
        $qb = $this->applyCriteria($qb, $criteria);

        if (null !== $orderBy) {
            foreach ($orderBy as $arr) {
                Assert::count($arr, 2, sprintf('Invalid order by first parameter "%s"', __METHOD__));
                $qb->orderBy($arr[0], $arr[1]);
            }
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        if (null !== $offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->select('jobs')->getQuery()->getResult();
        // return $this->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function applyCriteria(QueryBuilder $qb, array $criteria): QueryBuilder
    {
        foreach ($criteria as $key => $val) {
            switch ($key) {
                case self::SIMPLE_CRITERIA_JOB_ID_EQ:
                    $qb->andWhere('jobs.jobId = :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_JOB_TYPE_EQ:
                    $qb->andWhere('jobs.jobType = :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_CREATED_AT_LTE:
                    $qb->andWhere('jobs.createdAt <= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_CREATED_AT_GTE:
                    $qb->andWhere('jobs.createdAt >= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_LAST_DISPATCHED_AT_LTE:
                    $qb->andWhere('jobs.lastDispatchedAt <= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_LAST_DISPATCHED_AT_GTE:
                    $qb->andWhere('jobs.lastDispatchedAt >= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_LAST_ACCEPTED_AT_LTE:
                    $qb->andWhere('jobs.lastAcceptedAt <= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_LAST_ACCEPTED_AT_GTE:
                    $qb->andWhere('jobs.lastAcceptedAt >= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_REVOKED_AT_LTE:
                    $qb->andWhere('jobs.revokedAt <= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_REVOKED_AT_GTE:
                    $qb->andWhere('jobs.revokedAt >= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_RESOLVED_AT_LTE:
                    $qb->andWhere('jobs.resolvedAt <= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_RESOLVED_AT_GTE:
                    $qb->andWhere('jobs.resolvedAt >= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_SEALED_AT_LTE:
                    $qb->andWhere('jobs.sealedAt <= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_SEALED_AT_GTE:
                    $qb->andWhere('jobs.sealedAt >= :val')
                        ->setParameter('val', $val);
                    break;
                case self::SIMPLE_CRITERIA_HAS_ERRORS:
                    Assert::boolean(
                        $val,
                        sprintf('Invalid value for criteria "%s" in "%s', self::SIMPLE_CRITERIA_HAS_ERRORS, __METHOD__)
                    );
                    if (true === $val) {
                        $qb->andWhere('jobs.errors IS NOT NULL');
                    }
                    if (false === $val) {
                        $qb->andWhere('jobs.errors IS NULL');
                    }
                    break;
                case self::SIMPLE_CRITERIA_HAS_ATTEMPTS:
                    Assert::boolean(
                        $val,
                        sprintf('Invalid value for criteria "%s" in "%s', self::SIMPLE_CRITERIA_HAS_ATTEMPTS, __METHOD__)
                    );
                    if (true === $val) {
                        $qb->andWhere('jobs.attemptsCount > 0');
                    }
                    if (false === $val) {
                        $qb->andWhere('jobs.attemptsCount = 0');
                    }
                    break;
                case self::SIMPLE_CRITERIA_CHAIN_ID_EQ:
                    $qb->andWhere('jobs.chainId = :val')
                        ->setParameter('val', $val);
                    break;
                default:
                    throw new \RuntimeException(sprintf('Unsupported simple criteria "%s" in "%s"', $key, __METHOD__));
            }
        }

        return $qb;
    }
}
