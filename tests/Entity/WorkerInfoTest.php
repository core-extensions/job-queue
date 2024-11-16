<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Entity;

use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use PHPUnit\Framework\TestCase;

final class WorkerInfoTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_from_values(): void
    {
        $workerInfo = WorkerInfo::fromValues(1234, 'worker_name');

        self::assertSame(1234, $workerInfo->pid());
        self::assertSame('worker_name', $workerInfo->name());
    }

    /**
     * @test
     */
    public function it_serializes_and_deserializes(): void
    {
        $originalWorkerInfo = WorkerInfo::fromValues(1234, 'worker_name');

        $array = $originalWorkerInfo->toArray();
        $reconstructedWorkerInfo = WorkerInfo::fromArray($array);

        self::assertSame($originalWorkerInfo->pid(), $reconstructedWorkerInfo->pid());
        self::assertSame($originalWorkerInfo->name(), $reconstructedWorkerInfo->name());
    }

    /**
     * @test
     */
    public function it_throws_exception_on_non_positive_pid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkerInfo::fromValues(0, 'worker_name');
    }

    /**
     * @test
     */
    public function it_throws_exception_on_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkerInfo::fromValues(1234, '');
    }
}
