<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le premier administrateur devient le propriétaire de la plateforme : aucun autre
 * administrateur ne peut le modifier, le désactiver ni le supprimer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('is_active');
        });

        $firstAdminId = DB::table('users')->where('role', 'admin')->min('id');

        if ($firstAdminId) {
            DB::table('users')->where('id', $firstAdminId)->update(['is_owner' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
