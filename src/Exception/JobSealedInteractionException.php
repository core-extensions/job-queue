<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;

/**
 * throws if interaction with sealed job occurred
 */
final class JobSealedInteractionException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;
    private string $action;

    public static function fromJob(Job $job, string $action): self
    {
        $res = new self(
            sprintf(
                'Failed to apply action "%s" to sealed job "%s" (because of %d))',
                $action,
                $job->getJobId(),
                $job->getSealedBecauseOf()
            )
        );
        $res->jobId = $job->getJobId();
        $res->action = $action;

        return $res;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
