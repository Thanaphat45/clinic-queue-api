<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /** Admin: counts per service for today, plus average wait (issued to called). */
    public function today(): JsonResponse
    {
        $rows = Service::orderBy('code')->get()->map(function (Service $s) {
            $tickets = Ticket::where('service_id', $s->id)->today()->get();
            $waits = $tickets->filter(fn ($t) => $t->called_at)
                ->map(fn ($t) => $t->created_at->diffInSeconds($t->called_at));

            return [
                'service' => $s->code,
                'issued' => $tickets->count(),
                'waiting' => $tickets->where('status', TicketStatus::Waiting)->count(),
                'done' => $tickets->where('status', TicketStatus::Done)->count(),
                'skipped' => $tickets->where('status', TicketStatus::Skipped)->count(),
                'avg_wait_minutes' => $waits->isEmpty() ? null : round($waits->avg() / 60, 1),
            ];
        });

        return response()->json(['date' => today()->toDateString(), 'services' => $rows]);
    }
}
