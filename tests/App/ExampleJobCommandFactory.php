<?php

declare(strict_types=1);

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\UnsupportedJobTypeException;

/**
 * @app
 */
class ExampleJobCommandFactory implements \CoreExtensions\JobQueueBundle\JobCommandFactoryInterface
{
    /**
     * (типизацию придется убрать)
     */
    public function createFromJob(Job $job): \CoreExtensions\JobQueueBundle\JobCommandInterface
    {
        /** @noinspection DegradedSwitchInspection */
        switch ($job->getJobType()) {
            case ExampleReverseGeocodeCommand::JOB_TYPE:
                return ExampleReverseGeocodeCommand::fromArray($job->getJobCommand());
            default:
                throw new UnsupportedJobTypeException($job->getJobType());
        }
    }
}
