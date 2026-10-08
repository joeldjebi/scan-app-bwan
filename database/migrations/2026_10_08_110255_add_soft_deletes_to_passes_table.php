<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un pass supprimé est conservé (soft delete) pour que la synchronisation hors ligne
 * puisse signaler sa suppression aux applications mobiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['event_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
