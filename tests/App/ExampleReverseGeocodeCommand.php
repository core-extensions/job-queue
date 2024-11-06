<?php

declare(strict_types=1);


use Webmozart\Assert\Assert;

/**
 * (бонусы: typed props)
 * (минусы: сериализация - решаемо)
 *
 * @app
 */
final class ExampleReverseGeocodeCommand extends \CoreExtensions\JobQueueBundle\AbstractJobCommand
{
    /**
     * (числа могут случайно использовать повторно)
     * (в любом случае оставляем на усмотрение клиента, может и глобальный enum-использовать)
     */
    public const JOB_TYPE = 'app.geocoding.command.reverse_geocode_address';

    private float $lon;
    private float $lat;

    public function getJobType(): string
    {
        return self::JOB_TYPE;
    }

    // unbound
    public static function fromLonLat(float $lon, float $lat): self
    {
        $res = new self();
        $res->deserialize(['lon' => $lon, 'lat' => $lat]);

        return $res;
    }

    public function serialize(): array
    {
        return [
            'lon' => $this->getLon(),
            'lat' => $this->getLat(),
        ];
    }

    public function deserialize(array $array): void
    {
        Assert::float($array['lon'], sprintf('Invalid value of "%s" in "%s"', 'lon', __METHOD__));
        $this->lon = $array['lon'];

        Assert::float($array['lat'], sprintf('Invalid value of "%s" in "%s"', 'lat', __METHOD__));
        $this->lat = $array['lat'];
    }

    /*
    public static function fromJob(Job $job): AbstractJobCommand
    {
        Assert::same($job->getJobType(), self::JOB_TYPE, sprintf('Job type mismatch "%s"', __METHOD__));

        $res = self::fromLonLat($options['lon'], $options['lat']);
        $res->bindJob($job);

        return $res;
    }
    */

    public function getLon(): float
    {
        return $this->lon;
    }

    public function getLat(): float
    {
        return $this->lat;
    }
}
