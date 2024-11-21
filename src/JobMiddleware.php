<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Entity\AcceptanceInfo;
use CoreExtensions\JobQueueBundle\Entity\DispatchInfo;
use CoreExtensions\JobQueueBundle\Entity\FailInfo;
use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\Exception\JobNonRetryableExceptionInterface;
use CoreExtensions\JobQueueBundle\Exception\JobRetryableExceptionInterface;
use CoreExtensions\JobQueueBundle\Exception\JobUnboundException;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
use CoreExtensions\JobQueueBundle\Service\WorkerInfoResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

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

        // there are no correct way to receive an unbound job command
        $this->assertJobNotUnbound($jobCommand);

        /**
         * @var Job|null $job
         */
        $job = $this->findJobOrFail($jobCommand->getJobId());

        $job->assertJobNotRevoked();

        /**
         * Вот точный порядок добавления stamps в Symfony Messenger:
         *
         * ReceivedStamp - добавляется когда сообщение получено из транспорта
         * SentStamp - добавляется когда сообщение отправлено в транспорт
         * TransportMessageIdStamp - добавляется транспортом после отправки сообщения
         * HandledStamp - добавляется после обработки сообщения handler'ом
         *
         * ReceivedStamp и SentStamp работают в разных контекстах:
         *
         * При первой отправке сообщения:
         * Создается сообщение
         * Добавляется SentStamp
         * Сообщение уходит в транспорт
         * При получении из транспорта:
         * Worker получает сообщение из транспорта
         * Добавляется ReceivedStamp
         * Сообщение обрабатывается
         * То есть это два разных процесса:
         *
         * Producer добавляет SentStamp при отправке
         * Consumer добавляет ReceivedStamp при получении
         * Поэтому в middleware мы сначала проверяем ReceivedStamp - чтобы понять, что это сообщение уже из транспорта и его не нужно отправлять повторно.
         */

        /**
         * @var SentStamp|null $stamp
         */
        if (null === $job->getLastDispatchedAt() && null !== ($stamp = $envelope->last(SentStamp::class))) {
            // TODO: подумать где делать dispatched
            // TODO: хорошее место для incAttemptsCount?
            // $job->dispatched(new \DateTimeImmutable(), $this->messageIdResolver->resolveMessageId($envelope));
            // print_r($stamp->getSenderAlias());
        }

        /**
         * @var ReceivedStamp|null $stamp
         */
        if (null === $job->getLastAcceptedAt() && null !== ($stamp = $envelope->last(ReceivedStamp::class))) {
            // здесь непонятно, делать ли accepted для expired jobs или нет
            // еще лучше наверно внутри accepted делать, тогда sealed можно private

            $timeout = $job->jobConfiguration()->timeout();
            $isLimitReached = $job->getLastAcceptedAt()->getTimestamp() - $job->getLastDispatchedAt()->getTimestamp();

            $job->accepted(
                AcceptanceInfo::fromValues(
                    new \DateTimeImmutable(),
                    $this->workerInfoResolver->resolveWorkerInfo($stamp)
                )
            );
        }

        // call next middlewares and handler
        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (JobNonRetryableExceptionInterface $nonRetryableException) {
            $failedAt = new \DateTimeImmutable();
            $job->failed(FailInfo::fromThrowable($failedAt, $nonRetryableException));

            $job->sealed(new \DateTimeImmutable(), JOB::SEALED_DUE_NON_RETRYABLE_ERROR_OCCURRED);
        } catch (JobRetryableExceptionInterface|\Throwable $tr) {
            $failedAt = new \DateTimeImmutable();
            $job->failed(FailInfo::fromThrowable($failedAt, $tr));

            $maxRetries = $job->jobConfiguration()->maxRetries();
            $isLimitReached = $job->getAttemptsCount() >= $maxRetries;

            // retry handling
            if (!$isLimitReached) {
                // re-dispatching + increment counter to new attempt
                $repeatedEnvelope = $this->messageBus->dispatch($this->jobCommandFactory->createFromJob($job));
                $job->dispatched(
                    DispatchInfo::fromValues(
                        new \DateTimeImmutable(),
                        $this->messageIdResolver->resolveMessageId($repeatedEnvelope)
                    )
                );
            } else {
                $job->sealed(new \DateTimeImmutable(), JOB::SEALED_DUE_FAILED_BY_MAX_RETRIES_REACHED);
            }
        }

        /**
         * @var HandledStamp|null $stamp
         */
        if (null === $job->getResolvedAt() && null !== ($stamp = $envelope->last(HandledStamp::class))) {
            $job->resolved(new \DateTimeImmutable(), $stamp->getResult());

            if (null !== $job->getChainId()) {
                // dispatch next job in chain if exists
                $nextJob = $this->jobRepository->findNextChained($job->getChainId(), $job->getChainPosition());
                if (null !== $nextJob) {
                    // TODO: подумать где делать dispatched
                    // TODO: можно использовать enqueueJob?
                    $nextEnvelope = $this->messageBus->dispatch($this->jobCommandFactory->createFromJob($nextJob));
                    $nextJob->dispatched(
                        DispatchInfo::fromValues(
                            new \DateTimeImmutable(),
                            $this->messageIdResolver->resolveMessageId($nextEnvelope)
                        )
                    );
                }
            }
        }

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        return $envelope;
    }

    private function findJobOrFail(string $jobId): Job
    {
        /**
         * @var Job|null $job
         */
        $job = $this->jobRepository->find($jobId);

        // there are no other way to detect orphans
        if (null === $job) {
            throw JobCommandOrphanException::withJobId($jobId);
        }

        return $job;
    }

    private function assertJobNotUnbound(JobCommandInterface $jobCommand): void
    {
        if (null === $jobCommand->getJobId()) {
            throw JobUnboundException::fromJobCommand($jobCommand);
        }
    }
}
