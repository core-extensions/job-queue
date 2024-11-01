<?php

namespace App\Bundle\JobsBundle;

use App\Bundle\CoreBundle\Service\Geocoder\GeocoderInterface;
use App\Bundle\JobsBundle\Entity\Job;
use Ramsey\Uuid\Uuid;

$job = Job::initNew(
    Uuid::uuid4()->toString(),
    ExampleReverseGeocodeCommand::fromLonLat(0.5, 0.5),
    new \DateTimeImmutable()
);

$jobManager = new JobManager();
$jobManager->enqueueJob($job);

/*
routing:
    # async is whatever name you gave your transport above
    'ReverseGeocodeAddress': async
*/

class ReverseGeocodeAddressHandler
{
    private GeocoderInterface $geocoder;

    public function __construct(GeocoderInterface $geocoder)
    {
        $this->geocoder = $geocoder;
    }

    public function __invoke(ExampleReverseGeocodeCommand $job): void
    {
        // вот тут хочется иметь $jobId, его надо как то достать из envelope?
        // наверно логично его хранить в  stamp
    }
}

