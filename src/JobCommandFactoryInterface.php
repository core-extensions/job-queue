<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Entity\Job;

/**
 * @lib
 *
 * Основное назначение: хранить всю информацию которая понадобится в handler.
 * По сути это и есть Job, а тот который у нас есть - просто doctrine-based-запись и результат работы.
 */
interface JobCommandFactoryInterface
{
    public function createFromJob(Job $job): JobCommandInterface;
}
