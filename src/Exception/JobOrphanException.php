<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\JobManager;

/**
 * throws if no job found by command job id
 *
 * @see JobManager::enqueueJob()
 * @see JobManager::enqueueChain()
 */
final class JobOrphanException extends \RuntimeException implements JobNonRetryableExceptionInterface
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
