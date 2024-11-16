<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Entity;

use CoreExtensions\JobQueueBundle\Entity\AcceptanceInfo;
use CoreExtensions\JobQueueBundle\Entity\DispatchInfo;
use CoreExtensions\JobQueueBundle\Entity\FailInfo;
use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use CoreExtensions\JobQueueBundle\Exception\JobBusinessLogicException;
use CoreExtensions\JobQueueBundle\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueueBundle\Exception\JobTimeoutExceededException;
use CoreExtensions\JobQueueBundle\Helpers;
use CoreExtensions\JobQueueBundle\JobConfiguration;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
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
    public function it_can_be_initiated(): void
    {
        $createdAt = new \DateTimeImmutable();
        $command = $this->provideCommand($createdAt);
        $job = $this->provideJob('99a01a56-3f9d-4bf1-b065-484455cc2847', $command, $createdAt);

        $this->assertEquals('99a01a56-3f9d-4bf1-b065-484455cc2847', $job->getJobId());
        $this->assertEquals(TestingJobCommand::JOB_TYPE, $job->getJobType());
        $this->assertNotNull($job->getJobCommand());
        $this->assertEquals($createdAt, $job->getCreatedAt());
        $this->assertNull($job->getLastDispatchedAt());
        $this->assertNull($job->lastDispatch());
        $this->assertNull($job->getLastAcceptedAt());
        $this->assertNull($job->lastAcceptance());
        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getRevokedFor());
        $this->assertNull($job->getRevokeAcceptedAt());
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
        $job->dispatched(DispatchInfo::fromValues($dispatchedAt, 'random_string_id'));

        $this->assertEquals($dispatchedAt, $job->getLastDispatchedAt());
        $this->assertEquals('random_string_id', $job->lastDispatch()->messageId());
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
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id_1'));
        $job->accepted(AcceptanceInfo::fromValues($acceptedAt, $workerInfo));

        $this->assertEquals('long_string_id_1', $job->lastDispatch()->messageId());

        $this->assertEquals($acceptedAt, $job->getLastAcceptedAt());
        $this->assertEquals($acceptedAt, $job->lastAcceptance()->acceptedAt());
        $this->assertEquals($workerInfo->toArray(), $job->lastAcceptance()->workerInfo()->toArray());
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
        $job->revoked($revokedAt, Job::REVOKED_DUE_DEPLOYMENT);

        $this->assertEquals($revokedAt, $job->getRevokedAt());
        $this->assertEquals(Job::REVOKED_DUE_DEPLOYMENT, $job->getRevokedFor());
        // not confirmed yet
        $this->assertNull($job->getRevokeAcceptedAt());
    }

    /**
     * @test
     */
    public function it_can_be_revoke_confirmed(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $job->revoked(new \DateTimeImmutable(), Job::REVOKED_DUE_DEPLOYMENT);

        $revokeConfirmedAt = new \DateTimeImmutable();
        $job->revokeConfirmed($revokeConfirmedAt);

        $this->assertEquals($revokeConfirmedAt, $job->getRevokeAcceptedAt());

        // still revoked
        $this->assertNotNull($job->getRevokedAt());
        $this->assertEquals(Job::REVOKED_DUE_DEPLOYMENT, $job->getRevokedFor());

        // sealed now
        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_DUE_REVOKED_AND_CONFIRMED, $job->getSealedDue());
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
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'some_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

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
        $failInfo = FailInfo::fromThrowable(
            $failedAt,
            new JobBusinessLogicException('Some description', 10, new \RuntimeException('Previous'))
        );

        // job must be dispatched and accepted before
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'some_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $job->failed($failInfo);

        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getResult());
        $this->assertEquals(Helpers::serializeDateTime($failedAt), $job->getErrors()[0]['failedAt']);
        $this->assertEquals(1, $job->getAttemptsCount());
        $this->assertEquals($failInfo->toArray(), $job->getErrors()[0]);
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
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'some_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $failedAt1 = new \DateTimeImmutable();
        $error1 = FailInfo::fromThrowable(
            $failedAt1,
            new JobBusinessLogicException('Some description 1', 10, new \RuntimeException('Previous 1'))
        );
        $job->failed($error1);

        $failedAt2 = new \DateTimeImmutable();
        $error2 = FailInfo::fromThrowable(
            $failedAt2,
            new JobBusinessLogicException('Some description 2', 10, new \RuntimeException('Previous 2'))
        );
        $job->failed($error2);

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
        $job->failed($error3);

        // sealed only after attempts not reached
        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_DUE_FAILED_BY_MAX_RETRIES_REACHED, $job->getSealedDue());
    }

    // /**
    //  * @test
    //  */
    // public function it_can_be_sealed(): void
    // {
    //     $job = $this->job;
    //     $job->sealed(new \DateTimeImmutable(), Job::SEALED_DUE_FAILED_TIMEOUT);
    //
    //     $this->assertNotNull($job->getSealedAt());
    //     $this->assertNotNull($job->getSealedDue());
    // }

    /**
     * @test
     */
    public function it_yells_when_dispatching_sealed(): void
    {
        $job = $this->job;

        // making sealed through failing
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        // every action should throw exception if job is sealed
        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|dispatched|is');
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
    }

    /**
     * @test
     */
    public function it_yells_when_accepting_sealed(): void
    {
        $job = $this->job;

        // making sealed through failing
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|accepted|is');
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
    }

    /**
     * @test
     */
    public function it_yells_when_revoking_sealed(): void
    {
        $job = $this->job;

        // making sealed through failing
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|revoked|is');
        $job->revoked(new \DateTimeImmutable(), Job::REVOKED_DUE_DEPLOYMENT);
    }

    /**
     * @test
     */
    public function it_yells_when_revoke_confirming_sealed(): void
    {
        $job = $this->job;

        // making sealed through failing
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->revoked(new \DateTimeImmutable(), Job::REVOKED_DUE_DEPLOYMENT);
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|revokeConfirmed|is');
        $job->revokeConfirmed(new \DateTimeImmutable());
    }

    /**
     * @test
     */
    public function it_yells_when_resolving_sealed(): void
    {
        $job = $this->job;
        // workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worked_1')));

        // making sealed through failing
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|resolved|is');
        $job->resolved(new \DateTimeImmutable(), ['custom_result' => 1]);
    }

    /**
     * @test
     */
    public function it_yells_when_failing_sealed(): void
    {
        $job = $this->job;
        // workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worked_1')));

        // making sealed through failing
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|failed|is');
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new \RuntimeException('Hello'))
        );
    }

    /**
     * @test
     */
    public function it_yells_when_binding_to_chain_sealed(): void
    {
        $job = $this->job;

        // making sealed through failing
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worked_1')));
        $job->failed(
            FailInfo::fromThrowable(new \DateTimeImmutable(), new JobTimeoutExceededException())
        );

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|bindToChain|is');
        $job->bindToChain('1ab7d89d-fa15-4fb4-823b-4c3f08a16df3', 1);
    }

    /** @noinspection PhpSameParameterValueInspection */
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
}
