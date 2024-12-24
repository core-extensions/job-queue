<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Exception;

use CoreExtensions\JobQueueBundle\Exception\TerminateJobException;
use PHPUnit\Framework\TestCase;

class TerminateJobExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_job(): void
    {
        // given // when
        $exception = TerminateJobException::becauseOfAssertionFails(
            '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
            new \Webmozart\Assert\InvalidArgumentException('Webmozart assertion exception')
        );

        // then
        $this->assertEquals('8e2a3cfc-eef8-44f4-96ed-99a6b1678266', $exception->getJobId());
        $this->assertEquals(
            sprintf(
                'Job "%s" terminated by assertion fail with error "%s".',
                '8e2a3cfc-eef8-44f4-96ed-99a6b1678266',
                'Webmozart assertion exception'
            ),
            $exception->getMessage()
        );
    }
}
