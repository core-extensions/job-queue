<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Exception;

use CoreExtensions\JobQueueBundle\Exception\UnsupportedJobTypeException;
use PHPUnit\Framework\TestCase;

class UnsupportedJobTypeExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_job(): void
    {
        // given // when
        $exception = new UnsupportedJobTypeException('invalid_type');

        // then
        $this->assertEquals(sprintf('Unsupported job type: "%s"', 'invalid_type'), $exception->getMessage());
    }
}
