<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Middleware;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Middleware\JobMiddleware;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Service\WorkerInfoResolver;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommand;
use CoreExtensions\JobQueueBundle\Tests\TestingJobCommandFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final class JobMiddlewareTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private JobRepository $jobRepository;
    private WorkerInfoResolver $workerInfoResolver;
    private Job $job;
    private TestingJobCommandFactory $jobCommandFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->jobRepository = $this->createMock(JobRepository::class);
        $this->workerInfoResolver = $this->createMock(WorkerInfoResolver::class);
        $this->job = Job::initNew(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            TestingJobCommand::fromValues(
                1000,
                'string',
                new \DateTimeImmutable(),
                [1, 2, 'string', new \DateTimeImmutable()]
            ),
            new \DateTimeImmutable()
        );
        $this->jobCommandFactory = new TestingJobCommandFactory();
    }

    /**
     * @test
     */
    public function it_calls_pre_and_post_actions(): void
    {
        $job = $this->job;
        $middleware = new JobMiddleware($this->entityManager, $this->jobRepository, $this->workerInfoResolver);

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $middleware->handle(
            $envelope,
            new class implements StackInterface {

                public function next(): MiddlewareInterface
                {
                    // TODO: Implement next() method.
                }
            }
        );
    }
}