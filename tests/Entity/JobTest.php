<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Entity;

use _PHPStan_09f7c00bc\Nette\Utils\AssertionException;
use CoreExtensions\JobQueueBundle\Entity\AcceptanceInfo;
use CoreExtensions\JobQueueBundle\Entity\DispatchInfo;
use CoreExtensions\JobQueueBundle\Entity\FailInfo;
use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Entity\ProgressInfo;
use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use CoreExtensions\JobQueueBundle\Exception\JobExpiredException;
use CoreExtensions\JobQueueBundle\Exception\JobRetryableExceptionInterface;
use CoreExtensions\JobQueueBundle\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueueBundle\Exception\JobTerminatedException;
use CoreExtensions\JobQueueBundle\JobConfiguration;
use CoreExtensions\JobQueueBundle\Serializer;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    private \DateTimeImmutable $now;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new \DateTimeImmutable();
        $this->job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand($this->now),
            $this->now
        );
    }

    /**
     * @test
     */
    public function it_can_be_initiated(): void
    {
        $createdAt = $this->now;
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

        $this->assertEquals('99a01a56-3f9d-4bf1-b065-484455cc2847', $command->jobId());
    }

    /**
     * @test
     */
    public function it_can_be_dispatched(): void
    {
        $job = $this->job;

        $dispatchedAt = new \DateTimeImmutable();
        $job->dispatched(DispatchInfo::fromValues($dispatchedAt, 'random_string_id'));

        $this->assertEquals($dispatchedAt, $job->getLastDispatchedAt());
        $this->assertEquals('random_string_id', $job->lastDispatch()->messageId());

        // dispatch should increment attempts count
        $this->assertEquals(1, $job->getAttemptsCount());
    }

    /**
     * @test
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function it_can_be_accepted(): void
    {
        $job = $this->job;
        $createdAt = $this->now;

        // dispatched after one second
        $dispatchedAt = $createdAt->add(new \DateInterval('PT1S'));
        $job->dispatched(DispatchInfo::fromValues($dispatchedAt, 'long_string_id'));

        // no errors if timeout is not reached
        $timeout = $job->jobConfiguration()->timeout();
        $acceptedAt = $dispatchedAt->add(new \DateInterval('PT'.($timeout - 1).'S'));
        $workerInfo = WorkerInfo::fromValues(2, 'worker_2');

        $job->accept(AcceptanceInfo::fromValues($acceptedAt, $workerInfo));

        $this->assertEquals('long_string_id', $job->lastDispatch()->messageId());

        $this->assertEquals($acceptedAt, $job->getLastAcceptedAt());
        $this->assertEquals($acceptedAt, $job->lastAcceptance()->acceptedAt());
        $this->assertEquals($workerInfo->toArray(), $job->lastAcceptance()->workerInfo()->toArray());
    }

    /**
     * @test
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function it_yells_if_expired_accept(): void
    {
        $job = $this->job;
        $createdAt = $this->now;

        // dispatched after one second
        $dispatchedAt = $createdAt->add(new \DateInterval('PT1S'));
        $job->dispatched(DispatchInfo::fromValues($dispatchedAt, 'long_string_id'));

        $this->expectException(JobExpiredException::class);

        // accept at time far enough in the future to exceed timeout
        $timeout = $job->jobConfiguration()->timeout();
        $acceptedAt = $dispatchedAt->add(new \DateInterval('PT'.$timeout.'S'));
        $job->accept(AcceptanceInfo::fromValues($acceptedAt, WorkerInfo::fromValues(2, 'worker_2')));
    }

    /**
     * @test
     */
    public function it_can_be_revoked(): void
    {
        $job = $this->job;

        $revokedAt = new \DateTimeImmutable();
        $job->revoke($revokedAt, Job::REVOKED_BECAUSE_DEPLOYMENT);

        $this->assertEquals($revokedAt, $job->getRevokedAt());
        $this->assertEquals(Job::REVOKED_BECAUSE_DEPLOYMENT, $job->getRevokedFor());
        // not confirmed yet
        $this->assertNull($job->getRevokeAcceptedAt());
    }

    /**
     * @test
     */
    public function it_can_be_revoke_confirmed(): void
    {
        $job = $this->job;

        $job->revoke(new \DateTimeImmutable(), Job::REVOKED_BECAUSE_DEPLOYMENT);

        $revokeConfirmedAt = new \DateTimeImmutable();
        $job->confirmRevoke($revokeConfirmedAt);

        $this->assertEquals($revokeConfirmedAt, $job->getRevokeAcceptedAt());

        // still revoked
        $this->assertNotNull($job->getRevokedAt());
        $this->assertEquals(Job::REVOKED_BECAUSE_DEPLOYMENT, $job->getRevokedFor());

        // sealed now
        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_BECAUSE_REVOKED, $job->getSealedBecauseOf());
    }


    /**
     * @test
     */
    public function it_can_be_resolved(): void
    {
        $job = $this->job;

        $result = [
            'example_int' => 2,
            'example_string' => 'Hello',
            'example_date' => new \DateTimeImmutable(),
        ];

        // job must be dispatched and accepted before
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'some_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $resolvedAt = new \DateTimeImmutable();
        $job->resolve($resolvedAt, $result);

        $this->assertEquals($resolvedAt, $job->getResolvedAt());
        $this->assertEquals($result, $job->getResult());
        $this->assertEquals(1, $job->getAttemptsCount());
        $this->assertNull($job->getErrors());

        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_BECAUSE_RESOLVED, $job->getSealedBecauseOf());
    }

    /**
     * @test
     */
    public function it_can_be_failed(): void
    {
        $job = $this->job;

        $failedAt = new \DateTimeImmutable();
        $error = new JobTerminatedException('Some description', 10, new \RuntimeException('Previous'));

        // job must be dispatched and accepted before
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'some_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $job->reject($failedAt, $error);

        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getResult());
        $this->assertEquals(Serializer::serializeDateTime($failedAt), $job->getErrors()[0]['failedAt']);
        $this->assertEquals(1, $job->getAttemptsCount());
        $this->assertEquals(FailInfo::fromThrowable($failedAt, $error)->toArray(), $job->getErrors()[0]);
    }

    /**
     * @test
     */
    public function it_can_be_failed_many_times(): void
    {
        $job = $this->job;
        $job->configure(JobConfiguration::default()->withMaxRetries(3));

        // job must be dispatched and accepted before
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'message_id_1'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $failedAt1 = new \DateTimeImmutable();
        $retryableError1 = new class ('Definitely retryable') extends \RuntimeException implements
            JobRetryableExceptionInterface {
        };
        $job->reject($failedAt1, $retryableError1);

        // retry emulation
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'message_id_2'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $failedAt2 = new \DateTimeImmutable();
        $error2 = new \InvalidArgumentException('Retryable by default', 10, new \RuntimeException('Previous 2'));

        $job->reject($failedAt2, $error2);

        // can't be resolved
        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getResult());

        // properly counts attempts
        $this->assertEquals(2, $job->getAttemptsCount());
        $this->assertCount(2, $job->getErrors());

        // not sealed before attempts not reached
        $this->assertNull($job->getSealedAt());
        $this->assertNull($job->getSealedBecauseOf());

        $failedAt3 = new \DateTimeImmutable();
        $error3 = new JobTerminatedException('Some description 3', 10, new \RuntimeException('Previous 3'));
        $job->reject($failedAt3, $error3);
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
    public function it_can_refresh_progress(): void
    {
        $job = $this->job;
        $progressInfo = ProgressInfo::withTotalItems(1000);

        $job->refreshProgress($progressInfo);

        // it correctly sets progress
        $this->assertEquals($progressInfo->toArray(), $job->progress()->toArray());

        // workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'some_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        // progress hasn't been changed yet
        $this->assertEquals($progressInfo->toArray(), $job->progress()->toArray());

        $job->refreshProgress($job->progress()->increment(250));
        $this->assertEquals(25, round($job->progress()->percentage()));

        $job->refreshProgress($job->progress()->increment(10));
        $this->assertEquals(26, round($job->progress()->percentage()));

        $job->refreshProgress($job->progress()->increment(740));
        $this->assertEquals(100, round($job->progress()->percentage()));

        // it yells if set progress more than 100%
        $this->expectException(\Webmozart\Assert\InvalidArgumentException::class);
        $job->refreshProgress($job->progress()->increment(1));
    }

    /**
     * @test
     */
    public function it_yells_if_sealed_dispatch(): void
    {
        $job = $this->job;

        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

        // every action should throw exception if job is sealed
        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|dispatched|is');
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
    }

    /**
     * @test
     */
    public function it_yells_if_sealed_accept(): void
    {
        $job = $this->job;

        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|accept|is');
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
    }

    /**
     * @test
     */
    public function it_yells_if_sealed_revoke(): void
    {
        $job = $this->job;

        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|revoke|is');
        $job->revoke(new \DateTimeImmutable(), Job::REVOKED_BECAUSE_DEPLOYMENT);
    }

    /**
     * @test
     */
    public function it_yells_if_sealed_confirm_revoke(): void
    {
        $job = $this->job;

        // making sealed through failing
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->revoke(new \DateTimeImmutable(), Job::REVOKED_BECAUSE_DEPLOYMENT);
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|confirmRevoke|is');
        $job->confirmRevoke(new \DateTimeImmutable());
    }

    /**
     * @test
     */
    public function it_yells_if_sealed_resolve(): void
    {
        $job = $this->job;
        // workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worked_1')));
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|resolve|is');
        $job->resolve(new \DateTimeImmutable(), ['custom_result' => 1]);
    }

    /**
     * @test
     */
    public function it_yells_when_failing_sealed(): void
    {
        $job = $this->job;
        // workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worked_1')));
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

        $this->expectException(JobSealedInteractionException::class);
        $this->expectExceptionMessageMatches('|reject|is');
        $job->reject(new \DateTimeImmutable(), new \RuntimeException('Hello'));
    }

    /**
     * @test
     */
    public function it_yells_if_sealed_bind_to_chain(): void
    {
        $job = $this->job;

        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'string_id'));
        $job->accept(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worked_1')));
        $job->reject(new \DateTimeImmutable(), new JobExpiredException());
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_EXPIRED);

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
