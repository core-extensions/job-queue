<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

// нужен для: assertJobIsRevoked
abstract class AbstractJobHandler implements MessageHandlerInterface
{
    // TODO: или интерфейс, результат важно в виде массива
    abstract public function __invoke(JobCommandInterface $jobCommand): array;

    // TODO: метод для поставновки результата если его нельхя получить в middleware

    /**
     * Предназначен для прерывания длительных итерационных процессов.
     * Метод следует периодически вызывать в коде например в итерациях (каждые определенное кол-во раз или секунды).
     * (TODO: возможно стоит сделать интерфейс и трейт)
     */
    // protected function assertJobIsNotRevoked(Job $job): void
    // {
    //     if (null !== $job->isRevoked()) {
    //         throw JobRevokedException::fromJob($job);
    //     }
    // }
}
