<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entités extérieures autorisées à scanner les pass via l'API partenaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('webhook_url', 500)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamps();
        });

        // Clés API : seul le hachage SHA-256 est conservé, la clé n'est affichée qu'à sa création.
        Schema::create('partner_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('prefix', 20);
            $table->string('key_hash', 64)->unique();
            $table->json('abilities');
            $table->json('allowed_ips')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });

        // Événements sur lesquels un partenaire peut scanner (aucun par défaut).
        Schema::create('event_partner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'partner_id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 50);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('event_partner');
        Schema::dropIfExists('partner_api_keys');
        Schema::dropIfExists('partners');
    }
};
