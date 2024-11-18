<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

interface JobCommandHandlerInterface
{
    /**
     * (returned value will be stored in job->result)
     *
     * @return non-empty-array<string, mixed> $arr
     */
    public function __invoke(JobCommandInterface $jobCommand): array;
}
