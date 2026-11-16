<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passage effectué par un partenaire via l'API : `user_id` est alors vide. `operator_ref` identifie
 * l'agent chez le partenaire ; `idempotency_key` évite les doublons lors d'un nouvel envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('operator_ref', 100)->nullable()->after('partner_id');
            $table->string('idempotency_key', 100)->nullable()->after('client_uuid');

            $table->unique(['partner_id', 'idempotency_key']);
            $table->index(['partner_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropUnique(['partner_id', 'idempotency_key']);
            $table->dropIndex(['partner_id', 'scanned_at']);
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn(['operator_ref', 'idempotency_key']);
        });
    }
};
