<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception\Tests;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobRevokedException;
use CoreExtensions\JobQueueBundle\Serializer;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
use PHPUnit\Framework\TestCase;

class JobRevokedExceptionTest extends TestCase
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
        $job->revoke(new \DateTimeImmutable(), Job::REVOKED_BECAUSE_DEPLOYMENT);


        // when
        $exception = JobRevokedException::fromJob($job);

        // then
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $exception->getJobId());
        $this->assertEquals(
            sprintf(
                'Job "%s" already revoked at "%s" (for %d)',
                '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
                Serializer::serializeDateTime($job->getRevokedAt()),
                Job::REVOKED_BECAUSE_DEPLOYMENT
            ),
            $exception->getMessage()
        );
    }
}
