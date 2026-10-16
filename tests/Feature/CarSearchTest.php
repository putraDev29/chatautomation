<?php

namespace Tests\Feature;

use App\Services\Cars\CarSearchService;
use Database\Seeders\BintangMobilDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarSearchTest extends TestCase
{
    use RefreshDatabase;

    protected CarSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BintangMobilDemoSeeder::class);
        $this->searchService = app(CarSearchService::class);
    }

    public function test_search_by_brand(): void
    {
        $results = $this->searchService->search(['brand' => 'Toyota']);

        $this->assertNotEmpty($results);
        foreach ($results as $car) {
            $this->assertEquals('Toyota', $car->brand);
            $this->assertEquals('TERSEDIA', $car->stock_status);
        }
    }

    public function test_search_by_model(): void
    {
        $results = $this->searchService->search(['model' => 'Innova']);

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('Innova', $results->first()->model);
    }

    public function test_search_by_year(): void
    {
        $results = $this->searchService->search(['year' => 2022]);

        $this->assertNotEmpty($results);
        foreach ($results as $car) {
            $this->assertEquals(2022, $car->year);
        }
    }

    public function test_search_by_budget(): void
    {
        // Search cars with max price 150 million
        $results = $this->searchService->search(['max_price' => 150000000]);

        $this->assertNotEmpty($results);
        foreach ($results as $car) {
            $this->assertLessThanOrEqual(150000000, (float) $car->price);
        }
    }

    public function test_sold_units_are_not_returned_as_available(): void
    {
        // BM-2019-BR15 is sold
        $results = $this->searchService->search(['model' => 'Brio Satya']);

        foreach ($results as $car) {
            $this->assertNotEquals('TERJUAL', $car->stock_status);
        }

        // Check availability explicitly returns sold_count
        $availability = $this->searchService->checkAvailability('Brio Satya');
        $this->assertGreaterThan(0, $availability['sold_count']);
    }

    public function test_parse_query_from_indonesian_customer_text(): void
    {
        $parsed = $this->searchService->parseQueryFromText('inova reborn matic ada gak min?');
        $this->assertEquals('Innova', $parsed['model']);
        $this->assertEquals('Otomatis', $parsed['transmission']);

        $budgetParsed = $this->searchService->parseQueryFromText('Cari mobil keluarga budget 150 jutaan');
        $this->assertArrayHasKey('max_price', $budgetParsed);
        $this->assertEquals(150000000, $budgetParsed['max_price']);
    }
}
