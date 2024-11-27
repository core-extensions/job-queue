<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception\Tests;

use CoreExtensions\JobQueueBundle\Exception\JobOrphanException;
use PHPUnit\Framework\TestCase;

class JobOrphanExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_job(): void
    {
        // given // when
        $exception = JobOrphanException::withJobId('8e2a3cfc-eef8-44f4-96ed-99a6b1678266');

        // then
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $exception->getJobId());
        $this->assertEquals(
            sprintf(
                'Using orphan job command of job "%s"',
                '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
            ),
            $exception->getMessage()
        );
    }
}
