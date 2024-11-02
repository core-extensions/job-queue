<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\JobManager;

final class JobSealedInteractionException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    public static function fromJob(Job $job, string $action): self
    {
        return new self(
            sprintf(
                'Failed to apply action "%s" to sealed job "%s" (due %d))',
                $action,
                $job->getJobId(),
                $job->getSealedDue()
            )
        );
    }
}
