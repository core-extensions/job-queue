<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
use PHPUnit\Framework\TestCase;

class JobSealedInteractionExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_job(): void
    {
        // given // when
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
        $job->sealed(new \DateTimeImmutable(), Job::SEALED_BECAUSE_REVOKED);
        $exception = JobSealedInteractionException::fromJob($job, 'revoke');

        // then
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $exception->getJobId());
        $this->assertEquals(
            sprintf(
                'Failed to apply action "%s" to sealed job "%s" (because of %d))',
                'revoke',
                '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
                Job::SEALED_BECAUSE_REVOKED
            ),
            $exception->getMessage()
        );
    }
}
