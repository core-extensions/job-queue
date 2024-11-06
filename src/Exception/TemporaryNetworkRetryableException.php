<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * (когда не доступна сеть)
 */
final class TemporaryNetworkRetryableException extends \RuntimeException implements JobRetryableExceptionInterface
{
}
