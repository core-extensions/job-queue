<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests\Entity;

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\FailInfo;
use CoreExtensions\JobQueue\Exception\JobBusinessLogicException;
use CoreExtensions\JobQueue\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueue\Helpers;
use CoreExtensions\JobQueue\JobConfiguration;
use CoreExtensions\JobQueue\Tests\TestingJobCommand;
use CoreExtensions\JobQueue\WorkerInfo;
use PHPUnit\Framework\TestCase;

// TODO: throws_exceptions tests
// TODO: chain workflow tests
final class JobTest extends TestCase
{
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );
    }

    /**
     * @test
     */
    public function it_can_initiated(): void
    {
        $createdAt = new \DateTimeImmutable();
        $command = $this->provideCommand($createdAt);
        $job = $this->provideJob('99a01a56-3f9d-4bf1-b065-484455cc2847', $command, $createdAt);

        $this->assertEquals('99a01a56-3f9d-4bf1-b065-484455cc2847', $job->getJobId());
        $this->assertEquals(TestingJobCommand::JOB_TYPE, $job->getJobType());
        $this->assertNotNull($job->getJobCommand());
        $this->assertEquals($createdAt, $job->getCreatedAt());
        $this->assertNull($job->getDispatchedAt());
        $this->assertNull($job->getDispatchedMessageId());
        $this->assertNull($job->getAcceptedAt());
        $this->assertNull($job->getWorkerInfo());
        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getRevokedFor());
        $this->assertNull($job->getRevokeConfirmedAt());
        $this->assertNull($job->getChainId());
        $this->assertNull($job->getChainPosition());
        $this->assertNull($job->getResult());
        $this->assertNull($job->getErrors());
        $this->assertEquals(JobConfiguration::default()->toArray(), $job->getJobConfiguration()); // using default conf
    }

    /**
     * @test
     */
    public function it_binds_job_to_command_when_initiated(): void
    {
        $command = $this->provideCommand(new \DateTimeImmutable());
        Job::initNew('99a01a56-3f9d-4bf1-b065-484455cc2847', $command, new \DateTimeImmutable());

        $this->assertEquals('99a01a56-3f9d-4bf1-b065-484455cc2847', $command->getJobId());
    }

    /**
     * @test
     */
    public function it_can_be_dispatched(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $dispatchedAt = new \DateTimeImmutable();
        $job->dispatched($dispatchedAt, 'random_string_id');

        $this->assertEquals($dispatchedAt, $job->getDispatchedAt());
        $this->assertEquals('random_string_id', $job->getDispatchedMessageId());
    }


    /**
     * @test
     */
    public function it_can_be_accepted(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $acceptedAt = new \DateTimeImmutable();
        $workerInfo = WorkerInfo::fromValues(2, 'worker_2');

        // job must be dispatched before
        $job->dispatched(new \DateTimeImmutable(), 'random_string_id');
        $job->accepted($acceptedAt, $workerInfo);

        $this->assertEquals($acceptedAt, $job->getAcceptedAt());
        $this->assertEquals($workerInfo->toArray(), $job->getWorkerInfo());
    }

    /**
     * @test
     */
    public function it_can_be_revoked(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $revokedAt = new \DateTimeImmutable();
        $job->revoked($revokedAt, Job::REVOKED_FOR_RE_RUN);

        $this->assertEquals($revokedAt, $job->getRevokedAt());
        $this->assertEquals(Job::REVOKED_FOR_RE_RUN, $job->getRevokedFor());
    }

    /**
     * @test
     */
    public function it_can_be_resolved(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $result = [
            'example_int' => 2,
            'example_string' => 'Hello',
            'example_date' => new \DateTimeImmutable(),
        ];

        // job must be dispatched and accepted before
        $job->dispatched(new \DateTimeImmutable(), 'some_string_id');
        $job->accepted(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1'));

        $resolvedAt = new \DateTimeImmutable();
        $job->resolved($resolvedAt, $result);

        $this->assertEquals($resolvedAt, $job->getResolvedAt());
        $this->assertEquals($result, $job->getResult());
        $this->assertEquals(1, $job->getAttemptsCount());
        $this->assertNull($job->getErrors());

        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_DUE_RESOLVED, $job->getSealedDue());
    }

    /**
     * @test
     */
    public function it_can_be_failed(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $failedAt = new \DateTimeImmutable();
        $error = FailInfo::fromThrowable(
            $failedAt,
            new JobBusinessLogicException('Some description', 10, new \RuntimeException('Previous'))
        );

        // job must be dispatched and accepted before
        $job->dispatched(new \DateTimeImmutable(), 'some_string_id');
        $job->accepted(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1'));

        $job->failed($failedAt, $error);

        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getResult());
        $this->assertEquals(Helpers::serializeDateTime($failedAt), $job->getErrors()[0]['failedAt']);
        $this->assertEquals(1, $job->getAttemptsCount());
        $this->assertEquals($error->toArray(), $job->getErrors()[0]);
    }

    /**
     * @test
     */
    public function it_can_be_failed_many_times(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );
        $job->configure(JobConfiguration::default()->withMaxRetries(3));

        // job must be dispatched and accepted before
        $job->dispatched(new \DateTimeImmutable(), 'some_string_id');
        $job->accepted(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1'));

        $failedAt1 = new \DateTimeImmutable();
        $error1 = FailInfo::fromThrowable(
            $failedAt1,
            new JobBusinessLogicException('Some description 1', 10, new \RuntimeException('Previous 1'))
        );
        $job->failed($failedAt1, $error1);

        $failedAt2 = new \DateTimeImmutable();
        $error2 = FailInfo::fromThrowable(
            $failedAt2,
            new JobBusinessLogicException('Some description 2', 10, new \RuntimeException('Previous 2'))
        );
        $job->failed($failedAt2, $error2);

        // can't be resolved
        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getResult());

        // properly counts attempts
        $this->assertEquals(2, $job->getAttemptsCount());
        $this->assertCount(2, $job->getErrors());

        // not sealed before attempts not reached
        $this->assertNull($job->getSealedAt());
        $this->assertNull($job->getSealedDue());

        $failedAt3 = new \DateTimeImmutable();
        $error3 = FailInfo::fromThrowable(
            $failedAt3,
            new JobBusinessLogicException('Some description 3', 10, new \RuntimeException('Previous 3'))
        );
        $job->failed($failedAt3, $error3);

        // sealed only after attempts not reached
        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_DUE_MAX_RETRIES_REACHED, $job->getSealedDue());
    }

    /**
     * @test
     */
    public function it_can_be_sealed(): void
    {
        $job = $this->job;
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_DUE_TIMEOUT);

        $this->assertNotNull($job->getSealedAt());
        $this->assertNotNull($job->getSealedDue());
    }


    /**
     * @test
     */
    public function it_yell_when_interaction_with_sealed(): void
    {
        $job = $this->job;
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_DUE_TIMEOUT);

        // every action should throw exception if job is sealed
        $this->expectException(JobSealedInteractionException::class);
        $job->dispatched(new \DateTimeImmutable(), 'string_id');

        $this->expectException(JobSealedInteractionException::class);
        $job->accepted(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1'));

        $this->expectException(JobSealedInteractionException::class);
        $job->revoked(new \DateTimeImmutable(), Job::REVOKED_FOR_RE_RUN);

        $this->expectException(JobSealedInteractionException::class);
        $job->revokeConfirmed(new \DateTimeImmutable());

        $this->expectException(JobSealedInteractionException::class);
        $job->resolved(new \DateTimeImmutable(), ['custom_result' => 1]);

        $this->expectException(JobSealedInteractionException::class);
        $job->failed(
            new \DateTimeImmutable(),
            FailInfo::fromThrowable(new \DateTimeImmutable(), new \RuntimeException('Hello'))
        );

        $this->expectException(JobSealedInteractionException::class);
        $job->bindToChain('1ab7d89d-fa15-4fb4-823b-4c3f08a16df3', 1);
    }

    // TODO: chain tests

    /** @noinspection PhpSameParameterValueInspection */
    private function provideJob(
        string $jobId,
        TestingJobCommand $command,
        \DateTimeImmutable $createdAt
    ): Job {
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
}
