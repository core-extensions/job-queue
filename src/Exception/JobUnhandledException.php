<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

/**
 */
final class JobUnhandledException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
}
