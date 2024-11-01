<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests;

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\Exception\UnsupportedJobTypeException;
use CoreExtensions\JobQueue\JobCommandFactoryInterface;
use CoreExtensions\JobQueue\JobCommandInterface;

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
