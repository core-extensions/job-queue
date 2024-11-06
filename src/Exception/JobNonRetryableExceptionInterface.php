<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * для того чтобы пометить ошибку при которой не должно быть повтора
 */
interface JobNonRetryableExceptionInterface extends \Throwable
{
}
