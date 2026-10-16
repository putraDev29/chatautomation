<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\CarMedia;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\HandoverLog;
use App\Models\Lead;
use App\Models\Message;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\WaAdminSession;
use App\Models\WhatsAppIntegration;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BintangMobilDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. WhatsApp & AI Integration Settings
        $integration = WhatsAppIntegration::updateOrCreate(
            ['name' => 'default'],
            [
                'whatsapp_mode' => 'simulation',
                'whatsapp_provider' => 'simulated',
                'ai_driver' => 'simulation',
                'is_ai_enabled' => true,
                'ai_model' => 'gpt-4o-mini',
                'ai_max_tokens' => 500,
                'business_hours_start' => '08:00:00',
                'business_hours_end' => '21:00:00',
                'handover_auto_trigger' => true,
                'handover_keywords' => 'nego,diskon,harga pas,ketemu,test drive,kredit,dp,cicilan,serius,admin,manusia,sales,booking',
                'default_greeting' => 'Halo Kak! Selamat datang di Showroom Bintang Mobil Surabaya. Ada unit mobil yang sedang dicari atau ingin cek stok hari ini? 😊',
                'daily_ai_limit' => 1000,
                'current_daily_ai_usage' => 42,
            ]
        );

        // 2. Demo Admin & Multi-Sales Users
        $admins = [
            [
                'email' => 'admin@bintangmobil.test',
                'name' => 'Admin Showroom Bintang Mobil',
                'whatsapp_number' => '6285729905390',
                'role' => 'superadmin',
            ],
            [
                'email' => 'andi.sales@bintangmobil.test',
                'name' => 'Andi Pratama (Sales Senior)',
                'whatsapp_number' => '6281234567891',
                'role' => 'sales',
            ],
            [
                'email' => 'dewi.sales@bintangmobil.test',
                'name' => 'Dewi Lestari (Sales Counter)',
                'whatsapp_number' => '6281234567892',
                'role' => 'sales',
            ],
            [
                'email' => 'rian.sales@bintangmobil.test',
                'name' => 'Rian Hidayat (Sales Executive)',
                'whatsapp_number' => '6281234567893',
                'role' => 'sales',
            ],
        ];

        $admin = null;
        foreach ($admins as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'whatsapp_number' => $userData['whatsapp_number'],
                    'role' => $userData['role'],
                    'password' => Hash::make('admin123456'),
                    'email_verified_at' => now(),
                ]
            );

            WaAdminSession::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'session_id' => 'admin_' . $user->id,
                    'session_name' => $user->name,
                    'phone_number' => $user->whatsapp_number,
                    'status' => $userData['role'] === 'superadmin' ? 'connected' : 'disconnected',
                    'last_active_at' => now(),
                ]
            );

            if ($userData['role'] === 'superadmin') {
                $admin = $user;
            }
        }

        // 3. Shipping Rates (Surabaya Origin)
        $shippingRoutes = [
            [
                'origin_city' => 'Surabaya',
                'destination_city' => 'Jakarta',
                'transport_method' => 'Car Towing Single / Carrier',
                'estimated_cost_min' => 2500000,
                'estimated_cost_max' => 3800000,
                'estimated_days_min' => 2,
                'estimated_days_max' => 4,
                'operational_notes' => 'Asuransi towing tersedia, unit diantar sampai depan rumah.',
            ],
            [
                'origin_city' => 'Surabaya',
                'destination_city' => 'Denpasar (Bali)',
                'transport_method' => 'Car Towing Single',
                'estimated_cost_min' => 2000000,
                'estimated_cost_max' => 3200000,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'operational_notes' => 'Termasuk tiket penyeberangan kapal feri Ketapang - Gilimanuk.',
            ],
            [
                'origin_city' => 'Surabaya',
                'destination_city' => 'Semarang',
                'transport_method' => 'Car Towing Single',
                'estimated_cost_min' => 1500000,
                'estimated_cost_max' => 2200000,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'operational_notes' => 'Pengiriman via tol Trans Jawa kilat 24 jam.',
            ],
            [
                'origin_city' => 'Surabaya',
                'destination_city' => 'Bandung',
                'transport_method' => 'Car Towing Single / Carrier',
                'estimated_cost_min' => 2700000,
                'estimated_cost_max' => 4000000,
                'estimated_days_min' => 2,
                'estimated_days_max' => 4,
                'operational_notes' => 'Towing bergaransi full cover.',
            ],
            [
                'origin_city' => 'Surabaya',
                'destination_city' => 'Yogyakarta',
                'transport_method' => 'Car Towing Single',
                'estimated_cost_min' => 1300000,
                'estimated_cost_max' => 2000000,
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'operational_notes' => 'Via tol Solo-Ngawi cepat sampai.',
            ],
        ];

        foreach ($shippingRoutes as $route) {
            ShippingRate::updateOrCreate(
                [
                    'origin_city' => $route['origin_city'],
                    'destination_city' => $route['destination_city'],
                ],
                $route
            );
        }

        // 4. Cars (16 Demo Units with rich metadata)
        $carsData = [
            [
                'stock_code' => 'BM-2022-AV01',
                'brand' => 'Toyota',
                'model' => 'Avanza',
                'variant' => '1.5 G TSS CVT',
                'year' => 2022,
                'price' => 218000000,
                'internal_special_price' => 210000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Putih Mutiara',
                'mileage' => 24500,
                'tax_date' => Carbon::now()->addMonths(8),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Toyota Avanza 1.5 G TSS 2022 kondisi istimewa tangan pertama. Fitur keselamatan TSS lengkap, sensor blindspot, lane departure assist. Service record Toyota resmi.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-IN02',
                'brand' => 'Toyota',
                'model' => 'Innova Reborn',
                'variant' => '2.4 V AT Diesel',
                'year' => 2021,
                'price' => 355000000,
                'internal_special_price' => 342000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Diesel',
                'color' => 'Hitam Metalik',
                'mileage' => 48200,
                'tax_date' => Carbon::now()->addMonths(5),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Innova Reborn Diesel 2.4 Tipe V matic. Mesin 2GD super bertenaga & irit. Interior lux ambient light, AC digital dingin nyes, ban tebal 90%.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2022-BR03',
                'brand' => 'Honda',
                'model' => 'Brio',
                'variant' => '1.2 RS CVT',
                'year' => 2022,
                'price' => 188000000,
                'internal_special_price' => 180000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Kuning Carnival',
                'mileage' => 19300,
                'tax_date' => Carbon::now()->addMonths(10),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Honda Brio RS CVT Facelift warna favorit Yellow Carnival. Kilometer rendah original, jok motif sporty orisinil, audio touchscreen Apple CarPlay.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2022-HR04',
                'brand' => 'Honda',
                'model' => 'HR-V',
                'variant' => '1.5 SE CVT All New',
                'year' => 2022,
                'price' => 368000000,
                'internal_special_price' => 355000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Abu-abu Meteoroid Gray',
                'mileage' => 28000,
                'tax_date' => Carbon::now()->addMonths(7),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'All New Honda HR-V Tipe SE panoramic roof, Honda Sensing aktif, electric parking brake, wireless charger. Bebas banjir dan tabrakan.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2022-XP05',
                'brand' => 'Mitsubishi',
                'model' => 'Xpander',
                'variant' => '1.5 Ultimate CVT Facelift',
                'year' => 2022,
                'price' => 262000000,
                'internal_special_price' => 252000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Silver Metalik',
                'mileage' => 31000,
                'tax_date' => Carbon::now()->addMonths(4),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Mitsubishi Xpander Ultimate CVT kasta tertinggi. Suspensi paling empuk di kelasnya, rem tangan elektrik auto-hold, cruise control aktif.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-ER06',
                'brand' => 'Suzuki',
                'model' => 'Ertiga',
                'variant' => '1.5 GX AT',
                'year' => 2021,
                'price' => 195000000,
                'internal_special_price' => 188000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Coklat Metalik',
                'mileage' => 36400,
                'tax_date' => Carbon::now()->addMonths(6),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Suzuki All New Ertiga GX Matic. Sangat irit bensin, AC digital dingin, start-stop engine button, kabin 7 penumpang luas & nyaman.',
                'location' => 'Cabang Sidoarjo',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-TR07',
                'brand' => 'Daihatsu',
                'model' => 'Terios',
                'variant' => '1.5 R Deluxe MT',
                'year' => 2021,
                'price' => 198000000,
                'internal_special_price' => 190000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Hitam Metalik',
                'mileage' => 42000,
                'tax_date' => Carbon::now()->addMonths(9),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Daihatsu All New Terios R Deluxe transmisi manual. Ground clearance tinggi 220mm, kamera 360 keliling, siap liburan luar kota.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2022-FT08',
                'brand' => 'Toyota',
                'model' => 'Fortuner',
                'variant' => '2.8 GR Sport 4x2 AT Diesel',
                'year' => 2022,
                'price' => 525000000,
                'internal_special_price' => 510000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Diesel',
                'color' => 'Hitam Mika',
                'mileage' => 29000,
                'tax_date' => Carbon::now()->addMonths(11),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Fortuner 2.8 GR Sport Diesel mesin 1GD bertenaga 204 PS & torsi 500 Nm. Power back door kick sensor, velg GR asli, kondisi seperti baru.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-PJ09',
                'brand' => 'Mitsubishi',
                'model' => 'Pajero Sport',
                'variant' => '2.4 Dakar 4x2 AT',
                'year' => 2021,
                'price' => 495000000,
                'internal_special_price' => 482000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Diesel',
                'color' => 'Putih Mutiara',
                'mileage' => 38000,
                'tax_date' => Carbon::now()->addMonths(3),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'New Pajero Sport Dakar 4x2 Facelift LCD speedometer digital, sunroof aktif, blind spot monitoring, paddle shift 8-speed.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-RZ10',
                'brand' => 'Toyota',
                'model' => 'Raize',
                'variant' => '1.0 Turbo GR Sport CVT',
                'year' => 2021,
                'price' => 212000000,
                'internal_special_price' => 205000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Merah Two-Tone Hitam',
                'mileage' => 26000,
                'tax_date' => Carbon::now()->addMonths(8),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Toyota Raize 1.0 Turbo GR Sport dua warna sporty. Tarikan turbo gesit, irit bahan bakar, full bodykit GR Sport bawaan pabrik.',
                'location' => 'Cabang Sidoarjo',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-CH11',
                'brand' => 'Honda',
                'model' => 'City Hatchback',
                'variant' => '1.5 RS MT',
                'year' => 2021,
                'price' => 245000000,
                'internal_special_price' => 238000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Abu-abu Metalik',
                'mileage' => 27500,
                'tax_date' => Carbon::now()->addMonths(5),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Honda City Hatchback RS manual 6-speed. Ultra seats multifungsi, suspensi rigid stabil, audio 8 speaker jernih.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2020-CL12',
                'brand' => 'Toyota',
                'model' => 'Calya',
                'variant' => '1.2 G MT',
                'year' => 2020,
                'price' => 119000000,
                'internal_special_price' => 114000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Silver Metalik',
                'mileage' => 52000,
                'tax_date' => Carbon::now()->addMonths(2),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Toyota Calya G Manual 2020. Mobil keluarga 7 seater paling ramah di kantong & hemat bahan bakar. Siap pakai harian atau usaha.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2020-SG13',
                'brand' => 'Daihatsu',
                'model' => 'Sigra',
                'variant' => '1.2 R Deluxe MT',
                'year' => 2020,
                'price' => 112000000,
                'internal_special_price' => 106000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Putih',
                'mileage' => 49000,
                'tax_date' => Carbon::now()->addMonths(6),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Daihatsu Sigra Tipe R Deluxe tipe tertinggi. Sensor parkir depan belakang, velg polished alloy, pajak hidup panjang.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-XL14',
                'brand' => 'Suzuki',
                'model' => 'XL7',
                'variant' => '1.5 Alpha AT',
                'year' => 2021,
                'price' => 215000000,
                'internal_special_price' => 208000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Orange Two-Tone Hitam',
                'mileage' => 34000,
                'tax_date' => Carbon::now()->addMonths(4),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Suzuki XL7 Alpha Matic kasta tertinggi dengan E-Mirror cermin spion pintar merekam depan belakang. Jok kulit mewah & roof rail gagah.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2019-BR15',
                'brand' => 'Honda',
                'model' => 'Brio Satya',
                'variant' => '1.2 E MT',
                'year' => 2019,
                'price' => 135000000,
                'internal_special_price' => 129000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Merah Rallye',
                'mileage' => 58000,
                'tax_date' => Carbon::now()->subMonths(1),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERJUAL', // STATUS TERJUAL UNTUK TESTING
                'description' => 'Unit Brio Satya 2019 ini sudah TERJUAL minggu lalu. Gunakan data ini untuk menguji bahwa bot tidak menawarkan unit terjual.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2020-XP16',
                'brand' => 'Mitsubishi',
                'model' => 'Xpander',
                'variant' => '1.5 Exceed MT',
                'year' => 2020,
                'price' => 195000000,
                'internal_special_price' => 189000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Hitam Metalik',
                'mileage' => 45000,
                'tax_date' => Carbon::now()->addMonths(7),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'BOOKING', // STATUS BOOKING UNTUK TESTING
                'description' => 'Unit Xpander Exceed sedang dalam proses BOOKING oleh customer lain.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2019-MB17',
                'brand' => 'Honda',
                'model' => 'Mobilio',
                'variant' => '1.5 E CVT Facelift',
                'year' => 2019,
                'price' => 168000000,
                'internal_special_price' => 160000000,
                'transmission' => 'Otomatis',
                'fuel_type' => 'Bensin',
                'color' => 'Putih Mutiara',
                'mileage' => 45000,
                'tax_date' => Carbon::now()->addMonths(8),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'Honda Mobilio 1.5 E CVT Facelift 2019. Mesin 1.5L i-VTEC bertenaga & super irit, transmisi matic CVT halus, interior 7-seater lega dan bersih, AC double blower dingin merata, siap pakai harian maupun luar kota.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
            [
                'stock_code' => 'BM-2021-XN18',
                'brand' => 'Daihatsu',
                'model' => 'Xenia',
                'variant' => '1.3 R MT All New',
                'year' => 2021,
                'price' => 175000000,
                'internal_special_price' => 168000000,
                'transmission' => 'Manual',
                'fuel_type' => 'Bensin',
                'color' => 'Silver Metalik',
                'mileage' => 38000,
                'tax_date' => Carbon::now()->addMonths(6),
                'stnk_status' => 'HIDUP',
                'bpkb_status' => 'READY',
                'stock_status' => 'TERSEDIA',
                'description' => 'All New Daihatsu Xenia 1.3 R Manual 2021. Desain modern platform DNGA nyaman & irit, kabin luas sofa mode, AC digital, head unit layar sentuh.',
                'location' => 'Showroom Pusat Surabaya',
                'main_photo' => '/demo-cars/car-placeholder.svg',
            ],
        ];

        $cars = [];
        foreach ($carsData as $c) {
            $modelLower = strtolower($c['model']);
            $photoFile = match(true) {
                str_contains($modelLower, 'avanza') => 'avanza.jpg',
                str_contains($modelLower, 'innova') => 'innova.jpg',
                str_contains($modelLower, 'brio') => 'brio.jpg',
                str_contains($modelLower, 'hr-v') || str_contains($modelLower, 'hrv') => 'hrv.jpg',
                str_contains($modelLower, 'xpander') => 'xpander.jpg',
                str_contains($modelLower, 'mobilio') => 'mobilio.jpg',
                str_contains($modelLower, 'fortuner') || str_contains($modelLower, 'pajero') => 'fortuner.jpg',
                default => 'car-default.jpg',
            };
            $c['main_photo'] = "/demo-cars/{$photoFile}";

            $car = Car::updateOrCreate(
                ['stock_code' => $c['stock_code']],
                $c
            );
            $cars[$c['stock_code']] = $car;

            // Seed Media for each car with genuine .jpg photos
            CarMedia::updateOrCreate(
                ['car_id' => $car->id, 'media_type' => 'photo_exterior', 'sort_order' => 1],
                [
                    'file_path' => "demo-cars/{$photoFile}",
                    'file_url' => "/demo-cars/{$photoFile}",
                    'caption' => "Foto Eksterior {$car->full_name} tampak depan samping",
                    'is_active' => true,
                ]
            );

            CarMedia::updateOrCreate(
                ['car_id' => $car->id, 'media_type' => 'photo_interior', 'sort_order' => 2],
                [
                    'file_path' => 'demo-cars/interior.jpg',
                    'file_url' => '/demo-cars/interior.jpg',
                    'caption' => "Foto Interior & Dashboard {$car->full_name} bersih orisinil",
                    'is_active' => true,
                ]
            );

            CarMedia::updateOrCreate(
                ['car_id' => $car->id, 'media_type' => 'photo_engine', 'sort_order' => 3],
                [
                    'file_path' => 'demo-cars/engine.jpg',
                    'file_url' => '/demo-cars/engine.jpg',
                    'caption' => "Foto Ruang Mesin {$car->full_name} kering & terawat",
                    'is_active' => true,
                ]
            );

            CarMedia::updateOrCreate(
                ['car_id' => $car->id, 'media_type' => 'photo_odometer', 'sort_order' => 4],
                [
                    'file_path' => 'demo-cars/odometer.jpg',
                    'file_url' => '/demo-cars/odometer.jpg',
                    'caption' => "Foto Speedometer {$car->full_name} KM Asli {$car->formatted_mileage}",
                    'is_active' => true,
                ]
            );

            CarMedia::updateOrCreate(
                ['car_id' => $car->id, 'media_type' => 'video', 'sort_order' => 5],
                [
                    'file_path' => "demo-cars/{$photoFile}",
                    'file_url' => "/demo-cars/{$photoFile}",
                    'caption' => "Video Walkthrough & Cek Suara Mesin {$car->full_name}",
                    'is_active' => true,
                ]
            );
        }

        // 5. Demo Customers
        $customersData = [
            [
                'name' => 'Budi Santoso',
                'whatsapp_number' => '6281234567801',
                'city' => 'Surabaya',
                'customer_source' => 'WHATSAPP',
                'internal_notes' => 'Peminat mobil keluarga, cash keras.',
            ],
            [
                'name' => 'Dewi Anggraini',
                'whatsapp_number' => '6281234567802',
                'city' => 'Sidoarjo',
                'customer_source' => 'WHATSAPP',
                'internal_notes' => 'Mencari city car matic untuk kerja.',
            ],
            [
                'name' => 'Hendro Prasetyo',
                'whatsapp_number' => '6281234567803',
                'city' => 'Malang',
                'customer_source' => 'WHATSAPP',
                'internal_notes' => 'Cari Innova Diesel untuk operasional dinas.',
            ],
            [
                'name' => 'Rina Wulandari',
                'whatsapp_number' => '6281234567804',
                'city' => 'Jakarta Barat',
                'customer_source' => 'WHATSAPP',
                'internal_notes' => 'Tanya pengiriman towing ke Jakarta.',
            ],
            [
                'name' => 'Agus Priyanto',
                'whatsapp_number' => '6281234567805',
                'city' => 'Gresik',
                'customer_source' => 'WHATSAPP',
                'internal_notes' => 'Ingin nego keras Fortuner dan jadwal test drive.',
            ],
        ];

        $customers = [];
        foreach ($customersData as $cd) {
            $customer = Customer::updateOrCreate(
                ['whatsapp_number' => $cd['whatsapp_number']],
                $cd
            );
            $customers[$cd['whatsapp_number']] = $customer;
        }

        // 6. Conversations & Messages Demo Scenarios
        // Scenario 1: Budi Santoso -> Tanya Avanza 2022 (BOT_ACTIVE)
        $c1 = Conversation::updateOrCreate(
            ['customer_id' => $customers['6281234567801']->id],
            [
                'status' => 'BOT_ACTIVE',
                'is_bot_active' => true,
                'is_admin_takeover' => false,
                'interested_car_id' => $cars['BM-2022-AV01']->id,
                'interest_level' => 'HIGH',
                'last_message_at' => Carbon::now()->subMinutes(15),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-001'],
            [
                'conversation_id' => $c1->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'message_body' => 'Avanza 2022 ready gak min?',
                'delivery_status' => 'read',
                'created_at' => Carbon::now()->subMinutes(16),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-002'],
            [
                'conversation_id' => $c1->id,
                'direction' => 'outbound',
                'sender_type' => 'bot',
                'message_type' => 'text',
                'message_body' => "Halo Kak Budi! Unit Toyota Avanza 1.5 G TSS CVT (2022) warna Putih Mutiara masih READY TERSEDIA di Showroom Pusat Surabaya. 🚗\n\nHarga: Rp 218.000.000 (Nego halus)\nKilometer: 24.500 km\nTransmisi: Otomatis\nPajak: Hidup panjang\n\nApakah Kakak ingin saya kirimkan foto detail interior & eksteriornya? 😊",
                'delivery_status' => 'delivered',
                'created_at' => Carbon::now()->subMinutes(15),
            ]
        );

        // Scenario 2: Dewi Anggraini -> Cari mobil matic di bawah 150 juta (BOT_ACTIVE)
        $c2 = Conversation::updateOrCreate(
            ['customer_id' => $customers['6281234567802']->id],
            [
                'status' => 'BOT_ACTIVE',
                'is_bot_active' => true,
                'is_admin_takeover' => false,
                'interest_level' => 'MEDIUM',
                'last_message_at' => Carbon::now()->subMinutes(30),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-003'],
            [
                'conversation_id' => $c2->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'message_body' => 'Mobil keluarga budget 150 jutaan ada apa min?',
                'delivery_status' => 'read',
                'created_at' => Carbon::now()->subMinutes(31),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-004'],
            [
                'conversation_id' => $c2->id,
                'direction' => 'outbound',
                'sender_type' => 'bot',
                'message_type' => 'text',
                'message_body' => "Halo Kak Dewi! Untuk pilihan mobil keluarga dengan budget di bawah Rp 150 juta, saat ini kami punya unit terbaik:\n\n1. Toyota Calya 1.2 G MT (2020) - Rp 119.000.000 (Kilometer 52.000 km, Silver)\n2. Daihatsu Sigra 1.2 R Deluxe MT (2020) - Rp 112.000.000 (Kilometer 49.000 km, Putih)\n\nSemua unit surat lengkap dan bergaransi showroom. Tertarik cek unit yang mana Kak? 😊",
                'delivery_status' => 'delivered',
                'created_at' => Carbon::now()->subMinutes(30),
            ]
        );

        // Scenario 3: Hendro Prasetyo -> Minta video & foto Innova Reborn (BOT_ACTIVE)
        $c3 = Conversation::updateOrCreate(
            ['customer_id' => $customers['6281234567803']->id],
            [
                'status' => 'BOT_ACTIVE',
                'is_bot_active' => true,
                'is_admin_takeover' => false,
                'interested_car_id' => $cars['BM-2021-IN02']->id,
                'interest_level' => 'HIGH',
                'last_message_at' => Carbon::now()->subHours(1),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-005'],
            [
                'conversation_id' => $c3->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'message_body' => 'Inova reborn diesel ada foto dan vidio interiornya?',
                'delivery_status' => 'read',
                'created_at' => Carbon::now()->subHours(1)->subMinutes(2),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-006'],
            [
                'conversation_id' => $c3->id,
                'direction' => 'outbound',
                'sender_type' => 'bot',
                'message_type' => 'image',
                'message_body' => 'Berikut foto interior Toyota Innova Reborn 2.4 V AT Diesel (2021). Kondisi jok bersih, ambient light aktif, AC dingin.',
                'media_url' => '/demo-cars/car-interior.svg',
                'delivery_status' => 'delivered',
                'created_at' => Carbon::now()->subHours(1)->subMinutes(1),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-007'],
            [
                'conversation_id' => $c3->id,
                'direction' => 'outbound',
                'sender_type' => 'bot',
                'message_type' => 'video',
                'message_body' => 'Video walkthrough unit & suara mesin Innova Reborn Diesel. Tarikan halus & terawat!',
                'media_url' => '/demo-cars/car-video.svg',
                'delivery_status' => 'delivered',
                'created_at' => Carbon::now()->subHours(1),
            ]
        );

        // Scenario 4: Rina Wulandari -> Tanya Pengiriman Luar Kota Jakarta (BOT_ACTIVE)
        $c4 = Conversation::updateOrCreate(
            ['customer_id' => $customers['6281234567804']->id],
            [
                'status' => 'BOT_ACTIVE',
                'is_bot_active' => true,
                'is_admin_takeover' => false,
                'interested_car_id' => $cars['BM-2022-HR04']->id,
                'interest_level' => 'HIGH',
                'last_message_at' => Carbon::now()->subHours(2),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-008'],
            [
                'conversation_id' => $c4->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'message_body' => 'Bisa kirim HR-V ke Jakarta gak min? Biaya berapa?',
                'delivery_status' => 'read',
                'created_at' => Carbon::now()->subHours(2)->subMinutes(1),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-009'],
            [
                'conversation_id' => $c4->id,
                'direction' => 'outbound',
                'sender_type' => 'bot',
                'message_type' => 'text',
                'message_body' => "Bisa sekali Kak Rina! Showroom Bintang Mobil melayani pengiriman antar kota via Single Towing atau Car Carrier terpercaya sampai depan rumah di Jakarta.\n\nEstimasi biaya: Rp 2.500.000 - Rp 3.800.000 (2-4 hari kerja) sudah termasuk asuransi.\nUntuk konfirmasi jadwal angkut dan diskon ongkir resmi, saya bisa hubungkan Kakak dengan Admin Bintang Mobil ya Kak? 😊",
                'delivery_status' => 'delivered',
                'created_at' => Carbon::now()->subHours(2),
            ]
        );

        // Scenario 5: Agus Priyanto -> NEGO KERAS & INGIN TEST DRIVE (NEEDS_ADMIN - Handover Triggered)
        $c5 = Conversation::updateOrCreate(
            ['customer_id' => $customers['6281234567805']->id],
            [
                'status' => 'NEEDS_ADMIN',
                'is_bot_active' => false,
                'is_admin_takeover' => false,
                'interested_car_id' => $cars['BM-2022-FT08']->id,
                'interest_level' => 'HOT',
                'last_message_at' => Carbon::now()->subMinutes(8),
                'handover_at' => Carbon::now()->subMinutes(8),
                'handover_reason' => 'Customer serius negosiasi harga dan mengajukan jadwal test drive Fortuner.',
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-010'],
            [
                'conversation_id' => $c5->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'message_body' => 'Saya serius, Fortuner GR Sport bisa nego 500 pas? Saya mau ketemu dan test drive besok siang.',
                'delivery_status' => 'read',
                'created_at' => Carbon::now()->subMinutes(9),
            ]
        );

        Message::updateOrCreate(
            ['wa_message_id' => 'DEMO-MSG-011'],
            [
                'conversation_id' => $c5->id,
                'direction' => 'outbound',
                'sender_type' => 'bot',
                'message_type' => 'text',
                'message_body' => 'Siap Kak Agus, terima kasih atas minat seriusnya pada Toyota Fortuner 2.8 GR Sport! 😊 Saya bantu hubungkan dengan Tim Admin Senior Bintang Mobil sekarang juga untuk mendiskusikan penawaran harga terbaik dan mengunci jadwal test drive Kakak besok. Mohon tunggu sebentar ya Kak.',
                'delivery_status' => 'sent',
                'created_at' => Carbon::now()->subMinutes(8),
            ]
        );

        // Log the handover
        HandoverLog::updateOrCreate(
            ['conversation_id' => $c5->id, 'trigger_type' => 'AUTO_INTENT'],
            [
                'action' => 'HANDOVER_REQUESTED',
                'reason' => 'Customer serius negosiasi harga dan mengajukan jadwal test drive Fortuner.',
                'assigned_to_user_id' => $admin->id,
                'metadata' => [
                    'car_id' => $cars['BM-2022-FT08']->id,
                    'intent' => 'NEGOTIATION_AND_TEST_DRIVE',
                    'offer' => 500000000,
                ],
            ]
        );

        // 7. Seed Leads
        Lead::updateOrCreate(
            ['customer_id' => $customers['6281234567805']->id],
            [
                'car_id' => $cars['BM-2022-FT08']->id,
                'budget' => 500000000,
                'payment_method' => 'CASH',
                'destination_city' => 'Gresik',
                'lead_status' => 'HOT',
                'interest_level' => 'HOT',
                'test_drive_at' => Carbon::tomorrow()->setHour(14)->setMinute(0),
                'assigned_user_id' => $admin->id,
                'notes' => 'Customer minta diskon ke 500 juta cash keras. Jadwal test drive besok jam 14:00 di showroom pusat.',
            ]
        );

        Lead::updateOrCreate(
            ['customer_id' => $customers['6281234567801']->id],
            [
                'car_id' => $cars['BM-2022-AV01']->id,
                'budget' => 215000000,
                'payment_method' => 'CASH',
                'destination_city' => 'Surabaya',
                'lead_status' => 'QUALIFIED',
                'interest_level' => 'HIGH',
                'assigned_user_id' => $admin->id,
                'notes' => 'Tanya Avanza 2022 TSS, menunggu respon foto interior.',
            ]
        );

        Lead::updateOrCreate(
            ['customer_id' => $customers['6281234567803']->id],
            [
                'car_id' => $cars['BM-2021-IN02']->id,
                'budget' => 350000000,
                'payment_method' => 'CREDIT',
                'destination_city' => 'Malang',
                'lead_status' => 'NEGOTIATION',
                'interest_level' => 'HIGH',
                'assigned_user_id' => $admin->id,
                'notes' => 'Sudah melihat video interior & mesin Innova diesel. Diskusi DP 80 juta.',
            ]
        );
    }
}
