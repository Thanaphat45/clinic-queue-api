<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();          // what the patient's ticket/QR links to
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('sequence');           // 1, 2, 3 ... per service per day
            $table->string('number', 8);                   // "A001"
            $table->string('patient_name')->nullable();
            $table->string('status')->default('waiting');
            $table->string('counter', 20)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'queue_date', 'sequence']);
            $table->index(['service_id', 'queue_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
