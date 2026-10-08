<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un QR code inconnu (ou d'un événement auquel l'agent n'est pas affecté) n'a pas
 * d'événement : le passage refusé reste visible dans l'historique de l'agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->foreignId('event_id')->nullable()->change();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->index(['user_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'scanned_at']);
            $table->dropForeign(['event_id']);
            $table->foreignId('event_id')->nullable(false)->change();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }
};
