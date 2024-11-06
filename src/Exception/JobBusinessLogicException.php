<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * (когда ошибка в логике и нельзя повторять)
 * (аналог forced stop)
 */
final class JobBusinessLogicException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
}
