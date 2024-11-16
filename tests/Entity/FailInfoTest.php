<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Entity;

use CoreExtensions\JobQueueBundle\Entity\FailInfo;
use CoreExtensions\JobQueueBundle\Helpers;
use PHPUnit\Framework\TestCase;

final class FailInfoTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_be_created_from_throwable(): void
    {
        $failedAt = new \DateTimeImmutable();
        $exception = new \RuntimeException('Test error', 100);

        $failInfo = FailInfo::fromThrowable($failedAt, $exception);

        self::assertSame(100, $failInfo->errorCode());
        self::assertSame('Test error', $failInfo->errorMessage());
        self::assertSame($failedAt, $failInfo->failedAt());
        self::assertSame($exception->getLine(), $failInfo->errorLine());
        self::assertSame($exception->getFile(), $failInfo->errorFile());
        self::assertNull($failInfo->previousErrorCode());
        self::assertNull($failInfo->previousErrorMessage());
    }

    /**
     * @test
     */
    public function it_handles_previous_exception(): void
    {
        $failedAt = new \DateTimeImmutable();
        $previousException = new \LogicException('Previous error', 200);
        $exception = new \RuntimeException('Main error', 100, $previousException);

        $failInfo = FailInfo::fromThrowable($failedAt, $exception);

        self::assertSame(100, $failInfo->errorCode());
        self::assertSame('Main error', $failInfo->errorMessage());
        self::assertSame(200, $failInfo->previousErrorCode());
        self::assertSame('Previous error', $failInfo->previousErrorMessage());
    }

    /**
     * @test
     */
    public function it_serializes_and_deserializes(): void
    {
        $failedAt = new \DateTimeImmutable();
        $originalFailInfo = FailInfo::fromThrowable(
            $failedAt,
            new \RuntimeException('Test error', 100)
        );

        $array = $originalFailInfo->toArray();
        $reconstructedFailInfo = FailInfo::fromArray($array);

        self::assertEquals(
            Helpers::serializeDateTime($failedAt),
            Helpers::serializeDateTime($reconstructedFailInfo->failedAt())
        );
        self::assertSame($originalFailInfo->errorCode(), $reconstructedFailInfo->errorCode());
        self::assertSame($originalFailInfo->errorMessage(), $reconstructedFailInfo->errorMessage());
        self::assertSame($originalFailInfo->errorLine(), $reconstructedFailInfo->errorLine());
        self::assertSame($originalFailInfo->errorFile(), $reconstructedFailInfo->errorFile());
        self::assertSame($originalFailInfo->previousErrorCode(), $reconstructedFailInfo->previousErrorCode());
        self::assertSame($originalFailInfo->previousErrorMessage(), $reconstructedFailInfo->previousErrorMessage());
    }

    /**
     * @test
     */
    public function it_yells_on_negative_error_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FailInfo::fromArray([
            'failedAt' => Helpers::serializeDateTime(new \DateTimeImmutable()),
            'errorCode' => -1,
            'errorMessage' => 'Test',
            'errorLine' => 1,
            'errorFile' => __FILE__,
            'previousErrorCode' => null,
            'previousErrorMessage' => null,
        ]);
    }

    /**
     * @test
     */
    public function it_yells_on_zero_error_line(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FailInfo::fromArray([
            'failedAt' => Helpers::serializeDateTime(new \DateTimeImmutable()),
            'errorCode' => 1,
            'errorMessage' => 'Test',
            'errorLine' => 0,
            'errorFile' => __FILE__,
            'previousErrorCode' => null,
            'previousErrorMessage' => null,
        ]);
    }
}