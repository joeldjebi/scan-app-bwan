<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les QR codes sont désormais générés à la demande et envoyés directement au navigateur :
 * plus aucun export n'est conservé sur le serveur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exports');
    }

    public function down(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
};
