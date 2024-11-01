<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

final class UnsupportedJobTypeException extends \RuntimeException implements JobExceptionInterface
{
    public function __construct(int $type)
    {
        parent::__construct(sprintf('Unsupported job type: %d', $type));
    }
}
