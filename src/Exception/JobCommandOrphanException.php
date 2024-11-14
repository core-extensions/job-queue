<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\JobCommandInterface;
use CoreExtensions\JobQueueBundle\JobManager;

/**
 * Когда выясняется что у JobCommand нет соответствующего Job.
 *
 * @see JobManager::enqueueJob()
 * @see JobManager::enqueueChain()
 */
final class JobCommandOrphanException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    public static function withJobId(string $jobId): self
    {
        $res = new self(
            sprintf(
                'Using orphan job command of job "%s"',
                $jobId,
            )
        );
        $res->jobId = $jobId;

        return $res;
    }


    public function getJobId(): string
    {
        return $this->jobId;
    }
}
