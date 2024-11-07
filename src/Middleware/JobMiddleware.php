<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Middleware;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\JobCommandInterface;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Service\WorkerInfoResolver;
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
    private EntityManagerInterface $entityManager;
    private JobRepository $jobRepository;
    private WorkerInfoResolver $workerInfoResolver;

    public function __construct(
        EntityManagerInterface $entityManager,
        JobRepository $jobRepository,
        WorkerInfoResolver $workerInfoResolver
    ) {
        $this->entityManager = $entityManager;
        $this->jobRepository = $jobRepository;
        $this->workerInfoResolver = $workerInfoResolver;
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

            // persist always
            $this->entityManager->persist($job);
            $this->entityManager->flush();
        }

        return $envelope;
    }

    private function preHandling(Job $job): void
    {
        $workerInfo = $this->workerInfoResolver->resolveWorkerInfo();
        $job->accepted(new \DateTimeImmutable(), $workerInfo);
    }

    private function postHandling(Job $job): void
    {
        // TODO:
    }
}
