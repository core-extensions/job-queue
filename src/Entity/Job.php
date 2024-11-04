<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue\Entity;

use CoreExtensions\JobQueue\FailInfo;
use CoreExtensions\JobQueue\Exception\JobSealedInteractionException;
use CoreExtensions\JobQueue\Helpers;
use CoreExtensions\JobQueue\JobCommandInterface;
use CoreExtensions\JobQueue\JobConfiguration;
use CoreExtensions\JobQueue\JobManager;
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
 * // TODO: optimistic locking чтобы с UI не могли работать со старыми данными
 * // TODO: sealing ?
 * // TODO: set && get использовать?
 *
 * @ORM\Entity(repositoryClass="CoreExtensions\JobQueue\Repository\JobRepository")
 * @ORM\Table(name="orm_jobs", schema="jobs"))
 */
class Job
{
    /**
     * Признак что остановили временно для re-run после deploy (обсуждаемо).
     */
    // Признак что остановили временно для re-run после deploy (обсуждаемо).
    public const REVOKED_FOR_RE_RUN = 10;

    /**
     * Причины для sealed.
     */
    public const SEALED_DUE_REVOKED = 10;
    public const SEALED_DUE_RESOLVED = 20;
    public const SEALED_DUE_MAX_RETRIES_REACHED = 30;
    public const SEALED_DUE_TIMEOUT = 31;

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
     * Дата постановки в очередь.
     *
     * @ORM\Column(type="datetimetz_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $dispatchedAt = null;

    /**
     * Идентификатор сообщения в шине.
     * (в rabbit вида "amq.ctag-{random_string}-{number}")
     */
    private ?string $dispatchedMessageId = null;

    /**
     * Дата начала обработки.
     *
     * @ORM\Column(type="datetimetz_immutable")
     */
    private ?\DateTimeImmutable $acceptedAt = null;

    /**
     * Информация о worker который сделал accepted.
     *
     * @see WorkerInfo
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $workerInfo = null;

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
    private ?\DateTimeImmutable $revokeConfirmedAt = null;

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
     * Результат работы {*}.
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
     *  4) timeout reached => sealed
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
     * TODO: могут дублирование JobCommand, если jobId не будет меняться @see JobManager::enqueueChain(),
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
     */
    public function dispatched(\DateTimeImmutable $dispatchedAt, ?string $dispatchedMessageId): void
    {
        Assert::greaterThanEq(
            $dispatchedAt->getTimestamp(),
            $this->getCreatedAt()->getTimestamp(),
            sprintf(
                'Job "%s" cannot be dispatched "%s" earlier than created "%s" in "%s"',
                $this->getJobId(),
                Helpers::serializeDateTime($dispatchedAt),
                Helpers::serializeDateTime($this->getDispatchedAt()),
                __METHOD__
            )
        );
        Assert::nullOrStringNotEmpty(
            $dispatchedMessageId,
            sprintf('Invalid param "%s" in "%s"', 'dispatchedMessageId', __METHOD__)
        );
        $this->assertJobNotSealed('dispatched');

        $this->setDispatchedAt($dispatchedAt);
        $this->setDispatchedMessageId($dispatchedMessageId);
    }

    /**
     * Вызывается когда handler получил задачу из bus.
     */
    public function accepted(\DateTimeImmutable $acceptedAt, WorkerInfo $workerInfo): void
    {
        $dispatchedAt = $this->getDispatchedAt();

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
                Helpers::serializeDateTime($this->getDispatchedAt()),
                __METHOD__
            )
        );
        $this->assertJobNotSealed('accepted');

        $this->setAcceptedAt($acceptedAt);
        $this->setWorkerInfo($workerInfo->toArray());
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

