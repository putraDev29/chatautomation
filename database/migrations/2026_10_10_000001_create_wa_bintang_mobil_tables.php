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
        // 1. Integrations & Settings Configuration Table
        Schema::create('wa_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('default');
            $table->string('whatsapp_mode')->default('simulation'); // simulation | production
            $table->string('whatsapp_provider')->default('simulated'); // simulated | meta
            $table->string('ai_driver')->default('simulation'); // simulation | openai | gemini
            $table->boolean('is_ai_enabled')->default(true);
            $table->string('ai_model')->default('gpt-4o-mini');
            $table->integer('ai_max_tokens')->default(500);
            $table->time('business_hours_start')->default('08:00:00');
            $table->time('business_hours_end')->default('21:00:00');
            $table->boolean('handover_auto_trigger')->default(true);
            $table->text('handover_keywords')->nullable();
            $table->text('default_greeting')->nullable();
            $table->integer('daily_ai_limit')->default(1000);
            $table->integer('current_daily_ai_usage')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // 2. Cars Stock Table
        Schema::create('wa_cars', function (Blueprint $table) {
            $table->id();
            $table->string('stock_code')->unique();
            $table->string('brand'); // e.g. Toyota, Honda
            $table->string('model'); // e.g. Avanza, Innova Reborn, Brio
            $table->string('variant')->nullable(); // e.g. 1.5 G CVT TSS, 2.4 V AT
            $table->integer('year');
            $table->decimal('price', 15, 2);
            $table->decimal('internal_special_price', 15, 2)->nullable(); // only authorized admin
            $table->string('transmission'); // Manual, Otomatis, CVT
            $table->string('fuel_type')->default('Bensin'); // Bensin, Diesel, Hybrid
            $table->string('color');
            $table->integer('mileage')->default(0);
            $table->date('tax_date')->nullable();
            $table->string('stnk_status')->default('HIDUP'); // HIDUP, MATI, DALAM PROSES
            $table->string('bpkb_status')->default('READY'); // READY, LEASING, DALAM PROSES
            $table->string('stock_status')->default('TERSEDIA'); // TERSEDIA, BOOKING, TERJUAL, TIDAK AKTIF
            $table->text('description')->nullable();
            $table->string('location')->default('Showroom Pusat Surabaya');
            $table->string('main_photo')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_demo')->default(true);
            $table->timestamps();

            $table->index(['brand', 'model']);
            $table->index('stock_status');
            $table->index('price');
        });

        // 3. Car Media Table
        Schema::create('wa_car_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('wa_cars')->cascadeOnDelete();
            $table->string('media_type'); // photo_exterior, photo_interior, photo_engine, photo_odometer, video
            $table->string('file_path');
            $table->string('file_url')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('caption')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['car_id', 'media_type']);
        });

        // 4. Customers Table
        Schema::create('wa_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('whatsapp_number')->unique();
            $table->string('city')->nullable();
            $table->string('customer_source')->default('WHATSAPP');
            $table->text('internal_notes')->nullable();
            $table->string('communication_status')->default('ACTIVE'); // ACTIVE, BLOCKED, OPT_OUT
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index('whatsapp_number');
        });

        // 5. Conversations Table
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->string('wa_conversation_id')->nullable();
            $table->string('status')->default('BOT_ACTIVE'); // BOT_ACTIVE, NEEDS_ADMIN, ADMIN_ACTIVE, WAITING_CUSTOMER, CLOSED
            $table->boolean('is_bot_active')->default(true);
            $table->boolean('is_admin_takeover')->default(false);
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('interested_car_id')->nullable()->constrained('wa_cars')->nullOnDelete();
            $table->string('interest_level')->default('MEDIUM'); // LOW, MEDIUM, HIGH, HOT
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('handover_at')->nullable();
            $table->text('handover_reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_bot_active']);
            $table->index('last_message_at');
        });

        // 6. Messages Table (with idempotency support)
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->string('wa_message_id')->nullable()->unique(); // WhatsApp message ID for idempotency
            $table->string('direction'); // inbound, outbound
            $table->string('sender_type'); // customer, bot, admin, system
            $table->string('message_type')->default('text'); // text, image, video, document, interactive
            $table->text('message_body')->nullable();
            $table->string('media_id')->nullable();
            $table->string('media_url')->nullable();
            $table->string('delivery_status')->default('pending'); // pending, sent, delivered, read, failed
            $table->string('error_code')->nullable();
            $table->json('metadata')->nullable(); // sanitized metadata
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // 7. Leads & Sales Activities Table
        Schema::create('wa_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('wa_customers')->cascadeOnDelete();
            $table->foreignId('car_id')->nullable()->constrained('wa_cars')->nullOnDelete();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('payment_method')->default('UNKNOWN'); // CASH, CREDIT, UNKNOWN
            $table->string('destination_city')->nullable();
            $table->string('lead_status')->default('NEW'); // NEW, QUALIFIED, HOT, NEGOTIATION, BOOKED, WON, LOST
            $table->string('interest_level')->default('MEDIUM'); // LOW, MEDIUM, HIGH, HOT
            $table->dateTime('test_drive_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lead_status', 'interest_level']);
        });

        // 8. Handover & Audit Logs Table
        Schema::create('wa_handover_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->string('trigger_type')->default('CUSTOMER_REQUEST'); // AUTO_INTENT, CUSTOMER_REQUEST, MANUAL_ADMIN, FALLBACK
            $table->string('action'); // HANDOVER_REQUESTED, ADMIN_TAKEOVER, BOT_RESUMED, CLOSED
            $table->text('reason')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // 9. Shipping Rates & Inter-city Delivery Table
        Schema::create('wa_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('origin_city')->default('Surabaya');
            $table->string('destination_city');
            $table->string('transport_method')->default('Car Towing Single'); // Car Towing Single, Car Carrier
            $table->decimal('estimated_cost_min', 12, 2)->nullable();
            $table->decimal('estimated_cost_max', 12, 2)->nullable();
            $table->integer('estimated_days_min')->default(1);
            $table->integer('estimated_days_max')->default(3);
            $table->text('operational_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['origin_city', 'destination_city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_shipping_rates');
        Schema::dropIfExists('wa_handover_logs');
        Schema::dropIfExists('wa_leads');
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_conversations');
        Schema::dropIfExists('wa_customers');
        Schema::dropIfExists('wa_car_media');
        Schema::dropIfExists('wa_cars');
        Schema::dropIfExists('wa_integrations');
    }
};
