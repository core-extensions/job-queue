<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Entity\Job;

/**
 * Основное назначение: хранить всю информацию которая понадобится в handler.
 * По сути это и есть Job, а тот который у нас есть - просто doctrine-based-запись и результат работы.
 */
interface JobCommandInterface
{
    public function getJobType(): string;

    /**
     * (присутствует здесь потому что удобно доставать в handler)
     */
    public function getJobId(): ?string;

    public function bindJob(Job $job): void;

    public function toArray(): array;

    public static function fromArray(array $arr): self;
}
