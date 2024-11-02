<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueue;

use CoreExtensions\JobQueue\Entity\Job;
use Webmozart\Assert\Assert;

/**
 * Abstract вариация JobCommandInterface.
 *
 * @deprecated
 *
 * минусы:
 *  - чтобы не терять jobId придется вызывать parent::fromArray, from::toArray. Что делает Commands не ValueObjects-like.
 */
abstract class AbstractJobCommand
{
    private const KEY_JOB_ID = 'jobId';

    /**
     * (bound)
     * (можно было обойтись без этого поля, но с ним будет удобнее)
     */
    private ?string $jobId = null;

    /**
     * (присутствует здесь потому что удобно доставать в handler)
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * (вызывается только в Job::initNew())
     */
    final public function bindJob(Job $job): void
    {
        // чтобы команды были одноразовые
        Assert::false(
            null !== $this->jobId && $this->jobId !== $job->getJobId(),
            sprintf('Attempt to bind job to already bound JobCommand in "%s"', __METHOD__)
        );

        $this->jobId = $job->getJobId();
    }

    abstract public function getJobType(): string;

    // abstract public function toArray(): array;

    // нужен? вроде не нужен, так как придется тащить jobId
    // abstract public static function fromArray(array $arr);

    /**
     * (должен возвращать bound to Job вариант)
     * // bound
     */
    // abstract public static function fromJob(Job $job);

    /**
     * only for bound
     */
    protected static function fromArray(array $arr): self
    {
        // TODO: 1) либо разрешать не передавать jobId что может быть удобно, потому что fromArray может использоваться часто
        // TODO: 2) сделать отдельный метод для bound, тогда уж fromJob
        // TODO: 3) разрешать так как должен соблюдаться соглашение, но из БД брать отдельный методомчцыцфй
        // TODO: 4) переопределять
        $jobId = $arr[self::KEY_JOB_ID] ?? null;
        Assert::nullOrUuid(
            $jobId,
            sprintf('Invalid param "%s" in "%s"', self::KEY_JOB_ID, __METHOD__)
        );

        $res = new static();
        $res->jobId = $jobId;

        return $res;
    }

    protected function toArray(): array
    {
        return [self::KEY_JOB_ID => $this->jobId];
    }
}
