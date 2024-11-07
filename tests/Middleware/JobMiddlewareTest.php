<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests\Middleware;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\Entity\WorkerInfo;
use CoreExtensions\JobQueueBundle\Exception\JobCommandOrphanException;
use CoreExtensions\JobQueueBundle\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueueBundle\Exception\JobUnboundException;
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
    private JobMiddleware $jobMiddleware;
    private StackInterface $stack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->jobRepository = $this->createMock(JobRepository::class);
        $this->workerInfoResolver = $this->createMock(WorkerInfoResolver::class);
        $this->stack = $this->createMock(StackInterface::class);
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
        $this->jobMiddleware = new JobMiddleware($this->entityManager, $this->jobRepository, $this->workerInfoResolver);
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
     * @throws \ReflectionException
     */
    public function it_calls_pre_and_post_actions(): void
    {
        $job = $this->job;
        $jobMiddleware = $this->jobMiddleware;

        $jobCommand = $this->jobCommandFactory->createFromJob($job);
        $envelope = new Envelope($jobCommand, [new TransportMessageIdStamp('long_string_id')]);

        $this->jobRepository->method('find')->willReturn($job);
        $this->workerInfoResolver->method('resolveWorkerInfo')->willReturn(WorkerInfo::fromValues(1, 'worker_1'));

        // do workflow stuff
        $job->dispatched(new \DateTimeImmutable(), 'long_string_id');

        $this->assertMethodCalled($jobMiddleware, 'preHandling', $job, $envelope, $this->stack);

        $jobMiddleware->handle(
            $envelope,
            $this->stack
        // new class implements StackInterface {
        //     public function next(): MiddlewareInterface
        //     {
        //         return new class implements MiddlewareInterface {
        //             public function handle(Envelope $envelope, StackInterface $stack): Envelope
        //             {
        //                 return $envelope;
        //             }
        //         };
        //     }
        // }
        );
    }

    /**
     * @throws \ReflectionException
     */
    private function assertMethodCalled($object, string $methodName, ...$args): void
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        $mock = $this->getMockBuilder(get_class($object))
            ->onlyMethods([$methodName])
            ->getMock();

        // Настройка ожидания вызова метода
        $mock->expects($this->once())
            ->method($methodName)
            ->with(...$args);

        $mock->handle($args[0], $args[1]);
    }
}