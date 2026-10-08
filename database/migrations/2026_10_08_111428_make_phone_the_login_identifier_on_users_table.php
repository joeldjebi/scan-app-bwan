<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les agents et chefs se connectent avec leur numéro de téléphone : il devient unique
 * (stocké sans espaces ni séparateurs) et l'email devient facultatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function (object $user) {
            $phone = preg_replace('/(?!^\+)[^\d]/', '', trim($user->phone));
            DB::table('users')->where('id', $user->id)->update(['phone' => $phone !== '' ? $phone : null]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
