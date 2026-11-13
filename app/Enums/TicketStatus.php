<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Serving = 'serving';
    case Done = 'done';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /** Allowed transitions: a ticket only moves forward through the queue. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Waiting => [self::Called, self::Cancelled],
            self::Called => [self::Serving, self::Skipped, self::Waiting],
            self::Serving => [self::Done],
            default => [],
        }, true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Waiting, self::Called, self::Serving], true);
    }
}
