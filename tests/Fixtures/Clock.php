<?php

namespace Tests\Fixtures;

class Clock
{
    /**
     * Get the current date and time as a formatted string.
     */
    public function now(): string
    {
        return now()->toDateTimeString();
    }
}
