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
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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
    private MiddlewareInterface $stackNextMiddleware;
    private MessageIdResolver $messageIdResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->jobRepository = $this->createMock(JobRepository::class);
        $this->workerInfoResolver = $this->createMock(WorkerInfoResolver::class);
        $this->messageIdResolver = $this->createMock(MessageIdResolver::class);

        $this->stackNextMiddleware = $this->createMock(MiddlewareInterface::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->stack->method('next')->willReturn($this->stackNextMiddleware);

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
    public function it_accepts_job_on_first_call(): void
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

        // emulate pre-call
        $this->stackNextMiddleware->method('handle')->willReturn($envelope); // due envelope is final
        $jobMiddleware->handle($envelope, $this->stack);

        $this->assertNotNull($job->getAcceptedAt());
    }

    /**
     * @test
     */
    public function it_dispatches_next_job_of_chain(): void
    {
        $jobMiddleware = $this->jobMiddleware;

        // chained job 1
        $job1 = $this->job;
        $job1->bindToChain('dcaf6b93-1a63-400d-95cf-10b604cdc61a', 0);
        $jobCommand1 = $this->jobCommandFactory->createFromJob($job1);
        $envelope1 = new Envelope($jobCommand1, [new TransportMessageIdStamp('long_string_id_1')]);

        // chained job 2
        $job2 = $this->provideJob(
            '158b5ff2-c0d2-4118-a9fb-d3a1a8633d28',
            $this->provideCommand(new \DateTimeImmutable()),
            new \DateTimeImmutable()
        );
        $job2->bindToChain('dcaf6b93-1a63-400d-95cf-10b604cdc61a', 1);
        $jobCommand2 = $this->jobCommandFactory->createFromJob($job2);
        $envelope2 = new Envelope($jobCommand2, [new TransportMessageIdStamp('long_string_id_2')]);

        // do workflow stuffs
        $job1->dispatched(new \DateTimeImmutable(), 'long_string_id');
        $job1->accepted(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1'));
        $job1->resolved(new \DateTimeImmutable(), ['result' => 'good']);

        $this->jobRepository->method('find')->willReturn($job1);
        $this->jobRepository->method('findNextChained')->willReturn($job2);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // emulate post-call
        $envelope1 = $envelope1->with(new ReceivedStamp('some_transport'));
        $this->stackNextMiddleware->method('handle')->willReturn($envelope1); // due envelope is final

        // should dispatch next command
        $this->messageBus->expects($this->once())->method('dispatch')->with($jobCommand2)->willReturn($envelope2);

        $jobMiddleware->handle($envelope1, $this->stack);

        // should commit dispatched at
        $this->assertNotNull($job2->getDispatchedAt());
    }

    /**
     * TODO: подумать нужно ли это
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

        $this->stackNextMiddleware->method('handle')->willReturn($envelope); // due envelope is final
        $jobMiddleware->handle($envelope, $this->stack);
    }

    public function it_dont_dispatch_any_job_of_chain_if_end(): void
    {
    }

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