<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Entity;

use CoreExtensions\JobQueue\AbstractJobCommand;
use CoreExtensions\JobQueue\ErrorInfo;
use CoreExtensions\JobQueue\RetryOptions;
use CoreExtensions\JobQueue\WorkerInfo;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

/**
 * @lib
 * doctrine entity - точно нужно чтобы было doctrine entity?
 * плюсы:
 *  - простая запись
 *  - автоматическая сериализация
 * минусы:
 *  - нельзя гонять в виде message, поэтому появляется лишнее (JobStamp)
 *  - hardcoded-таблицы (пока)
 *
 * // TODO: retry + result of it
 *
 * @ORM\Entity(repositoryClass="CoreExtensions\JobQueue\Repository\JobRepository")
 * @ORM\Table(name="orm_jobs", schema="jobs"))
 */
class Job
{
    /**
     * Признак что остановили временно для re-run после deploy (обсуждаемо).
     */
    private const CANCELED_FOR_RERUN = 100;

    // ключи для worker
    private const WORKER_INFO_PID = 'pid';
    private const WORKER_INFO_NAME = 'name';

    // ключи retry
    private const RETRY_OPTION_MAX_RETRIES = 'max_retries';

    // ключи для error
    private const ERROR_CODE = 'code';
    private const ERROR_MESSAGE = 'message';
    private const ERROR_LINE = 'line';
    private const ERROR_FILE = 'file';
    private const ERROR_PREVIOUS_MESSAGE = 'previous_message';

    /**
     * @ORM\Id
     *
     * @ORM\Column(type="guid", unique=true)
     */
    private string $jobId;

    /**
     * (альтернатива - число, но тогда клиенту сложнее следить за уникальностью + при просмотре через rabbitui-нам трудно будет узнать задачу)
     *
     * @ORM\Column(type="string")
     */
    private string $jobType;

    /**
     * toArray - представление JobMessage
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private array $jobCommand;

    /**
     * Дата создания.
     *
     * @ORM\Column(type="datetimetz_immutable")
     */
    private \DateTimeImmutable $createdAt;

    /**
     * Дата постановки в очередь.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $dispatchedAt = null;

    /**
     * id сообщения в шине.
     * (в rabbit вида "amq.ctag-{random_string}-{number}")
     */
    private ?string $dispatchedMessageId = null;

    /**
     * Дата отмены.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $canceledAt = null;
    private ?int $canceledFor = null;

    /**
     * Дата когда команда приняла отмену.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $canceledAcceptedAt = null;

    /**
     * Информация о worker.
     * {pid, name}
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $workerInfo;

    /**
     * Признак chained очереди.
     *
     * @ORM\Column(type="guid", nullable=true)
     */
    private ?string $chainId = null;

    /**
     * Позиция в chained очереди.
     * (альтернатива хранить nexJobId - но сложнее в UI и БД, плюсы linked list)
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $chainPosition = null;

    /**
     * Результат работы.
     * {*}
     */
    private ?array $result = null;

    /**
     * {code, message, line, file}
     */
    private ?array $error = null;
    private ?array $retryOptions = null;

    /**
     * Инициализирует Job и делает JobMessage bound к нему.
     */
    public static function initNew(string $jobId, AbstractJobCommand $jobMessage, \DateTimeImmutable $createdAt): self
    {
        $res = new self();
        $res->setJobId($jobId);
        $res->setJobType($jobMessage->getJobType());
        $res->setJobCommand($jobMessage->toArray());
        $res->setCreatedAt($createdAt);

        $jobMessage->bindJob($res);

        return $res;
    }

    public function dispatched(\DateTimeImmutable $dispatchedAt, string $dispatchedMessageId): void
    {
        $this->setDispatchedAt($dispatchedAt);
        $this->setDispatchedMessageId($dispatchedMessageId);
    }