        $this->setRevokeConfirmedAt($revokeConfirmedAt);
        $this->sealed($revokeConfirmedAt, self::SEALED_DUE_REVOKED);
    }

    /**
     * Вызывается в handler при успешном выполнении.
     * (result - специально сделал not nullable, чтобы хоть что то о результате всегда писали)
     */
    public function resolved(\DateTimeImmutable $resolvedAt, array $result): void
    {
        $acceptedAt = $this->getAcceptedAt();

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
                Helpers::serializeDateTime($this->getDispatchedAt()),
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
     */
    public function failed(\DateTimeImmutable $failedAt, FailInfo $errorInfo): void
    {
        $acceptedAt = $this->getAcceptedAt();

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
                Helpers::serializeDateTime($this->getDispatchedAt()),
                __METHOD__
            )
        );
        $this->assertJobNotSealed('failed');

        $this->commitFailedAttempt($failedAt, $errorInfo);

        $jobConfiguration = JobConfiguration::fromArray($this->getJobConfiguration());
        $maxRetries = $jobConfiguration->getMaxRetries();

        if ($this->getAttemptsCount() >= $maxRetries) {
            $this->sealed($failedAt, self::SEALED_DUE_MAX_RETRIES_REACHED);
        }
    }

    // TODO: private либо makeСhained здесь
    public function bindToChain(string $chainId, int $chainPosition): void
    {
        $this->assertJobNotSealed('revoked');

        Assert::uuid($chainId, sprintf('Invalid param "%s" in "%s"', 'chainId', __METHOD__));
        Assert::greaterThanEq($chainPosition, 0, sprintf('Invalid param "%s" in "%s"', 'chainPosition', __METHOD__));

        $this->chainId = $chainId;
        $this->chainPosition = $chainPosition;
    }

    public function configure(JobConfiguration $jobConfiguration): void
    {
        $this->assertJobNotSealed('revoked');
        $this->setJobConfiguration($jobConfiguration->toArray());
    }

    public function sealed(\DateTimeImmutable $sealedAt, int $due): void
    {
        $this->setSealedAt($sealedAt);
        $this->setSealedDue($due);
    }

    private function commitFailedAttempt(\DateTimeImmutable $failedAt, FailInfo $failInfo): void
    {
        $this->incAttemptsCount();

        $errors = $this->getErrors() ?? [];
        $errors[] = $failInfo->toArray();

        $this->setErrors($errors);
    }

    private function assertJobNotSealed(string $action): void
    {
        if (null !== $this->getSealedAt()) {
            throw JobSealedInteractionException::fromJob($this, $action);
        }

        Assert::null(
            $this->getSealedAt(),
            sprintf(
                'Failed to apply action "%s" to sealed job "%s" (%d))',
                $action,
                $this->getJobId(),
                $this->getSealedDue()
            )
        );
    }

    private function resolveJobConfiguration(): ?JobConfiguration
    {
        // хранить в поле?
        // TODO: default - значение либо оно же но в сохраненном виде
        return null === $this->getJobConfiguration() ? null : JobConfiguration::fromArray($this->getJobConfiguration());
    }

    private function incAttemptsCount(): void
    {
        $this->setAttemptsCount($this->getAttemptsCount() + 1);
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

    public function getDispatchedMessageId(): ?string
    {
        return $this->dispatchedMessageId;
    }

    public function setDispatchedMessageId(?string $dispatchedMessageId): void
    {
        $this->dispatchedMessageId = $dispatchedMessageId;
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

    public function getRevokeConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->revokeConfirmedAt;
    }

    public function setRevokeConfirmedAt(?\DateTimeImmutable $revokeConfirmedAt): void
    {
        $this->revokeConfirmedAt = $revokeConfirmedAt;
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

    public function getResult(): ?array
    {
        return $this->result;
    }

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

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function setErrors(?array $errors): void
    {
        $this->errors = $errors;
    }

    public function getJobConfiguration(): array
    {
        return $this->jobConfiguration;
    }

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

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): void
    {
        $this->acceptedAt = $acceptedAt;
    }
}
