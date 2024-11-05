<?php

declare(strict_types=1);

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\Exception\UnsupportedJobTypeException;

/**
 * @app
 */
class ExampleJobCommandFactory implements \CoreExtensions\JobQueue\JobCommandFactoryInterface
{
    /**
     * (типизацию придется убрать)
     */
    public function createFromJob(Job $job): \CoreExtensions\JobQueue\JobCommandInterface
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
