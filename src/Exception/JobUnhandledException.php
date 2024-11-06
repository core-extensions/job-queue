<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 */
final class JobUnhandledException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
}
