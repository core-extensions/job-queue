<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\JobCommandInterface;

/**
 * Когда выясняется что к JobCommand не привязан Job.
 */
final class JobUnboundException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobType;

    public static function fromJobCommand(JobCommandInterface $jobCommand): self
    {
        $res = new self(
            sprintf(
                'Using unbound job command with job type "%s"',
                $jobCommand->getJobType()
            )
        );

        $res->jobType = $jobCommand->getJobType();

        return $res;
    }

    public function getJobType(): string
    {
        return $this->jobType;
    }
}
