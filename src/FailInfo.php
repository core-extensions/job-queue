<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

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

    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'failedAt', sprintf('No param "%s" in "%s"', 'failedAt', __METHOD__));
        Assert::keyExists($arr, 'code', sprintf('No param "%s" in "%s"', 'code', __METHOD__));
        Assert::keyExists($arr, 'message', sprintf('No param "%s" in "%s"', 'message', __METHOD__));
        Assert::keyExists($arr, 'line', sprintf('No param "%s" in "%s"', 'line', __METHOD__));
        Assert::keyExists($arr, 'file', sprintf('No param "%s" in "%s"', 'file', __METHOD__));
        Assert::keyExists($arr, 'previousErrorCode', sprintf('No param "%s" in "%s"', 'previousErrorCode', __METHOD__));
        Assert::keyExists(
            $arr,
            'previousErrorMessage',
            sprintf('No param "%s" in "%s"', 'previousErrorMessage', __METHOD__)
        );

        return self::fromValues(
            $arr['failedAt'],
            $arr['code'],
            $arr['message'],
            $arr['line'],
            $arr['file'],
            $arr['previousErrorCode'] ?? null,
            $arr['previousMessage'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'failedAt' => $this->failedAt,
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
        Assert::positiveInteger($errorCode, sprintf('Invalid param "%s" in "%s"', 'code', __METHOD__));
        Assert::stringNotEmpty($errorMessage, sprintf('Invalid param "%s" in "%s"', 'message', __METHOD__));
        Assert::positiveInteger($errorLine, sprintf('Invalid param "%s" in "%s"', 'line', __METHOD__));
        Assert::stringNotEmpty($errorFile, sprintf('Invalid param "%s" in "%s"', 'file', __METHOD__));
        Assert::nullOrInteger(
            $previousErrorCode,
            sprintf('Invalid param "%s" in "%s"', 'previousErrorCode', __METHOD__)
        );
        Assert::nullOrStringNotEmpty(
            $previousErrorMessage,
            sprintf('Invalid param "%s" in "%s"', 'previousErrorMessage', __METHOD__)
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

    public function getFailedAt(): \DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function setFailedAt(\DateTimeImmutable $failedAt): void
    {
        $this->failedAt = $failedAt;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function setErrorCode(int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getErrorLine(): int
    {
        return $this->errorLine;
    }

    public function setErrorLine(int $errorLine): void
    {
        $this->errorLine = $errorLine;
    }

    public function getErrorFile(): string
    {
        return $this->errorFile;
    }

    public function setErrorFile(string $errorFile): void
    {
        $this->errorFile = $errorFile;
    }

    public function getPreviousErrorCode(): ?int
    {
        return $this->previousErrorCode;
    }

    public function setPreviousErrorCode(?int $previousErrorCode): void
    {
        $this->previousErrorCode = $previousErrorCode;
    }

    public function getPreviousErrorMessage(): ?string
    {
        return $this->previousErrorMessage;
    }

    public function setPreviousErrorMessage(?string $previousErrorMessage): void
    {
        $this->previousErrorMessage = $previousErrorMessage;
    }
}
