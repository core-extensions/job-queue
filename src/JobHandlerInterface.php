<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

use CoreExtensions\JobQueue\Entity\Job;

interface JobHandlerInterface
{
    public function checkIfNotRevoked(Job $job): void;
}
