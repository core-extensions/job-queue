<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Exception;

use CoreExtensions\JobQueue\JobManager;

/**
 * Когда выясняется что у JobCommand нет соответствующего Job.
 *
 * @see JobManager::enqueueJob()
 * @see JobManager::enqueueChain()
 */
final class JobCommandOrphanException extends \RuntimeException implements JobNonRetryableExceptionInterface
{
}
