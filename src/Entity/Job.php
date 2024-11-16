<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle\Entity;

use CoreExtensions\JobQueueBundle\Exception\JobRevokedException;
use CoreExtensions\JobQueueBundle\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueueBundle\Helpers;
use CoreExtensions\JobQueueBundle\JobCommandInterface;
use CoreExtensions\JobQueueBundle\JobConfiguration;
use CoreExtensions\JobQueueBundle\JobManager;
use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

/**
 * // TODO: doctrine entity - точно нужно чтобы было doctrine entity?
 * // TODO: optimistic locking чтобы с UI не могли работать со старыми данными
 * // TODO: из-за retryable время надо фиксировать в множественном виде следующие поля:
 * //  - $dispatchedAt, $dispatchedMessageId
 * //  - $acceptedAt, $acceptedWorkerInfo
 *
 * @ORM\Entity(repositoryClass="CoreExtensions\JobQueueBundle\Repository\JobRepository")
 * @ORM\Table(name="orm_jobs", schema="jobs"))
 */
class Job
{
    /**
     * Признак что остановили временно для re-run после deploy (обсуждаемо).
     */
    // остановили временно для re-run после deploy (обсуждаемо).
    public const REVOKED_DUE_DEPLOYMENT = 10;

    /**
     * Причины для sealed.
     */
    public const SEALED_DUE_REVOKED_AND_CONFIRMED = 10;
    public const SEALED_DUE_RESOLVED = 20;
    public const SEALED_DUE_FAILED_BY_MAX_RETRIES_REACHED = 30;
    public const SEALED_DUE_FAILED_TIMEOUT = 31;

    /**
     * @ORM\Id
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
     * @var array<string, mixed>
     *
     * @see JobCommandInterface
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
     * Массив из DispatchInfo, где ключи ($attemptsCount - 1) (нумерация с нуля).
     *
     * @var ?array{dispatchedAt: string, messageId: string}
     *
     * @see DispatchInfo[]
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $dispatches = null;

    /**
     * Массив из AcceptanceInfo, где ключи ($attemptsCount - 1) (нумерация с нуля).
     *
     * @var ?array{accepteddAt: string, worker: WorkerInfo}
     *
     * @see AcceptanceInfo[]
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $acceptances = null;

    /**
     * Дата последней попытки постановки в очередь.
     * (denormalized)
     * (нужна для простой фильтрации при поиске)
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $lastDispatchedAt = null;

    /**
     * Дата последней попытки начала обработки.
     * (denormalized)
     * (нужна для простой фильтрации при поиске)
     *
     * @ORM\Column(type="datetimetz_immutable")
     */
    private ?\DateTimeImmutable $lastAcceptedAt = null;

    /**
     * Дата отмены.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $revokedAt = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $revokedFor = null;

    /**
     * Дата когда handler принял отмену.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $revokeAcceptedAt = null;

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
     *
     * @var ?array<string, mixed>
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $result = null;

    /**
     * Дата успешного выполнения.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $resolvedAt = null;

    /**
     * Количество попыток (0 - не запускалось, 1 - после первого запуска, инкремент - в последующие)
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $attemptsCount = 0;

    /**
     * Массив из ErrorInfo, где ключи ($attemptsCount - 1) (нумерация с нуля).
     *
     * @var ?array{failedAt: string, errorCode: int, errorMessage: string, errorLine: int, errorFile: string, previousErrorCode: ?int, previousErrorMessage: ?string}
     *
     * @see FailInfo[]
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $errors = null;

    /**
     * Если не задано, то будет установлено default-значение.
     * (так как maxRetries - должен иметь лимит)
     * (как и timeout - должен иметь лимит)
     *
     * @var array{maxRetries: int, timeout: ?int}
     *
     * @see JobConfiguration::default()
     *
     * @ORM\Column(type="json")
     */
    private array $jobConfiguration;

