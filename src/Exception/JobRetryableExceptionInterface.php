<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * marker for retryable error
 */
interface JobRetryableExceptionInterface extends \Throwable
{
}
