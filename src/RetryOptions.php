<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

use Webmozart\Assert\Assert;

final class RetryOptions
{
    private int $maxRetries;

    private function __construct()
    {
    }

    public static function fromValues(int $maxRetries): self
    {
        Assert::positiveInteger($maxRetries, sprintf('Invalid param "%s" in "%s"', 'maxRetries', __METHOD__));

        $res = new self();
        $res->maxRetries = $maxRetries;

        return $res;
    }

    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'maxRetries', sprintf('No param "%s" in "%s"', 'maxRetries', __METHOD__));

        return self::fromValues(
            $arr['maxRetries']
        );
    }

    public function toArray(): array
    {
        return [
            'maxRetries' => $this->maxRetries,
        ];
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
}