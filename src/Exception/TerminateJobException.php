<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

use Webmozart\Assert\InvalidArgumentException;

/**
 * used to forced stop handling
 */
final class TerminateJobException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
    private string $jobId;

    /**
     * (only webmozart/assert)
     */
    public static function becauseOfAssertionFails(string $jobId, InvalidArgumentException $assertionError): self
    {
        $res = new self(
            sprintf(
                'Job "%s" terminated by assertion fail with error "%s".',
                $jobId,
                $assertionError->getMessage(),
            ),
            0,
            $assertionError
        );
        $res->jobId = $jobId;

        return $res;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
