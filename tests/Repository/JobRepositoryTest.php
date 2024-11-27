<?php

declare(strict_types=1);

namespace СoreExtensions\JobQueueBundle\Tests\Repository;

use CoreExtensions\JobQueueBundle\Entity\AcceptanceInfo;
use CoreExtensions\JobQueueBundle\Entity\DispatchInfo;
use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JobRepositoryTest extends KernelTestCase
{
    private JobRepository $repository;
    private EntityManager $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        // $this->repository = $this->entityManager->getRepository(Job::class);
        $this->repository = self::getContainer()->get('core-extensions.job_queue.job_repository');

        // adopted to sqlite
        $sql = <<<SQL
CREATE TABLE jobs__orm_jobs
(
    job_id             UUID                     NOT NULL,
    job_type           VARCHAR             NOT NULL,
    job_command        JSONB                             DEFAULT NULL,
    created_at         TIMESTAMP WITH TIME ZONE NOT NULL,
    dispatches         JSONB                             DEFAULT NULL,
    acceptances        JSONB                             DEFAULT NULL,
    last_dispatched_at TIMESTAMP WITH TIME ZONE          DEFAULT NULL,
    last_accepted_at   TIMESTAMP WITH TIME ZONE          DEFAULT NULL,
    revoked_at         TIMESTAMP WITH TIME ZONE          DEFAULT NULL,
    revoked_for        INTEGER                           DEFAULT NULL,
    revoke_accepted_at TIMESTAMP WITH TIME ZONE          DEFAULT NULL,
    chain_id           UUID                              DEFAULT NULL,
    chain_position     INTEGER                           DEFAULT NULL,
    result             JSONB                             DEFAULT NULL,
    resolved_at        TIMESTAMP WITH TIME ZONE          DEFAULT NULL,
    attempts_count     INTEGER                  NOT NULL DEFAULT 0,
    errors             JSONB                             DEFAULT NULL,
    job_configuration  JSONB                    NOT NULL,
    version            INTEGER                  NOT NULL DEFAULT 0,
    sealed_at          TIMESTAMP WITH TIME ZONE          DEFAULT NULL,
    sealed_because_of  INTEGER                           DEFAULT NULL,
    PRIMARY KEY (job_id)
)
SQL;

        $this->entityManager->getConnection()->executeQuery($sql);
    }

    /**
     * @test
     */
    public function it_finds_next_chained_job(): void
    {
        // given
        $chainId = 'ef6c1ff6-1bf3-42d5-b69a-20c55541353d';

        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'created');
        $job1->bindToChain($chainId, 1);
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'created');
        $job2->bindToChain($chainId, 2);

        $this->saveJobs([$job1, $job2]);

        // when finds next
        $nextJob = $this->repository->findNextChained($chainId, 1);

        // then found
        $this->assertEquals($nextJob->getJobId(), $job2->getJobId());
        $this->assertEquals($chainId, $nextJob->getChainId());
        $this->assertEquals(2, $nextJob->getChainPosition());

        // when finds unknown
        $nextJob = $this->repository->findNextChained($chainId, 10);

        // then found no item
        $this->assertNull($nextJob);
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_job_id(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'created');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'created');
        $this->saveJobs([$job1, $job2]);

        // then finds by id
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_JOB_ID_EQ => '8e2a3cfc-eef8-44f4-96ed-99a6b1678266']
        );
        $this->assertCount(1, $results);
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $results[0]->getJobId());

        // then fund another too
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_JOB_ID_EQ => 'a11d0109-1eb5-48e9-9eda-dedd706bc447']
        );
        $this->assertCount(1, $results);
        $this->assertEquals('a11d0109-1eb5-48e9-9eda-dedd706bc447', $results[0]->getJobId());
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_job_type(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'created');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'created');
        $job2->setJobType('another_type_only_for_testing_purposes');
        $this->saveJobs([$job1, $job2]);

        // then
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_JOB_TYPE_EQ => TestingJobCommand::JOB_TYPE]
        );
        $this->assertCount(1, $results);

        // then
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_JOB_TYPE_EQ => 'another_type_only_for_testing_purposes']
        );
        $this->assertCount(1, $results);

        // then
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_JOB_TYPE_EQ => 'unknown_type']
        );
        $this->assertCount(0, $results);
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_created_at(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'created');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'created');
        $this->saveJobs([$job1, $job2]);

        // then find by created_at
        $this->assertLteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_CREATED_AT_LTE,
            $job1->getCreatedAt(),
            2
        );
        $this->assertGteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_CREATED_AT_GTE,
            $job1->getCreatedAt(),
            2
        );
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_dispatched_at(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'dispatched');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'dispatched');
        $this->saveJobs([$job1, $job2]);

        // then
        $this->assertLteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_LAST_DISPATCHED_AT_LTE,
            $job1->getLastDispatchedAt(),
            2
        );
        $this->assertGteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_LAST_DISPATCHED_AT_GTE,
            $job1->getLastDispatchedAt(),
            2
        );
    }


    /**
     * @test
     */
    public function it_finds_by_simple_criteria_accepted_at(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'accepted');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'accepted');
        $this->saveJobs([$job1, $job2]);

        // then
        $this->assertLteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_LAST_ACCEPTED_AT_LTE,
            $job1->getLastAcceptedAt(),
            2
        );
        $this->assertGteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_LAST_ACCEPTED_AT_GTE,
            $job1->getLastAcceptedAt(),
            2
        );
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_revoked_at(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'revoked');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'revoked');
        $this->saveJobs([$job1, $job2]);

        // then
        $this->assertLteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_REVOKED_AT_LTE,
            $job1->getRevokedAt(),
            2
        );
        $this->assertGteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_REVOKED_AT_GTE,
            $job1->getRevokedAt(),
            2
        );
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_resolved_at(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'resolved');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'resolved');
        $this->saveJobs([$job1, $job2]);

        // then
        $this->assertLteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_RESOLVED_AT_LTE,
            $job1->getResolvedAt(),
            2
        );
        $this->assertGteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_RESOLVED_AT_GTE,
            $job1->getResolvedAt(),
            2
        );
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_sealed_at(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'resolved');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'resolved');
        $this->saveJobs([$job1, $job2]);

        // then
        // the job must be sealed after resolved
        $this->assertEquals(Job::SEALED_BECAUSE_RESOLVED, $job1->getSealedBecauseOf());
        $this->assertEquals(Job::SEALED_BECAUSE_RESOLVED, $job2->getSealedBecauseOf());
        $this->assertLteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_SEALED_AT_LTE,
            $job1->getResolvedAt(),
            2
        );
        $this->assertGteDateCriteriaWorksProperly(
            JobRepository::SIMPLE_CRITERIA_SEALED_AT_GTE,
            $job1->getResolvedAt(),
            2
        );
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_has_errors(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'rejected');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'accepted');
        $job3 = $this->buildJobInStep('7136af94-2393-4bf6-97c1-dc10a94a4aae', 'dispatched');
        $this->saveJobs([$job1, $job2, $job3]);

        // then
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_HAS_ERRORS => true]
        );
        $this->assertCount(1, $results);
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $results[0]->getJobId());

        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_HAS_ERRORS => false]
        );
        $this->assertCount(2, $results);
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_has_attempts(): void
    {
        // given
        $chainId1 = 'ef6c1ff6-1bf3-42d5-b69a-20c55541353d';
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'created');
        $job1->bindToChain($chainId1, 1);

        $chainId2 = 'fd5b00ad-39f8-4d53-ac2c-4f387808efd2';
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'created');
        $job2->bindToChain($chainId2, 2);

        $this->saveJobs([$job1, $job2]);

        // then
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_CHAIN_ID_EQ => $chainId1]
        );
        $this->assertCount(1, $results);
        $this->assertEquals($chainId1, $results[0]->getChainId());

        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_CHAIN_ID_EQ => $chainId2]
        );
        $this->assertCount(1, $results);
        $this->assertEquals($chainId2, $results[0]->getChainId());

        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_CHAIN_ID_EQ => 'unknown_chain_id']
        );
        $this->assertCount(0, $results);
    }

    /**
     * @test
     */
    public function it_finds_by_simple_criteria_chain_id(): void
    {
        // given
        $job1 = $this->buildJobInStep('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', 'created');
        $job2 = $this->buildJobInStep('a11d0109-1eb5-48e9-9eda-dedd706bc447', 'accepted');
        $job3 = $this->buildJobInStep('7136af94-2393-4bf6-97c1-dc10a94a4aae', 'dispatched');
        $this->saveJobs([$job1, $job2, $job3]);

        // then
        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_HAS_ATTEMPTS => true]
        );
        $this->assertCount(2, $results);
        $this->assertEquals('a11d0109-1eb5-48e9-9eda-dedd706bc447', $results[0]->getJobId());
        $this->assertEquals('7136af94-2393-4bf6-97c1-dc10a94a4aae', $results[1]->getJobId());

        $results = $this->repository->findBySimpleCriteria(
            [JobRepository::SIMPLE_CRITERIA_HAS_ATTEMPTS => false]
        );
        $this->assertCount(1, $results);
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $results[0]->getJobId());
    }

    private function assertLteDateCriteriaWorksProperly(
        string $criteriaName,
        \DateTimeImmutable $recordedMoment,
        int $totalJobsCount
    ): void {
        $after = $recordedMoment->add(new \DateInterval('PT10S'));
        $before = $recordedMoment->sub(new \DateInterval('PT10S'));

        // no records before
        $results = $this->repository->findBySimpleCriteria(
            [$criteriaName => $before]
        );
        $this->assertCount(0, $results);

        // all records after
        $results = $this->repository->findBySimpleCriteria(
            [$criteriaName => $after]
        );
        $this->assertCount($totalJobsCount, $results);
    }

    private function assertGteDateCriteriaWorksProperly(
        string $criteriaName,
        \DateTimeImmutable $recordedMoment,
        int $allCount
    ): void {
        $after = $recordedMoment->add(new \DateInterval('PT10S'));
        $before = $recordedMoment->sub(new \DateInterval('PT10S'));

        // all records before
        $results = $this->repository->findBySimpleCriteria(
            [$criteriaName => $before]
        );
        $this->assertCount($allCount, $results);

        // no records after
        $results = $this->repository->findBySimpleCriteria(
            [$criteriaName => $after]
        );
        $this->assertCount(0, $results);
    }

    private function buildJobInStep(string $jobId, string $step): Job
    {
        switch ($step) {
            case 'created':
                $now = new \DateTimeImmutable();

                return $this->provideJob(
                    $jobId,
                    $this->provideCommand($now),
                    new \DateTimeImmutable()
                );
            case 'dispatched':
                $job = self::buildJobInStep($jobId, 'created');
                $dispatchedAt = $job->getCreatedAt()->add(new \DateInterval('PT2S'));
                $job->dispatched(DispatchInfo::fromValues($dispatchedAt, 'long_id_1'));

                return $job;
            case 'accepted':
                $job = self::buildJobInStep($jobId, 'dispatched');
                $acceptedAt = $job->getLastDispatchedAt()->add(new \DateInterval('PT2S'));
                $job->accept(AcceptanceInfo::fromValues($acceptedAt, WorkerInfo::fromValues(1, 'worker_1')));

                return $job;
            case 'revoked':
                $job = self::buildJobInStep($jobId, 'accepted');
                $revokedAt = $job->getLastAcceptedAt()->add(new \DateInterval('PT2S'));
                $job->revoke($revokedAt, Job::REVOKED_BECAUSE_DEPLOYMENT);

                return $job;
            case 'resolved':
                $job = self::buildJobInStep($jobId, 'accepted');
                $resolvedAt = $job->getLastAcceptedAt()->add(new \DateInterval('PT2S'));
                $job->resolve($resolvedAt, ['some_result' => 'hello']);

                return $job;
            case 'rejected':
                $job = self::buildJobInStep($jobId, 'accepted');
                $rejectedAt = $job->getLastAcceptedAt()->add(new \DateInterval('PT2S'));
                $job->reject($rejectedAt, new \RuntimeException('Error'));

                return $job;
            default:
                throw new \RuntimeException(sprintf('Unknown step "%s"', $step));
        }
    }

    private function provideJob(string $jobId, TestingJobCommand $command, \DateTimeImmutable $createdAt): Job
    {
        return Job::initNew($jobId, $command, $createdAt);
    }

    private function provideCommand(\DateTimeImmutable $date): TestingJobCommand
    {
        return TestingJobCommand::fromValues(
            1000,
            'string',
            $date,
            [1, 2, 'string', $date]
        );
    }

    private function saveJobs(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->entityManager->persist($job);
        }
        $this->entityManager->flush();
    }
}