    /**
     * Optimistic locking.
     * To prevent to manage outdated from UI.
     *
     * @ORM\Version
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $version = 0;

    /**
     * Дата когда Job помечена как "запечатанная".
     * Job в таком состоянии позволяет только читать из нее данные.
     * workflow-методы работать не будут.
     *
     * Это финальное состояние, оно возникает после:
     *  1) revoke => revokeConfirmed => sealed
     *  2) failed => max retries reached => sealed
     *  3) resolved => sealed
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $sealedAt = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $sealedDue = null;

    // >>> domain logic

    /**
     * Инициализирует Job и делает JobMessage bound к нему.
     * TODO: может быть дублирование JobCommand, если jobId не будет меняться @see JobManager::enqueueChain(),
     * TODO: возможно лучше сделать его генерацию внутренней, через factory (для тестов)
     */
    public static function initNew(string $jobId, JobCommandInterface $jobMessage, \DateTimeImmutable $createdAt): self
    {
        $res = new self();
        $res->setJobId($jobId);
        $res->setJobType($jobMessage->getJobType());
        $res->setJobCommand($jobMessage->toArray());
        $res->setCreatedAt($createdAt);
        $res->configure(JobConfiguration::default());

        $jobMessage->bindJob($res);

        return $res;
    }

    /**
     * Вызывается когда удалось опубликовать в bus.
     * (refreshing denormalized field too)
     */
    public function dispatched(DispatchInfo $dispatchInfo): void
    {
        $dispatchedAt = $dispatchInfo->dispatchedAt();
        $dispatchedMessageId = $dispatchInfo->messageId();

        Assert::greaterThanEq(
            $dispatchedAt->getTimestamp(),
            $this->getCreatedAt()->getTimestamp(),
            sprintf(
                'Job "%s" cannot be dispatched "%s" earlier than created "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($dispatchedAt),
                Helpers::serializeDateTime($this->getLastDispatchedAt()),
                __METHOD__
            )
        );
        Assert::nullOrStringNotEmpty(
            $dispatchedMessageId,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'dispatchedMessageId', $dispatchedMessageId, __METHOD__)
        );
        $this->assertJobNotSealed('dispatched');

