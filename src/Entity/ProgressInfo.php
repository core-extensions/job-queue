<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Entity;

use Webmozart\Assert\Assert;

class ProgressInfo
{
    private int $totalItems = 0;
    private int $processedItems = 0;

    private function __construct()
    {
    }

    public static function withTotalItems(int $totalItems): self
    {
        return self::fromValues($totalItems, 0);
    }

    public function increment(int $step): self
    {
        $newValue = $this->processedItems + $step;

        Assert::lessThanEq(
            $newValue,
            $this->totalItems,
            sprintf('Incremented value %d exceeds the total %d in %s"', $newValue, $this->totalItems, __METHOD__)
        );

        $res = clone $this;
        $res->processedItems = $newValue;

        return $res;
    }

    public function percentage(): float
    {
        Assert::positiveInteger(
            $this->totalItems,
            sprintf('Trying to "%s" with zero total items in "%s"', 'percentage', __METHOD__)
        );

        return ($this->processedItems / $this->totalItems) * 100;
    }

    private static function fromValues(int $totalItems, int $processedItems): self
    {
        Assert::positiveInteger(
            $totalItems,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'totalItems', $totalItems, __METHOD__)
        );
        Assert::greaterThanEq(
            $processedItems,
            0,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'processedItems', $processedItems, __METHOD__)
        );

        $res = new self();
        $res->totalItems = $totalItems;
        $res->processedItems = $processedItems;

        return $res;
    }

    /**
     * @return array{totalItems: int, processedItems: int}
     */
    public function toArray(): array
    {
        return [
            'totalItems' => $this->totalItems,
            'processedItems' => $this->processedItems,
        ];
    }

    /**
     * @param array{totalItems: int, processedItems: int} $arr
     *
     * @return self
     */
    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'totalItems', sprintf('No param "%s" in "%s"', 'totalItems', __METHOD__));
        Assert::keyExists($arr, 'processedItems', sprintf('No param "%s" in "%s"', 'processedItems', __METHOD__));

        return self::fromValues($arr['totalItems'], $arr['processedItems']);
    }

    public function totalItems(): int
    {
        return $this->totalItems;
    }

    public function processedItems(): int
    {
        return $this->processedItems;
    }
}
