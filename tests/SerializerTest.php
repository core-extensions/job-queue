<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use CoreExtensions\JobQueueBundle\Serializer;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    /**
     * @test
     */
    public function it_serializes_with_microseconds(): void
    {
        $date = new \DateTimeImmutable('2023-12-24 17:21:15.520646+03:00');

        $serialized = Serializer::serializeDateTime($date);

        $this->assertEquals('2023-12-24 17:21:15.520646+03:00', $serialized);
    }

    /**
     * @test
     */
    public function it_returns_null_if_null_serialize(): void
    {
        $serialized = Serializer::serializeDateTime(null);

        $this->assertNull($serialized);
    }

    /**
     * @test
     */
    public function tit_unserializes_with_microseconds(): void
    {
        $dateString = '2023-12-24 17:21:15.520646+03:00';

        $unserialized = Serializer::unserializeDateTime($dateString);

        $this->assertInstanceOf(\DateTimeImmutable::class, $unserialized);
        $this->assertEquals($dateString, $unserialized->format('Y-m-d H:i:s.uP'));
    }

    /**
     * @test
     */
    public function it_returns_null_if_null_unserialize(): void
    {
        $unserialized = Serializer::unserializeDateTime(null);

        $this->assertNull($unserialized);
    }
}
