<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

use CoreExtensions\JobQueue\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * @lib
 */
final class JobManager
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private JobCommandFactoryInterface $jobCommandFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        JobCommandFactoryInterface $jobCommandFactory
    ) {
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->jobCommandFactory = $jobCommandFactory;
    }

    /**
     * (в случае ошибок откатывает и возвращает ошибку)
     *
     * @throws \Throwable
     */
    public function enqueueJob(Job $job): void
    {
        $this->entityManager->beginTransaction();
        try {
            // всегда сначала сохраняем
            $this->entityManager->persist($job);

            $message = $this->jobCommandFactory->createFromJob($job);
            $envelope = $this->messageBus->dispatch($message); // jobId уже есть в команде

            // отмечаем отправку
            $job->dispatched(new \DateTimeImmutable(), $this->resolveEnvelopeMessageId($envelope));

            $this->entityManager->flush();
        } catch (\Throwable $tr) {
            $this->entityManager->rollBack();
            throw $tr;
        }
    }

    /**
     * (транзакция на всю группу)
     * (в случае ошибок откатывает и возвращает ошибку)
     *
     * @param Job[] $jobs
     */
    public function enqueueChain(string $chainId, array $jobs): void
    {
        $this->entityManager->beginTransaction();
        try {
            $i = 0; // 0 - у head
            foreach ($jobs as $job) {
                $job->bindToChain($chainId, $i++);
                $this->entityManager->persist($job);
            }

            foreach ($jobs as $job) {
                $message = $this->jobCommandFactory->createFromJob($job);
                $envelope = $this->messageBus->dispatch($message);

                // отмечаем отправку
                $job->dispatched(new \DateTimeImmutable(), $this->resolveEnvelopeMessageId($envelope));
            }

            $this->entityManager->flush();
        } catch (\Throwable $tr) {
            // logging
            $this->entityManager->rollBack();
        }
    }

    private function resolveEnvelopeMessageId(Envelope $envelope): ?string
    {
        /**
         * @var TransportMessageIdStamp $transportStamp
         */
        $transportStamp = $envelope->last(TransportMessageIdStamp::class);
        if (null === $transportStamp) {
            return null;
        }

        return (string)$transportStamp->getId();
    }
}
