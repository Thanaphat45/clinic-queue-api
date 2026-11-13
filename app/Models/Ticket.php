<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'service_id', 'queue_date', 'sequence', 'number', 'patient_name',
        'status', 'counter', 'handled_by', 'called_at', 'served_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'queue_date' => 'date',
            'called_at' => 'datetime',
            'served_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('queue_date', today());
    }
}
