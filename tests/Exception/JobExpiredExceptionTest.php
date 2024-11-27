<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception\Tests;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobExpiredException;
use CoreExtensions\JobQueueBundle\JobConfiguration;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
use PHPUnit\Framework\TestCase;

class JobExpiredExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_job(): void
    {
        // given
        $job = Job::initNew(
            '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
            TestingJobCommand::fromValues(
                1000,
                'string',
                new \DateTimeImmutable(),
                [1, 2, 'string', new \DateTimeImmutable()]
            ),
            new \DateTimeImmutable()
        );

        $job->configure(JobConfiguration::default()->withTimeout(1000));

        // when
        $exception = JobExpiredException::fromJob($job);

        // then
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $exception->getJobId());
        $this->assertEquals(
            sprintf(
                'Job "%s" failed because of timeout exceed (timeout %d sec))',
                '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
                1000
            ),
            $exception->getMessage()
        );
    }
}
