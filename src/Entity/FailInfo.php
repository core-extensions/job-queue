<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Entity;

use CoreExtensions\JobQueueBundle\Serializer;
use Webmozart\Assert\Assert;

final class FailInfo
{
    private \DateTimeImmutable $failedAt;
    private int $errorCode;
    private string $errorMessage;
    private int $errorLine;
    private string $errorFile;
    private ?int $previousErrorCode;
    private ?string $previousErrorMessage;

    private function __construct()
    {
    }

    public static function fromThrowable(\DateTimeImmutable $failedAt, \Throwable $tr): self
    {
        $previous = $tr->getPrevious();

        // ability to write stacktrace by JobConfiguration?
        return self::fromValues(
            $failedAt,
            $tr->getCode(),
            $tr->getMessage(),
            $tr->getLine(),
            $tr->getFile(),
            $previous ? $previous->getCode() : null,
            $previous ? $previous->getMessage() : null
        );
    }

    /**
     * @param array{failedAt: string, errorCode: int, errorMessage: string, errorLine: int, errorFile: string, previousErrorCode: ?int, previousErrorMessage: ?string} $arr
     */
    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'failedAt', sprintf('No param "%s" in "%s"', 'failedAt', __METHOD__));
        Assert::keyExists($arr, 'errorCode', sprintf('No param "%s" in "%s"', 'code', __METHOD__));
        Assert::keyExists($arr, 'errorMessage', sprintf('No param "%s" in "%s"', 'message', __METHOD__));
        Assert::keyExists($arr, 'errorLine', sprintf('No param "%s" in "%s"', 'line', __METHOD__));
        Assert::keyExists($arr, 'errorFile', sprintf('No param "%s" in "%s"', 'file', __METHOD__));
        Assert::keyExists($arr, 'previousErrorCode', sprintf('No param "%s" in "%s"', 'previousErrorCode', __METHOD__));
        Assert::keyExists(
            $arr,
            'previousErrorMessage',
            sprintf('No param "%s" in "%s"', 'previousErrorMessage', __METHOD__)
        );

        return self::fromValues(
            Serializer::unserializeDateTime($arr['failedAt']),
            $arr['errorCode'],
            $arr['errorMessage'],
            $arr['errorLine'],
            $arr['errorFile'],
            $arr['previousErrorCode'] ?? null,
            $arr['previousErrorMessage'] ?? null
        );
    }

    /**
     * @return array{failedAt: string, errorCode: int, errorMessage: string, errorLine: int, errorFile: string, previousErrorCode: ?int, previousErrorMessage: ?string}
     */
    public function toArray(): array
    {
        return [
            'failedAt' => Serializer::serializeDateTime($this->failedAt),
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'errorLine' => $this->errorLine,
            'errorFile' => $this->errorFile,
            'previousErrorCode' => $this->previousErrorCode,
            'previousErrorMessage' => $this->previousErrorMessage,
        ];
    }

    private static function fromValues(
        \DateTimeImmutable $failedAt,
        int $errorCode,
        string $errorMessage,
        int $errorLine,
        string $errorFile,
        ?int $previousErrorCode = null,
        ?string $previousErrorMessage = null
    ): self {
        Assert::greaterThanEq(
            $errorCode,
            0,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'code', $errorCode, __METHOD__)
        );
        /* throwable can have empty message
        Assert::stringNotEmpty(
            $errorMessage,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'message', $errorMessage, __METHOD__)
        );
        */
        Assert::positiveInteger(
            $errorLine,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'line', $errorLine, __METHOD__)
        );
        Assert::stringNotEmpty(
            $errorFile,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'file', $errorFile, __METHOD__)
        );
        Assert::nullOrInteger(
            $previousErrorCode,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'previousErrorCode', $previousErrorCode, __METHOD__)
        );
        Assert::nullOrStringNotEmpty(
            $previousErrorMessage,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'previousErrorMessage', $previousErrorMessage, __METHOD__)
        );

        $res = new self();
        $res->failedAt = $failedAt;
        $res->errorCode = $errorCode;
        $res->errorMessage = $errorMessage;
        $res->errorLine = $errorLine;
        $res->errorFile = $errorFile;
        $res->previousErrorCode = $previousErrorCode;
        $res->previousErrorMessage = $previousErrorMessage;

        return $res;
    }

    public function failedAt(): \DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function errorMessage(): string
    {
        return $this->errorMessage;
    }

    public function errorLine(): int
    {
        return $this->errorLine;
    }

    public function errorFile(): string
    {
        return $this->errorFile;
    }

    public function previousErrorCode(): ?int
    {
        return $this->previousErrorCode;
    }

    public function previousErrorMessage(): ?string
    {
        return $this->previousErrorMessage;
    }
}
