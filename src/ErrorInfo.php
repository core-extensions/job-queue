<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

use Webmozart\Assert\Assert;

// TODO: может дату сюда? => FailInfo
final class ErrorInfo
{
    private int $code;
    private string $message;
    private int $line;
    private string $file;
    private ?string $previousMessage;

    private function __construct()
    {
    }

    public static function fromValues(
        int $code,
        string $message,
        int $line,
        string $file,
        ?string $previousMessage = null
    ): self {
        Assert::positiveInteger($code, sprintf('Invalid param "%s" in "%s"', 'code', __METHOD__));
        Assert::notEmpty($message, sprintf('Invalid param "%s" in "%s"', 'message', __METHOD__));
        Assert::positiveInteger($line, sprintf('Invalid param "%s" in "%s"', 'line', __METHOD__));
        Assert::notEmpty($file, sprintf('Invalid param "%s" in "%s"', 'file', __METHOD__));

        $res = new self();
        $res->code = $code;
        $res->message = $message;
        $res->line = $line;
        $res->file = $file;
        $res->previousMessage = $previousMessage;

        return $res;
    }

    public static function fromThrowable(\Throwable $tr): self
    {
        $previous = $tr->getPrevious();

        return self::fromValues(
            $tr->getCode(),
            $tr->getMessage(),
            $tr->getLine(),
            $tr->getFile(),
            $previous ? $previous->getMessage() : null
        );
    }

    public static function fromArray(array $arr): self
    {
        Assert::keyExists($arr, 'code', sprintf('No param "%s" in "%s"', 'code', __METHOD__));
        Assert::keyExists($arr, 'message', sprintf('No param "%s" in "%s"', 'message', __METHOD__));
        Assert::keyExists($arr, 'line', sprintf('No param "%s" in "%s"', 'line', __METHOD__));
        Assert::keyExists($arr, 'file', sprintf('No param "%s" in "%s"', 'file', __METHOD__));
        Assert::keyExists($arr, 'previousMessage', sprintf('No param "%s" in "%s"', 'previousMessage', __METHOD__));

        return self::fromValues(
            $arr['code'],
            $arr['message'],
            $arr['line'],
            $arr['file'],
            $arr['previousMessage'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'line' => $this->line,
            'file' => $this->file,
            'previousMessage' => $this->previousMessage,
        ];
    }
}
