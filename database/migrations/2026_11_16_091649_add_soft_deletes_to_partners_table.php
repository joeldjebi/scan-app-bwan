<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un partenaire supprimé est conservé (soft delete) : ses passages restent attribués à son nom
 * dans l'historique des événements, alors que ses clés et ses accès sont retirés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
