<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;

/**
 * used if period between dispatch and accept exceeds timeout
 *
 * @see JobConfiguration::timeout
 */
final class JobExpiredException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    public static function fromJob(Job $job): self
    {
        $res = new self(
            sprintf(
                'Job "%s" failed because of timeout exceed (timeout %d sec))',
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
