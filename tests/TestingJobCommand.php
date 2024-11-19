<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\JobCommandInterface;
use CoreExtensions\JobQueueBundle\Serializer;
use Webmozart\Assert\Assert;

final class TestingJobCommand implements JobCommandInterface
{
    public const JOB_TYPE = 'test.command';

    private ?string $jobId = null;
    private int $int;
    private string $string;
    private \DateTimeImmutable $date;
    /**
     * @var array<string, mixed>
     */
    private array $array;

    private function __construct()
    {
    }

    public function getJobType(): string
    {
        return self::JOB_TYPE;
    }

    public function getJobId(): ?string
    {
        return $this->jobId;
    }

    public function bindJob(Job $job): void
    {
        $this->jobId = $job->getJobId();
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromValues(int $int, string $string, \DateTimeImmutable $date, array $array): self
    {
        $res = new self();
        $res->int = $int;
        $res->string = $string;
        $res->date = $date;
        $res->array = $array;

        return $res;
    }

    /**
     * @param array<mixed> $arr
     */
    public static function fromArray(array $arr): self
    {
        $date = Serializer::unserializeDateTime($arr['date']);
        Assert::notNull($date, sprintf('Invalid param "%s" in "%s"', 'date', __METHOD__));

        return self::fromValues($arr['int'], $arr['string'], $date, $arr['array']);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'int' => $this->getInt(),
            'string' => $this->getString(),
            'date' => Serializer::serializeDateTime($this->getDate()),
            'array' => $this->getArray(),
        ];
    }

    public function getInt(): int
    {
        return $this->int;
    }

    public function getString(): string
    {
        return $this->string;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @return array<mixed>
     */
    public function getArray(): array
    {
        return $this->array;
    }
}
