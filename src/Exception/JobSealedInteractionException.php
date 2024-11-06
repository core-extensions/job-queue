<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;

final class JobSealedInteractionException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $action;

    public static function fromJob(Job $job, string $action): self
    {
        $res = new self(
            sprintf(
                'Failed to apply action "%s" to sealed job "%s" (due %d))',
                $action,
                $job->getJobId(),
                $job->getSealedDue()
            )
        );
        $res->action = $action;

        return $res;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
