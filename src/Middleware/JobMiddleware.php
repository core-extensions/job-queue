<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Middleware;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\JobCommandInterface;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Выполняет pre и post actions.
 */
final class JobMiddleware implements MiddlewareInterface
{
    private JobRepository $jobRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(JobRepository $jobRepository, EntityManagerInterface $entityManager)
    {
        $this->jobRepository = $jobRepository;
        $this->entityManager = $entityManager;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /**
         * @var JobCommandInterface $jobCommand
         */
        $jobCommand = $envelope->getMessage();
        /**
         * @var Job $job
         */
        $job = $this->jobRepository->find($jobCommand->getJobId());

        // there are no other way to detect orphans
        if (null === $job) {
            throw JobCommandOrphanException::fromJobCommand($jobCommand);
        }

        if (!$envelope->last(ReceivedStamp::class)) {
            $this->preHandling($job);
        }

        // next
        $envelope = $stack->next()->handle($envelope, $stack);

        if ($envelope->last(ReceivedStamp::class)) {
            $this->postHandling($job);
        }

        return $envelope;
    }

    private function preHandling(Job $job): void
    {
        $job->accepted($job);
    }

    private function postHandling(Job $job): void
    {
        $this->entityManager->persist($job);
        $this->entityManager->flush();
    }
}
