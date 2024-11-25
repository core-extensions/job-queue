<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Service;

use CoreExtensions\JobQueueBundle\Service\WorkerInfoResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class WorkerInfoResolverTest extends TestCase
{
    private WorkerInfoResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new WorkerInfoResolver();
    }

    /**
     * @test
     */
    public function it_resolves_worker_info_from_received_stamp(): void
    {
        // given
        $receivedStamp = new ReceivedStamp('async');

        // when
        $workerInfo = $this->resolver->resolveWorkerInfo($receivedStamp);

        // then
        $this->assertGreaterThan(0, $workerInfo->pid());
        $this->assertEquals('async', $workerInfo->name());
    }
}
