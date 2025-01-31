<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

use CoreExtensions\JobQueueBundle\Exception\JobExpiredException;
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
     * Если выполнение уже началось (был accepted), то оно не будет прервано.
     * (проверка применяется к периоду между dispatched и accepted)
     *
     * @see JobExpiredException
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

    public function withMaxRetries(int $maxRetries): self
    {
        $res = clone $this;
        $res->maxRetries = $maxRetries;

        return $res;
    }

    public function withTimeout(int $timeout): self
    {
        $res = clone $this;
        $res->timeout = $timeout;

        return $res;
    }

    /**
     * @param array{maxRetries: int, timeout: ?int} $arr
     */
    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'maxRetries', sprintf('No param "%s" in "%s"', 'maxRetries', __METHOD__));
        Assert::keyExists($arr, 'timeout', sprintf('No param "%s" in "%s"', 'timeout', __METHOD__));

        return self::fromValues(
            $arr['maxRetries'],
            $arr['timeout']
        );
    }

    /**
     * @return array{maxRetries: int, timeout: ?int}
     */
    public function toArray(): array
    {
        return [
            'maxRetries' => $this->maxRetries(),
            'timeout' => $this->timeout(),
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

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }
}
