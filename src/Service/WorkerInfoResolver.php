<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Service;

use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class WorkerInfoResolver
{
    public function resolveWorkerInfo(ReceivedStamp $receivedStamp): WorkerInfo
    {
        // $processInfo = shell_exec("ps -p $pid -o args=");
        // TODO: worker name
        return WorkerInfo::fromValues(getmypid(), $receivedStamp->getTransportName());
    }
}
