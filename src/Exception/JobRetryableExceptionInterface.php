<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Exception;

/**
 * для того чтобы пометить ошибку при которой возможен быть повтор
 */
interface JobRetryableExceptionInterface extends \Throwable
{
}
