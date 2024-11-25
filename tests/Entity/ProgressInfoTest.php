<?php

declare(strict_types=1);

namespace Tests\CoreExtensions\JobQueueBundle\Entity;

use CoreExtensions\JobQueueBundle\Entity\ProgressInfo;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\InvalidArgumentException as AssertionException;

class ProgressInfoTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_incremented(): void
    {
        $progress = ProgressInfo::withTotalItems(10);
        $newProgress = $progress->increment(3);

        $this->assertEquals(3, $newProgress->processedItems());
        $this->assertEquals(10, $newProgress->totalItems());

        // original object should remain unchanged
        $this->assertEquals(0, $progress->processedItems());
    }

    /**
     * @test
     */
    public function it_correctly_calculates_percent(): void
    {
        $progress = ProgressInfo::withTotalItems(100);

        $progress = $progress->increment(25);
        $this->assertEquals(25.0, $progress->percentage());

        $progress = $progress->increment(5);
        $this->assertEquals(30.0, $progress->percentage());

        $progress = $progress->increment(69);
        $this->assertEquals(99.0, $progress->percentage());

        $progress = $progress->increment(1);
        $this->assertEquals(100.0, $progress->percentage());
    }

    /**
     * @test
     */
    public function it_yells_if_total_is_not_positive_number(): void
    {
        $this->expectException(AssertionException::class);
        ProgressInfo::withTotalItems(-1);
    }

    /**
     * @test
     */
    public function it_yells_if_total_is_zero(): void
    {
        $this->expectException(AssertionException::class);
        ProgressInfo::withTotalItems(0);
    }
}
