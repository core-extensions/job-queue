<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests\Entity;

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\Tests\TestingJobCommand;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
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
        $this->assertNull($job->getCanceledAcceptedAt());
        $this->assertNull($job->getCanceledFor());
        $this->assertNull($job->getCanceledAcceptedAt());
        $this->assertNull($job->getWorkerInfo());
        $this->assertNull($job->getChainId());
        $this->assertNull($job->getChainPosition());
        $this->assertNull($job->getResult());
        $this->assertNull($job->getError());
        $this->assertNull($job->getRetryOptions());
    }

    /**
     * @test
     */
    public function it_binds_job_to_command_when_initiated(): void
    {
        $command = $this->provideCommand(new \DateTimeImmutable());
        $job = $this->provideJob('99a01a56-3f9d-4bf1-b065-484455cc2847', $command, new \DateTimeImmutable());

        $this->assertEquals('99a01a56-3f9d-4bf1-b065-484455cc2847', $command->getJobId());
    }

    /**
     * TODO: throws_exceptions
     *
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
    public function it_can_be_canceled(): void
    {
        $job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );

        $canceledAt = new \DateTimeImmutable();
        $job->canceled($canceledAt, Job::CANCELED_FOR_RERUN);

        $this->assertEquals($canceledAt, $job->getCanceledAt());
        $this->assertEquals(Job::CANCELED_FOR_RERUN, $job->getCanceledFor());
    }


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