    public function canceled(\DateTimeImmutable $canceledAt, int $canceledFor): void
    {
        $this->setCanceledAt($canceledAt);
        $this->setCanceledFor($canceledFor);
    }

    public function resolved(?array $result): void
    {
        Assert::nullOrNotEmpty(
            $result,
            sprintf('Result must be not empty array or null for job "%s" in "%s"', $this->jobId, __METHOD__)
        );

        $maxRetries = $this->getRetryOptions()[self::RETRY_OPTION_MAX_RETRIES] ?? null;
        if (null === $maxRetries) {
            Assert::null(
                $this->getError(),
                sprintf('Trying to resolve already failed non retryable job "%s" in "%s"', $this->jobId, __METHOD__)
            );
        }

        $this->setResult($result);
    }

    public function failed(ErrorInfo $errorInfo): void
    {
        $this->setError($errorInfo->toArray());
    }

    public function bindToChain(string $chainId, int $chainPosition): void
    {
        Assert::uuid($chainId, sprintf('Invalid param "%s" in "%s"', 'chainId', __METHOD__));
        Assert::greaterThanEq($chainPosition, 0, sprintf('Invalid param "%s" in "%s"', 'chainPosition', __METHOD__));

        $this->chainId = $chainId;
        $this->chainPosition = $chainPosition;
    }

    public function bindWorkerInfo(WorkerInfo $workerInfo): void
    {
        $this->setWorkerInfo($workerInfo->toArray());
    }

    public function configureRetryOptions(RetryOptions $retryOptions): void
    {
        $this->setRetryOptions($retryOptions->toArray());
    }

    // getters && setters

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function setJobId(string $jobId): void
    {
        $this->jobId = $jobId;
    }

    public function getJobType(): string
    {
        return $this->jobType;
    }

    public function setJobType(string $jobType): void
    {
        $this->jobType = $jobType;
    }

    public function getJobCommand(): array
    {
        return $this->jobCommand;
    }

    public function setJobCommand(array $jobCommand): void
    {
        $this->jobCommand = $jobCommand;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getDispatchedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function setDispatchedAt(?\DateTimeImmutable $dispatchedAt): void
    {
        $this->dispatchedAt = $dispatchedAt;
    }

    public function getWorkerInfo(): ?array
    {
        return $this->workerInfo;
    }

    public function setWorkerInfo(?array $workerInfo): void
    {
        $this->workerInfo = $workerInfo;
    }

    public function getChainId(): ?string
    {
        return $this->chainId;
    }

    public function setChainId(?string $chainId): void
    {
        $this->chainId = $chainId;
    }

    public function getChainPosition(): ?int
    {
        return $this->chainPosition;
    }

    public function setChainPosition(?int $chainPosition): void
    {
        $this->chainPosition = $chainPosition;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function setError(?array $error): void
    {
        $this->error = $error;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function setResult(?array $result): void
    {
        $this->result = $result;
    }

    public function getCanceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeImmutable $canceledAt): void
    {
        $this->canceledAt = $canceledAt;
    }

    public function getCanceledFor(): ?int
    {
        return $this->canceledFor;
    }

    public function setCanceledFor(?int $canceledFor): void
    {
        $this->canceledFor = $canceledFor;
    }

    public function getDispatchedMessageId(): ?string
    {
        return $this->dispatchedMessageId;
    }

    public function setDispatchedMessageId(?string $dispatchedMessageId): void
    {
        $this->dispatchedMessageId = $dispatchedMessageId;
    }

    public function getCanceledAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->canceledAcceptedAt;
    }

    public function setCanceledAcceptedAt(?\DateTimeImmutable $canceledAcceptedAt): void
    {
        $this->canceledAcceptedAt = $canceledAcceptedAt;
    }

    public function setRetryOptions(?array $retryOptions): void
    {
        $this->retryOptions = $retryOptions;
    }

    public function getRetryOptions(): ?array
    {
        return $this->retryOptions;
    }
}
