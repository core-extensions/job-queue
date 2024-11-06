<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Entity\Job;

interface JobHandlerInterface
{
    public function checkIfNotRevoked(Job $job): void;
}
