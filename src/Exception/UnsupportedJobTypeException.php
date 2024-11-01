<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

final class UnsupportedJobTypeException extends \RuntimeException implements JobExceptionInterface
{
    public function __construct(string $type)
    {
        parent::__construct(sprintf('Unsupported job type: "%s"', $type));
    }
}
