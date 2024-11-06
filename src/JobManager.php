<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Управляет очередью.
 */
final class JobManager
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private JobCommandFactoryInterface $jobCommandFactory;
    private MessageIdResolver $messageIdResolver;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        JobCommandFactoryInterface $jobCommandFactory,
        MessageIdResolver $messageIdResolver
    ) {
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->jobCommandFactory = $jobCommandFactory;
        $this->messageIdResolver = $messageIdResolver;
    }

    /**
     * (специально сначала dispatching, затем сохранение в БД + откат в случае неудачи + throw)
     * TODO: в handlers придется обрабатывать случай когда нет Job (OrphanJobCommandException)
     *
     * @throws \Throwable
     */
    public function enqueueJob(Job $job): void
    {
        $this->entityManager->beginTransaction();
        try {
            // 1) dispatching
            $message = $this->jobCommandFactory->createFromJob($job);
            $envelope = $this->messageBus->dispatch($message);

            // 2) mark as dispatched and persist (because new entity)
            $job->dispatched(new \DateTimeImmutable(), $this->messageIdResolver->resolveMessageId($envelope));
            $this->entityManager->persist($job);

            // 3) writing to db
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $tr) {
            $this->entityManager->rollBack();
            throw $tr;
        }
    }

    /**
     * Проблемы могут возникнуть и при saving и при dispatching.
     * Способ:
     *  1) сначала dispatching, потом saving:
     *   - плюсы:
     *      * exception для клиента и он знает что не удалось выполнить задачу
     *      * возможность повторно ставить задачу и не думать о failed (может нажать кнопку еще раз)
     *   - минусы:
     *      * могут возникнуть OrphanJobCommand
     *      * могут дублирование JobCommand, если jobId не будет меняться
     * 2) Сначала saving, потом dispatching
     *   - плюсы:
     *      * могут появится Job у которых нет JobCommand
     *   - минусы:
     *      * какой-то отдельный процесс должен будет повторять публикацию для dispatchedAt == null jobs
     *
     * (специально сначала dispatching всех, затем сохранение всех в БД и откат все в случае неудачи + throw)
     * TODO: в handlers придется обрабатывать случай когда нет Job (OrphanJobCommandException)
     * (транзакция на всю группу)
     *
     * @param Job[] $jobs
     *
     * @throws \Throwable
     *
     * @see JobCommandOrphanException
     */
    public function enqueueChain(string $chainId, array $jobs): void
    {
        $this->entityManager->beginTransaction();
        try {
            // 1) prepare all to be chained
            $i = 0; // 0 - у head
            foreach ($jobs as $job) {
                $job->bindToChain($chainId, $i++);
            }

            // 2) dispatching all
            foreach ($jobs as $job) {
                $message = $this->jobCommandFactory->createFromJob($job);
                $envelope = $this->messageBus->dispatch($message);

                // 3) mark as dispatched and persist (because new entity)
                $job->dispatched(new \DateTimeImmutable(), $this->messageIdResolver->resolveMessageId($envelope));
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $tr) {
            $this->entityManager->rollBack();
            throw $tr;
        }
    }
}
