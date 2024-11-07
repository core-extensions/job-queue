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
        /**
         * @var JobCommandInterface $command
         */
        $command = null;

        /** @noinspection DegradedSwitchInspection */
        switch ($job->getJobType()) {
            case TestingJobCommand::JOB_TYPE:
                $command = TestingJobCommand::fromArray($job->getJobCommand());
                break;
        }

        if (null === $command) {
            throw new UnsupportedJobTypeException($job->getJobType());
        }

        $command->bindJob($job);

        return $command;
    }
}
