<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Service;

use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessageIdResolverTest extends TestCase
{
    private MessageIdResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MessageIdResolver();
    }

    /**
     * @test
     */
    public function it_resolves_message_id_when_stamp_exists(): void
    {
        // given
        $messageId = '123';
        $envelope = new Envelope(new \stdClass());
        $envelope = $envelope->with(new TransportMessageIdStamp($messageId));

        // when
        $resolvedId = $this->resolver->resolveMessageId($envelope);

        // then
        $this->assertEquals($messageId, $resolvedId);
    }

    /**
     * @test
     */
    public function it_returns_null_when_stamp_not_exists(): void
    {
        // given
        $envelope = new Envelope(new \stdClass());

        // when
        $resolvedId = $this->resolver->resolveMessageId($envelope);

        // then
        $this->assertNull($resolvedId);
    }
}