        $this->recordDispatch($dispatchInfo);
        $this->setLastDispatchedAt($dispatchedAt);
    }

    /**
     * Вызывается когда handler получил задачу из bus.
     * (refreshing denormalized field too)
     */
    public function accepted(AcceptanceInfo $acceptanceInfo): void
    {
        $acceptedAt = $acceptanceInfo->acceptedAt();

        $dispatchedAt = $this->getLastDispatchedAt();

        Assert::notNull(
            $dispatchedAt,
            sprintf('Attempt to accept non dispatched job "%s" in "%s"', $this->getJobId(), __METHOD__)
        );
        Assert::greaterThanEq(
            $acceptedAt->getTimestamp(),
            $dispatchedAt->getTimestamp(),
            sprintf(
                'Job "%s" cannot be accepted "%s" earlier than dispatched "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($acceptedAt),
                Helpers::serializeDateTime($this->getLastDispatchedAt()),
                __METHOD__
            )
        );
        $this->assertJobNotSealed('accepted');

        $this->recordAcceptance($acceptanceInfo);
        $this->setLastAcceptedAt($acceptedAt);
    }

    /**
     * Вызывается клиентом для отмены.
     */
    public function revoked(\DateTimeImmutable $revokedAt, int $revokedFor): void
    {
        Assert::greaterThanEq(
            $revokedAt->getTimestamp(),
            $this->getCreatedAt()->getTimestamp(),
            sprintf(
                'Job "%s" cannot be revoked "%s" earlier than created "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($revokedAt),
                Helpers::serializeDateTime($this->getCreatedAt()),
                __METHOD__
            )
        );
        $this->assertJobNotSealed('revoked');

        $this->setRevokedAt($revokedAt);
        $this->setRevokedFor($revokedFor);
    }

    /**
     * Вызывается когда handler получил знание об отмене.
     * (TODO: будет понятно после создания handler)
     * (TODO: в итерациях?)
     */
    public function revokeConfirmed(\DateTimeImmutable $revokeConfirmedAt): void
    {
        $revokedAt = $this->getRevokedAt();

        Assert::notNull(
            $revokedAt,
            sprintf('Attempt to confirm revoke of non revoked job "%s" in "%s"', $this->getJobId(), __METHOD__)
        );
        Assert::greaterThanEq(
            $revokeConfirmedAt->getTimestamp(),
            $revokedAt->getTimestamp(),
            sprintf(
                'Job "%s" revoking cannot be confirmed "%s" earlier than revoked "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($revokeConfirmedAt),
                Helpers::serializeDateTime($revokedAt),
                __METHOD__
            )
        );
        $this->assertJobNotSealed('revokeConfirmed');

        $this->setRevokeAcceptedAt($revokeConfirmedAt);
        $this->sealed($revokeConfirmedAt, self::SEALED_DUE_REVOKED_AND_CONFIRMED);
    }

    /**
     * Вызывается в handler при успешном выполнении.
     * (result - специально сделал not nullable, чтобы хоть что то о результате всегда писали)
     *
     * @param array<string, mixed> $result
     */
    public function resolved(\DateTimeImmutable $resolvedAt, array $result): void
    {
        $acceptedAt = $this->getLastAcceptedAt();

        Assert::notNull(
            $acceptedAt,
            sprintf('Attempt to resolve non accepted job "%s" in "%s"', $this->getJobId(), __METHOD__)
        );
        Assert::greaterThanEq(
            $resolvedAt->getTimestamp(),
            $acceptedAt->getTimestamp(),
            sprintf(
                'Job "%s" cannot be resolved "%s" earlier than accepted "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($acceptedAt),
                Helpers::serializeDateTime($this->getLastDispatchedAt()),
                __METHOD__
            )
        );
        Assert::notEmpty(
            $result,
            sprintf('Result must be not empty array for job "%s" in "%s"', $this->jobId, __METHOD__)
        );
        $this->assertJobNotSealed('resolved');

        $this->incAttemptsCount();
        $this->setResolvedAt($resolvedAt);
        $this->setResult($result);

        // resolved => sealed
        $this->sealed($resolvedAt, self::SEALED_DUE_RESOLVED);
    }

    /**
     * Вызывается в handler при каждом fail.
     * (increments attempts count)
     */
    public function failed(FailInfo $errorInfo): void
    {
        $failedAt = $errorInfo->failedAt();
        $acceptedAt = $this->getLastAcceptedAt();

        Assert::notNull(
            $acceptedAt,
            sprintf('Attempt to failed non accepted job "%s" in "%s"', $this->getJobId(), __METHOD__)
        );
        Assert::greaterThanEq(
            $failedAt->getTimestamp(),
            $acceptedAt->getTimestamp(),
            sprintf(
                'Job "%s" cannot be failed "%s" earlier than accepted "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($acceptedAt),
                Helpers::serializeDateTime($this->getLastDispatchedAt()),
                __METHOD__
            )
        );
        $this->assertJobNotSealed('failed');

        // we should increment attempts count
        $this->incAttemptsCount();

        $this->recordFailedAttempt($errorInfo);

        // TODO: вынесем в middle?
        $jobConfiguration = JobConfiguration::fromArray($this->getJobConfiguration());
        $maxRetries = $jobConfiguration->getMaxRetries();

        if ($this->getAttemptsCount() >= $maxRetries) {
            $this->sealed($failedAt, self::SEALED_DUE_FAILED_BY_MAX_RETRIES_REACHED);
        }
    }

    public function bindToChain(string $chainId, int $chainPosition): void
    {
        $this->assertJobNotSealed('bindToChain');

        Assert::uuid($chainId, sprintf('Invalid param "%s" value "%s" in "%s"', 'chainId', $chainId, __METHOD__));
        Assert::greaterThanEq(
            $chainPosition,
            0,
            sprintf('Invalid param "%s" value "%s" in "%s"', 'chainPosition', $chainPosition, __METHOD__)
        );

        $this->chainId = $chainId;
        $this->chainPosition = $chainPosition;
    }

    public function configure(JobConfiguration $jobConfiguration): void
    {
        $this->assertJobNotSealed('revoked');
        $this->setJobConfiguration($jobConfiguration->toArray());
    }

    /**
     * (специально private)
     */
    private function sealed(\DateTimeImmutable $sealedAt, int $due): void
    {
        $this->setSealedAt($sealedAt);
        $this->setSealedDue($due);
    }

    private function recordDispatch(DispatchInfo $dispatchInfo): void
    {
        $dispatched = $this->getDispatches() ?? [];
        $dispatched[] = $dispatchInfo->toArray();

        $this->setDispatches($dispatched);
    }

    private function recordAcceptance(AcceptanceInfo $acceptanceInfo): void
    {
        $acceptances = $this->getAcceptances() ?? [];
        $acceptances[] = $acceptanceInfo->toArray();

        $this->setAcceptances($acceptances);
    }

    private function recordFailedAttempt(FailInfo $failInfo): void
    {
        $errors = $this->getErrors() ?? [];
        $errors[] = $failInfo->toArray();

        $this->setErrors($errors);
    }


    private function incAttemptsCount(): void
    {
        $this->setAttemptsCount($this->getAttemptsCount() + 1);
    }

    public function lastDispatch(): ?DispatchInfo
    {
        if (null === $this->dispatches) {
            return null;
        }

        return DispatchInfo::fromArray(end($this->dispatches));
    }

    public function lastAcceptance(): ?AcceptanceInfo
    {
        if (null === $this->acceptances) {
            return null;
        }

        return AcceptanceInfo::fromArray(end($this->acceptances));
    }

    /**
     * (метод следует периодически вызывать в коде например в итерациях (каждые определенное кол-во раз или секунды).
     */
    public function assertJobNotRevoked(): void
    {
        if (null !== $this->getRevokedAt()) {
            throw JobRevokedException::fromJob($this);
        }
    }

    public function assertJobNotSealed(string $action): void
    {
        if (null !== $this->getSealedAt()) {
            throw JobSealedInteractionException::fromJob($this, $action);
        }
    }

    // <<< domain logic

    // getters && setters (doctrine)
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

    /**
     * @return array<string, mixed>
     */
    public function getJobCommand(): array
    {
        return $this->jobCommand;
    }

    /**
     * @param array<string, mixed> $jobCommand
     */
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

    public function getLastDispatchedAt(): ?\DateTimeImmutable
    {
        return $this->lastDispatchedAt;
    }

    public function setLastDispatchedAt(?\DateTimeImmutable $lastDispatchedAt): void
    {
        $this->lastDispatchedAt = $lastDispatchedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): void
    {
        $this->revokedAt = $revokedAt;
    }

    public function getRevokedFor(): ?int
    {
        return $this->revokedFor;
    }

    public function setRevokedFor(?int $revokedFor): void
    {
        $this->revokedFor = $revokedFor;
    }

    public function getRevokeAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->revokeAcceptedAt;
    }

    public function setRevokeAcceptedAt(?\DateTimeImmutable $revokeAcceptedAt): void
    {
        $this->revokeAcceptedAt = $revokeAcceptedAt;
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

    /**
     * @return ?array<string, mixed>
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    /**
     * @param ?array<string, mixed> $result
     */
    public function setResult(?array $result): void
    {
        $this->result = $result;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): void
    {
        $this->resolvedAt = $resolvedAt;
    }

    public function getAttemptsCount(): int
    {
        return $this->attemptsCount;
    }

    public function setAttemptsCount(int $attemptsCount): void
    {
        $this->attemptsCount = $attemptsCount;
    }

    /**
     * @return ?array{failedAt: string, errorCode: int, errorMessage: string, errorLine: int, errorFile: string, previousErrorCode: ?int, previousErrorMessage: ?string}
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @param ?array{failedAt: string, errorCode: int, errorMessage: string, errorLine: int, errorFile: string, previousErrorCode: ?int, previousErrorMessage: ?string} $errors
     */
    public function setErrors(?array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * @return array{maxRetries: int, timeout: ?int}
     */
    public function getJobConfiguration(): array
    {
        return $this->jobConfiguration;
    }

    /**
     * @param array{maxRetries: int, timeout: ?int} $jobConfiguration
     */
    public function setJobConfiguration(array $jobConfiguration): void
    {
        $this->jobConfiguration = $jobConfiguration;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getSealedAt(): ?\DateTimeImmutable
    {
        return $this->sealedAt;
    }

    public function setSealedAt(?\DateTimeImmutable $sealedAt): void
    {
        $this->sealedAt = $sealedAt;
    }

    public function getSealedDue(): ?int
    {
        return $this->sealedDue;
    }

    public function setSealedDue(?int $sealedDue): void
    {
        $this->sealedDue = $sealedDue;
    }

    public function getLastAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->lastAcceptedAt;
    }

    public function setLastAcceptedAt(?\DateTimeImmutable $lastAcceptedAt): void
    {
        $this->lastAcceptedAt = $lastAcceptedAt;
    }

    public function getDispatches(): ?array
    {
        return $this->dispatches;
    }

    public function setDispatches(?array $dispatches): void
    {
        $this->dispatches = $dispatches;
    }

    public function getAcceptances(): ?array
    {
        return $this->acceptances;
    }

    public function setAcceptances(?array $acceptances): void
    {
        $this->acceptances = $acceptances;
    }
}
