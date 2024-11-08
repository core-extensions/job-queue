<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Middleware;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\Exception\JobUnboundException;
use CoreExtensions\JobQueueBundle\Middleware\JobMiddleware;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
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
    private JobMiddleware $jobMiddleware;
    private StackInterface $stack;
    private MessageIdResolver $messageIdResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->jobRepository = $this->createMock(JobRepository::class);
        $this->workerInfoResolver = $this->createMock(WorkerInfoResolver::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->messageIdResolver = $this->createMock(MessageIdResolver::class);
        $this->job = $this->provideJob(
            '99a01a56-3f9d-4bf1-b065-484455cc2847',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );
        $this->jobCommandFactory = new TestingJobCommandFactory();
        $this->jobMiddleware = new JobMiddleware(
            $this->entityManager,
            $this->messageBus,
            $this->jobRepository,
            $this->workerInfoResolver,
            $this->jobCommandFactory,
            $this->messageIdResolver
        );
    }

    /**
     * @test
     */
    public function it_yells_when_orphan_command_found(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $this->expectException(JobCommandOrphanException::class);
        $jobMiddleware->handle($envelope, $this->stack);
    }

    /**
     * @test
     */
    public function it_yells_when_unbound_command_found(): void
    {
        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = TestingJobCommand::fromValues(1, 'string', new \DateTimeImmutable(), ['some_array', 1]);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $this->expectException(JobUnboundException::class);
        $jobMiddleware->handle($envelope, $this->stack);
    }

    /**
     * @test
     */
    public function it_accepts_job(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $this->jobRepository->method('find')->willReturn($job);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // do workflow stuff
        $job->dispatched(new \DateTimeImmutable(), 'long_string_id');

        $this->assertNull($job->getAcceptedAt());

        $jobMiddleware->handle(
            $envelope,
            // due envelope is final
            new class implements StackInterface {
                public function next(): MiddlewareInterface
                {
                    return new class implements MiddlewareInterface {
                        public function handle(Envelope $envelope, StackInterface $stack): Envelope
                        {
                            return $envelope;
                        }
                    };
                }
            }
        );

        $this->assertNotNull($job->getAcceptedAt());
    }

    /**
     * @test
     */
    public function it_persist_and_flush_job(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $this->jobRepository->method('find')->willReturn($job);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // do workflow stuff
        $job->dispatched(new \DateTimeImmutable(), 'long_string_id');

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('persist')->with($job);

        $jobMiddleware->handle(
            $envelope,
            // due envelope is final
            new class implements StackInterface {
                public function next(): MiddlewareInterface
                {
                    return new class implements MiddlewareInterface {
                        public function handle(Envelope $envelope, StackInterface $stack): Envelope
                        {
                            return $envelope;
                        }
                    };
                }
            }
        );
    }

    /**
     * @test
     */
    public function it_dispatches_next_job_of_chain(): void
    {
        $job = $this->job;
        $job->bindToChain('dcaf6b93-1a63-400d-95cf-10b604cdc61a', 0);

        // make chain
        $job2 = $this->provideJob(
            '158b5ff2-c0d2-4118-a9fb-d3a1a8633d28',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );
        $job2->bindToChain('dcaf6b93-1a63-400d-95cf-10b604cdc61a', 1);

        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $this->jobRepository->method('find')->willReturn($job);
        $this->jobRepository->method('findNextChained')->willReturn($job2);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // make chain
        $job2 = $this->provideJob(
            '158b5ff2-c0d2-4118-a9fb-d3a1a8633d28',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );
        $job2->bindToChain('dcaf6b93-1a63-400d-95cf-10b604cdc61a', 1);

        // do workflow stuff
        $job->dispatched(new \DateTimeImmutable(), 'long_string_id');

        $this->assertNull($job->getAcceptedAt());
        $this->messageBus->expects($this->once())->method('dispatch');

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->exactly(2))->method('persist');

        // TODO: post stampt
        $jobMiddleware->handle(
            $envelope,
            // due envelope is final
            new class implements StackInterface {
                public function next(): MiddlewareInterface
                {
                    return new class implements MiddlewareInterface {
                        public function handle(Envelope $envelope, StackInterface $stack): Envelope
                        {
                            return $envelope;
                        }
                    };
                }
            }
        );

        $this->assertNotNull($job->getAcceptedAt());
    }

    public function it_dont_dispatch_any_job_of_chain_if_end(): void
    {
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function provideJob(string $jobId, TestingJobCommand $command, \DateTimeImmutable $createdAt): Job
    {
        return Job::initNew($jobId, $command, $createdAt);
    }

    private function provideCommand(\DateTimeImmutable $date): TestingJobCommand
    {
        return TestingJobCommand::fromValues(
            1000,
            'string',
            $date,
            [1, 2, 'string', $date]
        );
    }
}