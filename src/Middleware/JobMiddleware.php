<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Middleware;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\Exception\JobUnboundException;
use CoreExtensions\JobQueueBundle\JobCommandFactoryInterface;
use CoreExtensions\JobQueueBundle\JobCommandInterface;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
use CoreExtensions\JobQueueBundle\Service\WorkerInfoResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Выполняет pre и post actions.
 */
class JobMiddleware implements MiddlewareInterface
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private JobRepository $jobRepository;
    private WorkerInfoResolver $workerInfoResolver;
    private JobCommandFactoryInterface $jobCommandFactory;
    private MessageIdResolver $messageIdResolver;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        JobRepository $jobRepository,
        WorkerInfoResolver $workerInfoResolver,
        JobCommandFactoryInterface $jobCommandFactory,
        MessageIdResolver $messageIdResolver
    ) {
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->jobRepository = $jobRepository;
        $this->workerInfoResolver = $workerInfoResolver;
        $this->jobCommandFactory = $jobCommandFactory;
        $this->messageIdResolver = $messageIdResolver;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /**
         * @var JobCommandInterface $jobCommand
         */
        $jobCommand = $envelope->getMessage();
        $jobId = $jobCommand->getJobId();

        // there are no correct way to receive an unbound job command
        if (null === $jobId) {
            throw JobUnboundException::fromJobCommand($jobCommand);
        }

        /**
         * @var Job|null $job
         */
        $job = $this->jobRepository->find($jobId);

        // there are no other way to detect orphans
        if (null === $job) {
            throw JobCommandOrphanException::fromJobCommand($jobCommand);
        }

        $job->assertJobNotRevoked();

        // pre handling
        if (!$envelope->last(ReceivedStamp::class)) {
            $this->acceptJob($job);

            $this->entityManager->persist($job);
            // TODO: подумать нужно ли это
            $this->entityManager->flush();
        }

        // next
        $envelope = $stack->next()->handle($envelope, $stack);

        // post handling
        if ($envelope->last(ReceivedStamp::class)) {
            // do chain stuff if chained and resolved
            if (null !== $job->getResolvedAt() && null !== $job->getChainId()) {
                $this->handleChain($job);
            }

            // TODO:
            // resolving?


            $this->entityManager->persist($job);
            // TODO: подумать нужно ли это
            $this->entityManager->flush();
        }

        return $envelope;
    }

    private function acceptJob(Job $job): void
    {
        $workerInfo = $this->workerInfoResolver->resolveWorkerInfo();
        $job->accepted(new \DateTimeImmutable(), $workerInfo);
    }

    // TODO: using JobManager::enqueueJob? but transactional stuffs?
    // handling chain if need
    private function handleChain(Job $currJob): void
    {
        $nextJob = $this->jobRepository->findNextChained($currJob->getChainId(), $currJob->getChainPosition());
        if (null !== $nextJob) {
            $nextMessage = $this->jobCommandFactory->createFromJob($nextJob);
            $nextEnvelope = $this->messageBus->dispatch($nextMessage);

            // 3) mark as dispatched and persist (because new entity)
            $nextJob->dispatched(
                new \DateTimeImmutable(),
                $this->messageIdResolver->resolveMessageId($nextEnvelope)
            );
            $this->entityManager->persist($nextJob);
        }
    }
}
