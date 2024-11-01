<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests;

use CoreExtensions\JobQueue\Entity\Job;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_initiated(): void
    {
        $date = new \DateTimeImmutable();
        $command = TestingJobCommand::fromArray([
            'int' => 1000,
            'string' => 'hello',
            'date' => $date, // serialize?
            'array' => [1, 2, 'string', $date],
        ]);
        $job = Job::initNew('99a01a56-3f9d-4bf1-b065-484455cc2847', $command, $date);
    }
}
