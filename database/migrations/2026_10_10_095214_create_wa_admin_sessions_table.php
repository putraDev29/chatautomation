<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wa_admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->unique()->index();
            $table->string('session_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('status')->default('disconnected'); // disconnected, qr_ready, connected
            $table->longText('qr_code')->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->string('wa_session_id')->nullable()->after('assigned_admin_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropColumn('wa_session_id');
        });
        Schema::dropIfExists('wa_admin_sessions');
    }
};
