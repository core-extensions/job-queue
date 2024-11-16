<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\JobCommandFactoryInterface;
use CoreExtensions\JobQueueBundle\JobManager;
use CoreExtensions\JobQueueBundle\Service\MessageIdResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final class JobManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private JobCommandFactoryInterface $jobCommandFactory;
    private JobManager $jobManager;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        // (если Mockery то нет типизации)
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->jobCommandFactory = new TestingJobCommandFactory();
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
        $this->jobManager = new JobManager(
            $this->entityManager,
            $this->messageBus,
            $this->jobCommandFactory,
            new MessageIdResolver()
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function it_enqueues_one_job(): void
    {
        $jobManager = $this->jobManager;

        $job = $this->job;
        $jobCommand = $this->jobCommandFactory->createFromJob($job);

        // we expect persist and flush
        $this->entityManager->expects($this->once())->method('persist')->with($job);
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        // we expect dispatching to bus
        $this->messageBus->expects($this->once())->method('dispatch')->with($jobCommand)->willReturn(
            new Envelope(new \stdClass(), [new TransportMessageIdStamp('long_string_id')])
        );

        $jobManager->enqueueJob($job);

        // we expect job will be marked as dispatched
        $this->assertNotNull($job->getLastDispatchedAt());

        $this->assertEquals($job->getLastDispatchedAt(), $job->lastDispatch()->dispatchedAt());
        $this->assertEquals('long_string_id', $job->lastDispatch()->messageId());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function it_enqueues_chain_of_jobs(): void
    {
        $jobManager = $this->jobManager;

        $job1 = Job::initNew(
            '44f2d3b5-492e-41c0-909f-c7a45286a68b',
            TestingJobCommand::fromValues(1, 'test1', new \DateTimeImmutable(), []),
            new \DateTimeImmutable()
        );
        $job2 = Job::initNew(
            'a0752bbd-7e12-43ac-b241-a26c865b2c6d',
            TestingJobCommand::fromValues(2, 'test2', new \DateTimeImmutable(), []),
            new \DateTimeImmutable()
        );
        $job3 = Job::initNew(
            '4061eecd-1262-4ff0-be38-09d7432ff608',
            TestingJobCommand::fromValues(3, 'test3', new \DateTimeImmutable(), []),
            new \DateTimeImmutable()
        );

        // we expect persist and flush all jobs
        $this->entityManager->expects($this->exactly(3))->method('persist')->withConsecutive([$job1], [$job2], [$job3]);
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        // we expect dispatching to bus only head job
        $jobCommand1 = $this->jobCommandFactory->createFromJob($job1);
        $this->messageBus->expects($this->once())->method('dispatch')->with($jobCommand1)->willReturnOnConsecutiveCalls(
            new Envelope(new \stdClass(), [new TransportMessageIdStamp('long_string_id_1')])
        );

        $jobManager->enqueueChain('be3a7e26-aa34-454b-9c5d-121356e13910', [$job1, $job2, $job3]);

        // we expect only head job will be marked as dispatched
        $this->assertNotNull($job1->getLastDispatchedAt());
        $this->assertEquals($job1->getLastDispatchedAt(), $job1->lastDispatch()->dispatchedAt());
        $this->assertEquals('long_string_id_1', $job1->lastDispatch()->messageId());

        $this->assertNull($job2->getLastDispatchedAt());
        $this->assertNull($job2->getDispatches());
        $this->assertNull($job3->getLastDispatchedAt());
        $this->assertNull($job3->getDispatches());
    }
}
