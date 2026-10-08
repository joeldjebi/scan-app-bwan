<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logo, affiche et couleurs de l'événement (formulaire d'enregistrement brandé).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('description');
            $table->string('poster_path')->nullable()->after('logo_path');
            $table->boolean('theme_from_poster')->default(true)->after('poster_path');
            $table->string('primary_color', 7)->nullable()->after('theme_from_poster');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'poster_path', 'theme_from_poster', 'primary_color', 'secondary_color']);
        });
    }
};
