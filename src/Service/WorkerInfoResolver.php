<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Service;

use CoreExtensions\JobQueueBundle\WorkerInfo;

class WorkerInfoResolver
{
    public function resolveWorkerInfo(): WorkerInfo
    {
        // $processInfo = shell_exec("ps -p $pid -o args=");
        return WorkerInfo::fromValues(getmypid(), 'NEED_PASS_ID_IN_WORKER');
    }
}
