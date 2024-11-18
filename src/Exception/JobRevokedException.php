<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Serializer;

/**
 * throws when interaction with revoked job occurred
 */
final class JobRevokedException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    public static function fromJob(Job $job): self
    {
        $res = new self(
            sprintf(
                'Job "%s" was already revoked at "%s" (for %d))',
                $job->getJobId(),
                Serializer::serializeDateTime($job->getRevokedAt()),
                $job->getRevokedFor(),
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
