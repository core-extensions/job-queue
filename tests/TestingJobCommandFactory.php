<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\UnsupportedJobTypeException;
use CoreExtensions\JobQueueBundle\JobCommandFactoryInterface;
use CoreExtensions\JobQueueBundle\JobCommandInterface;

final class TestingJobCommandFactory implements JobCommandFactoryInterface
{
    public function createFromJob(Job $job): JobCommandInterface
    {
        /** @noinspection DegradedSwitchInspection */
        switch ($job->getJobType()) {
            case TestingJobCommand::JOB_TYPE:
                return TestingJobCommand::fromArray($job->getJobCommand());
            default:
                throw new UnsupportedJobTypeException($job->getJobType());
        }
    }
}
