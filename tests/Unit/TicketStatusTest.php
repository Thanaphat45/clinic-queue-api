<?php

namespace Tests\Unit;

use App\Enums\TicketStatus as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    public static function transitions(): array
    {
        return [
            'waiting -> called' => [S::Waiting, S::Called, true],
            'waiting -> cancelled' => [S::Waiting, S::Cancelled, true],
            'waiting -> done (skip ahead)' => [S::Waiting, S::Done, false],
            'called -> serving' => [S::Called, S::Serving, true],
            'called -> skipped' => [S::Called, S::Skipped, true],
            'called -> back to waiting' => [S::Called, S::Waiting, true],
            'serving -> done' => [S::Serving, S::Done, true],
            'serving -> waiting' => [S::Serving, S::Waiting, false],
            'done is terminal' => [S::Done, S::Waiting, false],
            'skipped is terminal' => [S::Skipped, S::Called, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(S $from, S $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_open_statuses(): void
    {
        $this->assertTrue(S::Waiting->isOpen());
        $this->assertTrue(S::Serving->isOpen());
        $this->assertFalse(S::Done->isOpen());
        $this->assertFalse(S::Cancelled->isOpen());
    }
}
