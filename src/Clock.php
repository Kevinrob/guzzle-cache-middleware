<?php

namespace Kevinrob\GuzzleCache;

use Psr\Clock\ClockInterface;

/**
 * Global clock provider to allow time mocking in tests.
 */
final class Clock
{
    /**
     * @var ClockInterface|null
     */
    private static $instance = null;

    /**
     * Set a custom clock instance (e.g., for testing).
     *
     * @param ClockInterface $clock
     */
    public static function set(ClockInterface $clock): void
    {
        self::$instance = $clock;
    }

    /**
     * Get the current clock instance.
     *
     * @return ClockInterface
     */
    public static function get(): ClockInterface
    {
        if (self::$instance === null) {
            self::$instance = new class implements ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable();
                }
            };
        }

        return self::$instance;
    }

    /**
     * Get the current time as a DateTimeImmutable.
     *
     * @return \DateTimeImmutable
     */
    public static function now(): \DateTimeImmutable
    {
        return self::get()->now();
    }
}
