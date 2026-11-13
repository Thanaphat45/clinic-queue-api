<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Service;
use App\Models\Ticket;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private QueueService $queue) {}

    /** Kiosk: take a number. Public, rate limited. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'patient_name' => ['nullable', 'string', 'max:100'],
        ]);

        $ticket = $this->queue->issue(Service::findOrFail($data['service_id']), $data['patient_name'] ?? null);

        return (new TicketResource($ticket->load('service')))
            ->additional($this->queue->position($ticket))
            ->response()->setStatusCode(201);
    }

    /** Patient checks a ticket by its public UUID (the QR on the printed slip). */
    public function show(Ticket $ticket): JsonResponse
    {
        return (new TicketResource($ticket->load('service')))
            ->additional($this->queue->position($ticket))
            ->response();
    }

    /** Staff: call the next waiting patient to a counter. */
    public function callNext(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate(['counter' => ['required', 'string', 'max:20']]);
        $ticket = $this->queue->callNext($service, $request->user(), $data['counter']);

        if (! $ticket) {
            return response()->json(['message' => 'No one is waiting.'], 404);
        }

        return (new TicketResource($ticket->load('service')))->response();
    }

    /** Staff: move a ticket through its lifecycle. */
    public function updateStatus(Request $request, Ticket $ticket): TicketResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ]);

        return new TicketResource(
            $this->queue->transition($ticket, TicketStatus::from($data['status']), $request->user())->load('service')
        );
    }
}
