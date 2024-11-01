<?php

declare(strict_types=1);


use App\Bundle\CoreBundle\Service\Geocoder\GeocoderInterface;
use App\Bundle\JobsBundle\Entity\Job;
use App\Bundle\JobsBundle\Exception\JobUnhandledException;
use App\Bundle\JobsBundle\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Webmozart\Assert\Assert;

/**
 * @app
 */
class ExampleReverseGeocodeCommandHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private GeocoderInterface $geocoder;
    private JobRepository $jobRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        GeocoderInterface $geocoder,
        JobRepository $jobRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->geocoder = $geocoder;
        $this->jobRepository = $jobRepository;
        $this->entityManager = $entityManager;
    }

    public function __invoke(ExampleReverseGeocodeCommand $jobCommand): void
    {
        try {
            $jobId = $jobCommand->getJobId();
            $this->logger->info(sprintf('Job "%s" has been started for processing in "%s"', $jobId, __METHOD__));

            $job = $this->jobRepository->find($jobId);
            Assert::notNull($job, sprintf('Job "%s" not found in "%s"', $jobId, __METHOD__));

            $this->handle($job, $jobCommand);
        } catch (\Throwable $tr) {
            // здесь поймали исключение которое нельзя обработать
            throw new JobUnhandledException(sprintf('Unhandled exception in "%s"', __METHOD__), null, $tr);
        }

        $this->entityManager->persist($job);
        $this->entityManager->flush();
    }

    public function handle(Job $job, ExampleReverseGeocodeCommand $jobCommand): void
    {
        try {
            $response = $this->geocoder->reverseGeocode($jobCommand->getLon(), $jobCommand->getLat());
            if (null === $response) {
                throw new JobUnhandledException('Empty result of geocoding, for example we should stop.');
            }
            $job->resolved($response->toScalar());
        } catch (\Throwable $tr) {
            $job->failedWithException($tr);
        }
    }
}
