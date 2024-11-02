<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

/**
 * @see JobConfiguration::timeout
 */
final class JobTimeoutExceededException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
}
