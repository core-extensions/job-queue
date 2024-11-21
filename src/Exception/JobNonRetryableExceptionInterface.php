<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * marker for non-retryable error
 */
interface JobNonRetryableExceptionInterface extends \Throwable
{
}
