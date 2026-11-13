<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'number' => $this->number,
            'service' => $this->whenLoaded('service', fn () => ['code' => $this->service->code, 'name' => $this->service->name]),
            'status' => $this->status->value,
            'counter' => $this->counter,
            'issued_at' => $this->created_at?->toIso8601String(),
            'called_at' => $this->called_at?->toIso8601String(),
            'served_at' => $this->served_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
