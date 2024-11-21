<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\JobCommandInterface;

/**
 * throws if job command has empty job id
 */
final class JobUnboundException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobType;

    public static function fromJobCommand(JobCommandInterface $jobCommand): self
    {
        $res = new self(
            sprintf(
                'Using unbound job command with job type "%s"',
                $jobCommand->jobType()
            )
        );

        $res->jobType = $jobCommand->jobType();

        return $res;
    }

    public function getJobType(): string
    {
        return $this->jobType;
    }
}
