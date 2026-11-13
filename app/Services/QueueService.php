<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QueueService
{
    /** Issue the next ticket number for a service (A001, A002 ...), restarting daily. */
    public function issue(Service $service, ?string $patientName = null): Ticket
    {
        if (! $service->is_open) {
            throw ValidationException::withMessages(['service_id' => 'This service is closed today.']);
        }

        return DB::transaction(function () use ($service, $patientName) {
            // Lock the service row so concurrent kiosks serialize on the sequence.
            Service::whereKey($service->id)->lockForUpdate()->first();

            $next = (int) Ticket::where('service_id', $service->id)->today()->max('sequence') + 1;

            return Ticket::create([
                'public_id' => (string) Str::uuid(),
                'service_id' => $service->id,
                'queue_date' => today(),
                'sequence' => $next,
                'number' => sprintf('%s%03d', $service->code, $next),
                'patient_name' => $patientName,
                'status' => TicketStatus::Waiting,
            ]);
        });
    }

    /** Call the oldest waiting ticket to a counter. Returns null when nobody is waiting. */
    public function callNext(Service $service, User $staff, string $counter): ?Ticket
    {
        return DB::transaction(function () use ($service, $staff, $counter) {
            $ticket = Ticket::where('service_id', $service->id)->today()
                ->where('status', TicketStatus::Waiting)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => TicketStatus::Called,
                'counter' => $counter,
                'handled_by' => $staff->id,
                'called_at' => now(),
            ]);

            return $ticket;
        });
    }

    public function transition(Ticket $ticket, TicketStatus $to, User $staff): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $staff) {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! $ticket->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move a ticket from {$ticket->status->value} to {$to->value}.",
                ]);
            }

            $changes = ['status' => $to, 'handled_by' => $staff->id];
            if ($to === TicketStatus::Serving) {
                $changes['served_at'] = now();
            }
            if (in_array($to, [TicketStatus::Done, TicketStatus::Skipped, TicketStatus::Cancelled], true)) {
                $changes['finished_at'] = now();
            }
            if ($to === TicketStatus::Waiting) {   // returned to the queue
                $changes += ['counter' => null, 'called_at' => null];
            }

            $ticket->update($changes);

            return $ticket;
        });
    }

    /** How many people are ahead, and a rough wait estimate in minutes. */
    public function position(Ticket $ticket): array
    {
        if ($ticket->status !== TicketStatus::Waiting) {
            return ['ahead' => 0, 'estimated_wait_minutes' => 0];
        }

        $ahead = Ticket::where('service_id', $ticket->service_id)
            ->whereDate('queue_date', $ticket->queue_date)
            ->where('status', TicketStatus::Waiting)
            ->where('sequence', '<', $ticket->sequence)
            ->count();

        return [
            'ahead' => $ahead,
            'estimated_wait_minutes' => $ahead * $ticket->service->avg_minutes,
        ];
    }
}
