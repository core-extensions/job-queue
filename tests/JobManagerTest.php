<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests;

use CoreExtensions\JobQueue\Entity\Job;
use CoreExtensions\JobQueue\JobCommandFactoryInterface;
use CoreExtensions\JobQueue\JobManager;
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
        $jobManager = new JobManager($this->entityManager, $this->messageBus, $this->jobCommandFactory);
        $jobManager->enqueueJob($job);

        $this->assertNotNull($job->getDispatchedAt());
        $this->assertEquals('long_string_id', $job->getDispatchedMessageId());
    }
}
