<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;

final class JobUnboundException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    public static function fromJob(Job $job): self
    {
        $res = new self(
            sprintf(
                'Using unbound jobCommand of job "%s"',
                $job->getJobId()
            )
        );

        $res->jobId = $job->getJobId();

        return $res;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
