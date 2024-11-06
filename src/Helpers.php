<?php

declare(strict_types=1);

namespace CoreExtensions\JobQueueBundle;

final class Helpers
{
    /**
     * Дата и время с часовым поясом и микросекундами.
     * 2020-12-24 17:21:15.520646+03:00.
     */
    private const API_DATETIME_FORMAT_USEC = 'Y-m-d H:i:s.uP';

    public static function serializeDateTime(?\DateTimeImmutable $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return $date->format(self::API_DATETIME_FORMAT_USEC);
    }

    public static function unserializeDateTime(?string $string): ?\DateTimeImmutable
    {
        if (null === $string) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat(self::API_DATETIME_FORMAT_USEC, $string);
    }
}
