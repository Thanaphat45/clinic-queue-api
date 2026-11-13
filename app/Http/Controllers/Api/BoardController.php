<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class BoardController extends Controller
{
    /** Public display board: who is being called or served now, and who is next. */
    public function show(Service $service): JsonResponse
    {
        $today = Ticket::where('service_id', $service->id)->today();

        $now = (clone $today)->whereIn('status', [TicketStatus::Called, TicketStatus::Serving])
            ->orderByDesc('called_at')->get(['number', 'counter', 'status']);
        $next = (clone $today)->where('status', TicketStatus::Waiting)
            ->orderBy('sequence')->limit(5)->pluck('number');

        return response()->json([
            'service' => ['code' => $service->code, 'name' => $service->name],
            'now' => $now->map(fn ($t) => ['number' => $t->number, 'counter' => $t->counter, 'status' => $t->status->value]),
            'next' => $next,
            'waiting' => (clone $today)->where('status', TicketStatus::Waiting)->count(),
        ]);
    }
}
