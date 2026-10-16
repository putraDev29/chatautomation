<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\CarMedia;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppIntegration;
use Database\Seeders\BintangMobilDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseAndModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_runs_idempotently_and_populates_data(): void
    {
        // Run first time
        $this->seed(BintangMobilDemoSeeder::class);

        $this->assertDatabaseCount('wa_cars', 18);
        $this->assertDatabaseCount('wa_customers', 5);
        $this->assertGreaterThanOrEqual(5, Conversation::count());
        $this->assertGreaterThanOrEqual(10, Message::count());

        // Run second time (Idempotency test)
        $this->seed(BintangMobilDemoSeeder::class);

        $this->assertDatabaseCount('wa_cars', 18);
        $this->assertDatabaseCount('wa_customers', 5);
    }

    public function test_car_and_media_relationships(): void
    {
        $this->seed(BintangMobilDemoSeeder::class);

        $car = Car::where('stock_code', 'BM-2022-AV01')->first();
        $this->assertNotNull($car);
        $this->assertNotEmpty($car->media);
        $this->assertInstanceOf(CarMedia::class, $car->media->first());
        $this->assertEquals($car->id, $car->media->first()->car_id);
    }

    public function test_customer_conversation_and_message_relationships(): void
    {
        $this->seed(BintangMobilDemoSeeder::class);

        $customer = Customer::where('whatsapp_number', '6281234567801')->first();
        $this->assertNotNull($customer);
        $this->assertTrue($customer->conversations()->exists());

        $conversation = $customer->conversations()->first();
        $this->assertTrue($conversation->messages()->exists());
        $this->assertInstanceOf(Message::class, $conversation->messages()->first());
    }

    public function test_customer_lead_relationship(): void
    {
        $this->seed(BintangMobilDemoSeeder::class);

        $lead = Lead::whereNotNull('car_id')->first();
        $this->assertNotNull($lead);
        $this->assertInstanceOf(Customer::class, $lead->customer);
        $this->assertInstanceOf(Car::class, $lead->car);
    }

    public function test_integration_current_singleton(): void
    {
        $integration = WhatsAppIntegration::current();
        $this->assertNotNull($integration);
        $this->assertEquals('simulation', $integration->whatsapp_mode);
        $this->assertEquals('simulation', $integration->ai_driver);
    }
}
