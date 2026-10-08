<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('draft');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('event_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('agent');
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('pass_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10);
            $table->string('color', 7)->default('#2563eb');
            $table->timestamps();

            $table->unique(['event_id', 'code']);
        });

        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_type_id')->constrained()->cascadeOnDelete();
            $table->string('token', 32)->unique();
            $table->unsignedInteger('sequence');
            $table->string('number', 40)->unique();
            $table->string('status', 20)->default('pending');
            $table->string('presence', 10)->default('out');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['pass_type_id', 'sequence']);
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'updated_at']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('plate', 20);
            $table->string('plate_key', 20);
            $table->string('brand', 50);
            $table->string('color', 30);
            $table->string('phone', 30);
            $table->timestamps();

            // Un même véhicule ne peut détenir qu'un pass par événement.
            $table->unique(['event_id', 'plate_key']);
        });

        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10);
            $table->string('result', 10);
            $table->string('reason')->nullable();
            $table->boolean('forced')->default(false);
            $table->boolean('offline')->default(false);
            $table->string('device_id', 100)->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['event_id', 'scanned_at']);
            $table->index(['pass_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('passes');
        Schema::dropIfExists('pass_types');
        Schema::dropIfExists('event_staff');
        Schema::dropIfExists('events');
    }
};
