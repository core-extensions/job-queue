<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Entity\Job;

/**
 * TODO: удобнее если такие классы будут в разных бандах. Иначе на клиенте приходится иметь один класс в Core.
 *
 * Основное назначение: хранить всю информацию которая понадобится в handler.
 * По сути это и есть Job, а тот который у нас есть - просто doctrine-based-запись и результат работы.
 */
interface JobCommandFactoryInterface
{
    /**
     * (должен вызывать \CoreExtensions\JobQueueBundle\JobCommandInterface::bindJob)
     */
    public function createFromJob(Job $job): JobCommandInterface;
}
