<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Tests;

use CoreExtensions\JobQueue\AbstractJobCommand;
use Webmozart\Assert\Assert;

final class TestingJobCommand extends AbstractJobCommand
{
    public const JOB_TYPE = 'test.command';

    private string $int;
    private string $string;
    private \DateTimeImmutable $date;
    private array $array;

    public function getJobType(): string
    {
        return self::JOB_TYPE;
    }

    public function serialize(): array
    {
        return [
            'int' => $this->getInt(),
            'string' => $this->getString(),
            'date' => $this->getDate(), // serialize?
            'array' => $this->getArray(),
        ];
    }

    public static function fromArray(array $arr): self
    {
        $res = parent::fromArray($arr);
        $res->int = $arr['int'];
        $res->string = $arr['string'];
        $res->date = $arr['date'];
        $res->array = $arr['array'];

        return $res;
    }

    public function getInt(): string
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
