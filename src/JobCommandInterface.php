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
     * (присутствует здесь потому что требуется в handler и middleware)
     */
    public function getJobId(): ?string;

    /**
     * (вызывается в \CoreExtensions\JobQueueBundle\Entity\Job::initNew)
     * (вызывается в \CoreExtensions\JobQueueBundle\JobCommandFactoryInterface::createFromJob)
     */
    public function bindJob(Job $job): void;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * @param array<string, mixed> $arr
     */
    public static function fromArray(array $arr): self;
}
