<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

use Webmozart\Assert\Assert;

final class WorkerInfo
{
    private int $pid;
    private string $name;

    private function __construct()
    {
    }

    public static function fromValues(int $pid, string $name): self
    {
        Assert::positiveInteger($pid, sprintf('Invalid param "%s" value "%s" in "%s"', 'pid', $pid, __METHOD__));
        Assert::stringNotEmpty($name, sprintf('Invalid param "%s" value "%s" in "%s"', 'name', $name, __METHOD__));

        $res = new self();
        $res->pid = $pid;
        $res->name = $name;

        return $res;
    }

    public function toArray(): array
    {
        return [
            'pid' => $this->pid,
            'name' => $this->name,
        ];
    }

    private static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'pid', sprintf('No param "%s" in "%s"', 'pid', __METHOD__));
        Assert::keyExists($arr, 'name', sprintf('No param "%s" in "%s"', 'name', __METHOD__));

        return self::fromValues(
            $arr['pid'],
            $arr['name']
        );
    }
}
