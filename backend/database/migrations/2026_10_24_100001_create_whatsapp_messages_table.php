<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des alertes WhatsApp : une ligne par tentative d'envoi (queued, puis sent avec le wamid
     * de Meta ou failed avec l'erreur), mise à jour ensuite par les statuts du webhook.
     * Ne stocke jamais de texte de message échangé.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template', 64);
            $table->string('to', 20);
            $table->string('context')->nullable();
            $table->string('wamid')->nullable()->unique();
            $table->string('status', 16)->default('queued');
            $table->string('error_code', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
