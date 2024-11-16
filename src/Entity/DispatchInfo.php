<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Entity;

use CoreExtensions\JobQueueBundle\Helpers;
use Webmozart\Assert\Assert;

final class DispatchInfo
{
    private \DateTimeImmutable $dispatchedAt;
    /**
     * (в rabbit вида "amq.ctag-{random_string}-{number}")
     */
    private ?string $messageId = null;

    private function __construct()
    {
    }

    /**
     * @param array{dispatchedAt: string, messageId: string} $arr
     */
    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'dispatchedAt', sprintf('No param "%s" in "%s"', 'dispatchedAt', __METHOD__));
        Assert::keyExists($arr, 'messageId', sprintf('No param "%s" in "%s"', 'messageId', __METHOD__));

        return self::fromValues(Helpers::unserializeDateTime($arr['dispatchedAt']), $arr['messageId']);
    }

    /**
     * @return array{dispatchedAt: string, messageId: string}
     */
    public function toArray(): array
    {
        return [
            'dispatchedAt' => Helpers::serializeDateTime($this->dispatchedAt),
            'messageId' => $this->messageId,
        ];
    }

    public static function fromValues(\DateTimeImmutable $dispatchedAt, ?string $messageId): self
    {
        Assert::nullOrStringNotEmpty(
            $messageId,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'messageId', $messageId, __METHOD__)
        );

        $res = new self();
        $res->dispatchedAt = $dispatchedAt;
        $res->messageId = $messageId;

        return $res;
    }

    public function dispatchedAt(): \DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function messageId(): ?string
    {
        return $this->messageId;
    }

}
