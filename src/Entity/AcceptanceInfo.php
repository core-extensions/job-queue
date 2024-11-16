<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Entity;

use CoreExtensions\JobQueueBundle\Helpers;
use Webmozart\Assert\Assert;

final class AcceptanceInfo
{
    private \DateTimeImmutable $acceptedAt;
    private WorkerInfo $workerInfo;

    private function __construct()
    {
    }

    /**
     * @param array{acceptedAt: string, worker: array{pid: int, name: string}} $arr
     */
    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'acceptedAt', sprintf('No param "%s" in "%s"', 'acceptedAt', __METHOD__));
        Assert::keyExists($arr, 'workerInfo', sprintf('No param "%s" in "%s"', 'workerInfo', __METHOD__));

        return self::fromValues(
            Helpers::unserializeDateTime($arr['acceptedAt']),
            WorkerInfo::fromArray($arr['workerInfo'])
        );
    }

    /**
     * @return array{acceptedAt: string, worker: array{pid: int, name: string}}
     */
    public function toArray(): array
    {
        return [
            'acceptedAt' => Helpers::serializeDateTime($this->acceptedAt),
            'workerInfo' => $this->workerInfo->toArray(),
        ];
    }

    public static function fromValues(
        \DateTimeImmutable $acceptedAt,
        WorkerInfo $workerInfo
    ): self {
        $res = new self();
        $res->acceptedAt = $acceptedAt;
        $res->workerInfo = $workerInfo;

        return $res;
    }

    public function acceptedAt(): \DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function workerInfo(): WorkerInfo
    {
        return $this->workerInfo;
    }
}
