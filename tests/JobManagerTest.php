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
        $this->jobManager = new JobManager($this->entityManager, $this->messageBus, $this->jobCommandFactory, new MessageIdResolver());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function it_can_enqueue_job(): void
    {
        // @see https://github.com/symfony/symfony/issues/33740
        $this->messageBus->method('dispatch')->willReturn(
            new Envelope(new \stdClass(), [new TransportMessageIdStamp('long_string_id')])
        );

        // $transportStampMock = Mockery::mock(StampInterface::class);
        // $transportStampMock->shouldReceive('getId')->andReturn('long_string');
        //
        // $envelopeMock = Mockery::mock(Envelope::class);
        // $envelopeMock->shouldReceive('last')
        //     ->with(TransportMessageIdStamp::class)
        //     ->andReturn($transportStampMock);

        $job = $this->job;
        $jobManager = $this->jobManager;

        $jobManager->enqueueJob($job);

        $this->assertNotNull($job->getDispatchedAt());
        $this->assertEquals('long_string_id', $job->getDispatchedMessageId());
    }

    /**
     * @test
     */
    public function it_can_enqueue_chain_of_jobs(): void
    {
        $this->messageBus->method('dispatch')->willReturnOnConsecutiveCalls(
            new Envelope(new \stdClass(), [new TransportMessageIdStamp('long_string_id_1')]),
            new Envelope(new \stdClass(), [new TransportMessageIdStamp('long_string_id_2')]),
            new Envelope(new \stdClass(), [new TransportMessageIdStamp('long_string_id_3')]),
        );

        $job1 = Job::initNew('44f2d3b5-492e-41c0-909f-c7a45286a68b', TestingJobCommand::fromValues(1, 'test1', new \DateTimeImmutable(), []), new \DateTimeImmutable());
        $job2 = Job::initNew('a0752bbd-7e12-43ac-b241-a26c865b2c6d', TestingJobCommand::fromValues(2, 'test2', new \DateTimeImmutable(), []), new \DateTimeImmutable());
        $job3 = Job::initNew('4061eecd-1262-4ff0-be38-09d7432ff608', TestingJobCommand::fromValues(3, 'test3', new \DateTimeImmutable(), []), new \DateTimeImmutable());

        $jobManager = $this->jobManager;
        $jobManager->enqueueChain('be3a7e26-aa34-454b-9c5d-121356e13910', [$job1, $job2, $job3]);

        $this->assertNotNull($job1->getDispatchedAt());
        $this->assertNotNull($job2->getDispatchedAt());
        $this->assertNotNull($job3->getDispatchedAt());

        $this->assertEquals('long_string_id_1', $job1->getDispatchedMessageId());
        $this->assertEquals('long_string_id_2', $job2->getDispatchedMessageId());
        $this->assertEquals('long_string_id_3', $job3->getDispatchedMessageId());

        // TODO: последовательное исполнение
    }
}
