<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

interface JobCommandHandlerInterface
{
    /**
     * (returned value will be stored in job->result)
     */
    public function __invoke(JobCommandInterface $jobCommand): array;
}
