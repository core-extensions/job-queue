<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * used to forced stop handling
 */
final class TerminateJobException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
}
