<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Exception\JobTimeoutExceededException;
use Webmozart\Assert\Assert;

/**
 * Опции запуска.
 * (здесь далее будут специфичные для транспорта?)
 */
final class JobConfiguration
{
    private const DEFAULT_TIMEOUT = 24 * 3600;

    /**
     * Максимальное количество попыток.
     */
    private int $maxRetries;

    /**
     * Количество секунд по истечению которой Job при поступлении в handler не будет.
     *
     * @see JobTimeoutExceededException
     */
    private ?int $timeout;

    private function __construct()
    {
    }

    /**
     * default параметры + one shot
     */
    public static function default(): self
    {
        return self::fromValues(1, self::DEFAULT_TIMEOUT);
    }

    // >>> fluent setters
    public function withMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    public function withTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    // <<< fluent setters

    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'maxRetries', sprintf('No param "%s" in "%s"', 'maxRetries', __METHOD__));
        Assert::keyExists($arr, 'timeout', sprintf('No param "%s" in "%s"', 'timeout', __METHOD__));

        return self::fromValues(
            $arr['maxRetries'],
            $arr['timeout']
        );
    }

    public function toArray(): array
    {
        return [
            'maxRetries' => $this->maxRetries,
            'timeout' => $this->timeout,
        ];
    }

    private static function fromValues(int $maxRetries, ?int $timeout): self
    {
        Assert::positiveInteger(
            $maxRetries,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'maxRetries', $maxRetries, __METHOD__)
        );
        Assert::nullOrPositiveInteger(
            $timeout,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'timeout', $timeout, __METHOD__)
        );

        $res = new self();
        $res->maxRetries = $maxRetries;
        $res->timeout = $timeout;

        return $res;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
