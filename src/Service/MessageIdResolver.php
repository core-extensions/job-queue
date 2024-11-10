<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Service;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

class MessageIdResolver
{
    /**
     * Resolves the message ID from the given envelope.
     *
     * @param Envelope $envelope The envelope containing the message.
     *
     * @return string|null The resolved message ID or null if not found.
     */
    public function resolveMessageId(Envelope $envelope): ?string
    {
        /** @var TransportMessageIdStamp|null $transportStamp */
        $transportStamp = $envelope->last(TransportMessageIdStamp::class);
        if (null === $transportStamp) {
            return null;
        }

        return (string)$transportStamp->getId();
    }
}
