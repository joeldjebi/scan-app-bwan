<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode d'identification du véhicule lors d'un passage : scan du QR code ou saisie
 * manuelle de l'immatriculation par l'agent (traçabilité des saisies manuelles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('method', 10)->default('qr')->after('result');
            $table->index(['event_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'method']);
            $table->dropColumn('method');
        });
    }
};
