<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests\Entity;

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\ErrorInfo;
use CoreExtensions\JobQueue\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueue\JobConfiguration;
use CoreExtensions\JobQueue\Tests\TestingJobCommand;
use CoreExtensions\JobQueue\WorkerInfo;
use PHPUnit\Framework\TestCase;

// TODO: throws_exceptions tests
// TODO: chain workflow tests
// TODO: sealed throw
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
        $this->assertNull($job->getResolvedAt());
        $this->assertNull($job->getRevokedFor());
        $this->assertNull($job->getRevokeAcceptedAt());
        $this->assertNull($job->getWorkerInfo());
        $this->assertNull($job->getChainId());
        $this->assertNull($job->getChainPosition());
        $this->assertNull($job->getResult());
        $this->assertNull($job->getErrors());
        $this->assertEquals(JobConfiguration::default()->toArray(), $job->getJobConfiguration()); // using default conf
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

        $resolvedAt = new \DateTimeImmutable();
        $job->resolved($resolvedAt, $result);

        $this->assertEquals($resolvedAt, $job->getResolvedAt());
        $this->assertEquals($result, $job->getResult());
    }

    /**
     * @test
     */
    public function it_yell_when_interaction_with_sealed(): void
    {
        $job = $this->job;
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_DUE_TIMEOUT);

        $this->expectException(JobSealedInteractionException::class);
        $job->dispatched(new \DateTimeImmutable(), 'string_id');

        $this->expectException(JobSealedInteractionException::class);
        $job->revoked(new \DateTimeImmutable(), Job::REVOKED_FOR_RE_RUN);

        $this->expectException(JobSealedInteractionException::class);
        $job->resolved(new \DateTimeImmutable(), ['custom_result' => 1]);

        $this->expectException(JobSealedInteractionException::class);
        $job->failed(new \DateTimeImmutable(), ErrorInfo::fromValues(1, 'message', 1, 'file', 'previous_message'));

        $this->expectException(JobSealedInteractionException::class);
        $job->bindToChain('1ab7d89d-fa15-4fb4-823b-4c3f08a16df3', 1);

        $this->expectException(JobSealedInteractionException::class);
        $job->bindWorkerInfo(WorkerInfo::fromValues(123, 'app'));
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
