<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AppTest extends KernelTestCase
{
    /**
     * @test
     *
     * @throws \Throwable
     */
    public function it_can_work_together(): void
    {
        $this->assertTrue(true);
    }
}
