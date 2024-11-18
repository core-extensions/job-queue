<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;

/**
 * @see JobConfiguration::timeout
 */
final class JobTimeoutExceededException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    public static function fromJob(Job $job): self
    {
        $res = new self(
            sprintf(
                'Job "%s" failed due to timeout exceed (timeout %d sec))',
                $job->getJobId(),
                $job->jobConfiguration()->timeout(),
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
