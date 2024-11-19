<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use CoreExtensions\JobQueueBundle\Entity\AcceptanceInfo;
use CoreExtensions\JobQueueBundle\Entity\DispatchInfo;
use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\Exception\JobNonRetryableExceptionInterface;
use CoreExtensions\JobQueueBundle\Exception\JobRetryableExceptionInterface;
use CoreExtensions\JobQueueBundle\Exception\JobRevokedException;
use CoreExtensions\JobQueueBundle\Exception\JobUnboundException;
use CoreExtensions\JobQueueBundle\JobConfiguration;
use CoreExtensions\JobQueueBundle\JobMiddleware;
use CoreExtensions\JobQueueBundle\Repository\JobRepository;
use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
use CoreExtensions\JobQueueBundle\Service\WorkerInfoResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
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
    public function it_yells_when_revoked_job_found(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        // prevents unbound found
        $this->jobRepository->method('find')->willReturn($job);

        // do workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));
        $job->revoked(new \DateTimeImmutable(), Job::REVOKED_DUE_DEPLOYMENT);

        $this->expectException(JobRevokedException::class);
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
        $envelope = new Envelope(
            $jobCommand,
            [new TransportMessageIdStamp('long_string_id'), new ReceivedStamp('transport_1')]
        );

        $this->jobRepository->method('find')->willReturn($job);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // do workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));

        $this->assertNull($job->getLastAcceptedAt());

        // emulate pre-call
        $this->stackNextMiddleware->method('handle')->willReturn($envelope); // due envelope is final
        $jobMiddleware->handle($envelope, $this->stack);

        $this->assertNotNull($job->getLastAcceptedAt());
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
        $job1->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));
        $job1->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        // should not be orphan
        $this->jobRepository->method('find')->willReturn($job1);
        // should find next chained job
        $this->jobRepository->method('findNextChained')->willReturn($job2);
        // should resolve worker info
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // emulate resolved
        $envelope1 = $envelope1->with(new HandledStamp(['result' => 'good'], 'handler_1'));
        $this->stackNextMiddleware->method('handle')->willReturn($envelope1); // due envelope is final

        // should dispatch next command
        $this->messageBus->expects($this->once())->method('dispatch')->with($jobCommand2)->willReturn($envelope2);

        $jobMiddleware->handle($envelope1, $this->stack);

        // should commit dispatched at
        $this->assertNotNull($job2->getLastDispatchedAt());
    }

    /**
     * @test
     */
    public function it_dont_dispatch_any_job_of_chain_if_end(): void
    {
        $jobMiddleware = $this->jobMiddleware;

        // chained job 1
        $job1 = $this->job;
        $job1->bindToChain('dcaf6b93-1a63-400d-95cf-10b604cdc61a', 0);

        $job1->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_message_id_1'));
        $job1->accepted(AcceptanceInfo::fromValues(new \DateTimeImmutable(), WorkerInfo::fromValues(1, 'worker_1')));

        $jobCommand1 = $this->jobCommandFactory->createFromJob($job1);
        $envelope1 = new Envelope($jobCommand1, [new TransportMessageIdStamp('long_string_id_1')]);

        // should not be orphan
        $this->jobRepository->method('find')->willReturn($job1);
        // should not find next chained job (due chain consists one item)
        $this->jobRepository->method('findNextChained')->willReturn(null);
        // should resolve worker info
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // emulate post-call for job1
        $envelope1 = $envelope1->with(
            new ReceivedStamp('some_transport'),
            new HandledStamp(['result' => 'good'], 'handler_1')
        );
        $this->stackNextMiddleware->method('handle')->willReturn($envelope1); // due envelope is final

        $jobMiddleware->handle($envelope1, $this->stack);

        // should not dispatch next command
        $this->messageBus->expects($this->never())->method('dispatch');
    }

    /**
     * @test
     */
    public function it_redispatch_and_seals_after_max_retries_reached_if_retryable_or_base_exception_thrown(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;
        $workerInfo = WorkerInfo::fromValues(1, 'worker_1');

        // retry 3 times
        $job->configure(JobConfiguration::default()->withMaxRetries(4));

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope(
            $jobCommand,
            [new TransportMessageIdStamp('long_string_id_1'), new ReceivedStamp('transport_1')]
        );

        // no orphan
        $this->jobRepository->method('find')->willReturn($job);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn($workerInfo);

        // do workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id_1'));

        // not accepted, no errors and no result before handling but already has been dispatched
        $this->assertNull($job->getLastAcceptedAt());
        $this->assertNull($job->getResult());
        $this->assertNull($job->getErrors());
        $this->assertCount(1, $job->getDispatches());

        // should dispatch repeated envelope
        $repeatedEnvelope = new Envelope(
            $jobCommand,
            [new TransportMessageIdStamp('long_string_id_2'), new ReceivedStamp('transport_1')]
        );

        // first call's retry 1 + first retries' retry + second retries' retry
        $this->messageBus->expects($this->exactly(3))->method('dispatch')->willReturn($repeatedEnvelope);

        // first call
        $this->stackNextMiddleware->method('handle')->willThrowException(
            new class('retryable_exception_message') extends \Exception implements JobRetryableExceptionInterface {
            }
        );
        $jobMiddleware->handle($envelope, $this->stack);

        // it accepts once (ReceivedStamp)
        $this->assertNotNull($job->getLastAcceptedAt());
        $this->assertEquals($workerInfo, $job->lastAcceptance()->workerInfo());
        $this->assertCount(1, $job->getAcceptances());

        // it redispatched and dispatching increments attempts immediately
        $this->assertCount(2, $job->getDispatches());
        $this->assertEquals(2, $job->getAttemptsCount());
        $this->assertCount(1, $job->getErrors()); // // it records a failure
        $this->assertEquals('retryable_exception_message', $job->getErrors()[0]['errorMessage']);
        $this->assertNull($job->getSealedAt()); // it didn't seal (max retries is not reached yet)

        // first retry
        $jobMiddleware->handle($repeatedEnvelope, $this->stack);
        $this->assertCount(3, $job->getDispatches());
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertEquals(3, $job->getAttemptsCount());
        $this->assertCount(
            2,
            $job->getErrors()
        ); // errors count less than attempts count because new attempt already started
        $this->assertNull($job->getSealedAt());

        // it retries if base exception thrown
        $this->stackNextMiddleware->method('handle')->willThrowException(
            new class('retryable_exception_message') extends \Exception {
            }
        );

        // second retry
        $jobMiddleware->handle($repeatedEnvelope, $this->stack);
        $this->assertCount(4, $job->getDispatches());
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertEquals(4, $job->getAttemptsCount());
        $this->assertCount(
            3,
            $job->getErrors()
        );  // errors count less than attempts count because new attempt already started
        $this->assertNull($job->getSealedAt());

        // last retry
        $jobMiddleware->handle($repeatedEnvelope, $this->stack);
        // it seals job due max retries reached
        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_DUE_FAILED_BY_MAX_RETRIES_REACHED, $job->getSealedDue());
        // so, it didn't change attempts counts
        $this->assertCount(4, $job->getDispatches());
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertEquals(4, $job->getAttemptsCount());
        // but it records last error too
        $this->assertCount(
            4,
            $job->getErrors()
        );
    }

    /**
     * @test
     */
    public function it_seals_non_retryable_at_once(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;
        $workerInfo = WorkerInfo::fromValues(1, 'worker_1');

        // once
        $job->configure(JobConfiguration::default()->withMaxRetries(1));

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope(
            $jobCommand,
            [new TransportMessageIdStamp('long_string_id_1'), new ReceivedStamp('transport_1')]
        );

        // no orphan
        $this->jobRepository->method('find')->willReturn($job);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn($workerInfo);

        // do workflow stuff
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id_1'));

        $this->stackNextMiddleware->method('handle')->willThrowException(
            new class('non_retryable_exception_message') extends \Exception implements
                JobNonRetryableExceptionInterface {
            }
        );

        $jobMiddleware->handle($envelope, $this->stack);

        // it records error
        $this->assertCount(1, $job->getErrors()); // // it records a failure
        $this->assertEquals('non_retryable_exception_message', $job->getErrors()[0]['errorMessage']);
        // no retry found
        $this->assertCount(1, $job->getDispatches());
        $this->assertEquals(1, $job->getAttemptsCount());
        // it properly seals
        $this->assertNotNull($job->getSealedAt());
        $this->assertEquals(Job::SEALED_DUE_NON_RETRYABLE_ERROR_OCCURRED, $job->getSealedDue());
    }

    /**
     * TODO: подумать нужно ли это
     *
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
        $job->dispatched(DispatchInfo::fromValues(new \DateTimeImmutable(), 'long_string_id'));

        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('persist')->with($job);

        $this->stackNextMiddleware->method('handle')->willReturn($envelope); // due envelope is final
        $jobMiddleware->handle($envelope, $this->stack);
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
