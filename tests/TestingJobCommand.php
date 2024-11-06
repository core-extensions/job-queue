<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Tests;

use CoreExtensions\JobQueueBundle\Entity\Job;
use CoreExtensions\JobQueueBundle\JobCommandInterface;

final class TestingJobCommand implements JobCommandInterface
{
    public const JOB_TYPE = 'test.command';

    private ?string $jobId = null;
    private int $int;
    private string $string;
    private \DateTimeImmutable $date;
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

    public static function fromValues(int $int, string $string, \DateTimeImmutable $date, array $array): self
    {
        $res = new self();
        $res->int = $int;
        $res->string = $string;
        $res->date = $date;
        $res->array = $array;

        return $res;
    }

    public static function fromArray(array $arr): self
    {
        return self::fromValues($arr['int'], $arr['string'], $arr['date'], $arr['array']);
    }

    public function toArray(): array
    {
        return [
            'jobId' => $this->getJobId(),
            'int' => $this->getInt(),
            'string' => $this->getString(),
            'date' => $this->getDate(), // serialize?
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

    public function getArray(): array
    {
        return $this->array;
    }
}
