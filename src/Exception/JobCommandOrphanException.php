<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

use CoreExtensions\JobQueue\JobCommandInterface;
use CoreExtensions\JobQueue\JobManager;

/**
 * Когда выясняется что у JobCommand нет соответствующего Job.
 *
 * @see JobManager::enqueueJob()
 * @see JobManager::enqueueChain()
 */
final class JobCommandOrphanException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    public static function fromJobCommand(JobCommandInterface $jobCommand): self
    {
        $res = new self(
            sprintf(
                'Job "%s" not found (type "%s"))',
                $jobCommand->getJobId(),
                $jobCommand->getJobType()
            )
        );
        $res->jobId = $jobCommand->getJobId();

        return $res;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
