<?php

namespace App\Services\Cars;

use App\Models\Car;
use Illuminate\Database\Eloquent\Collection;

class CarSearchService
{
    /**
     * Search cars based on free-form query or structured filters.
     */
    public function search(array $filters = []): Collection
    {
        $query = Car::query()->where('is_published', true);

        // Filter stock status (default: TERSEDIA)
        if (!empty($filters['stock_status'])) {
            $query->where('stock_status', strtoupper($filters['stock_status']));
        } else {
            $query->where('stock_status', 'TERSEDIA');
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $brand = $this->normalizeBrand($filters['brand']);
            $query->where('brand', 'LIKE', "%{$brand}%");
        }

        // Model filter
        if (!empty($filters['model'])) {
            $model = $this->normalizeModel($filters['model']);
            $query->where(function ($q) use ($model) {
                $q->where('model', 'LIKE', "%{$model}%")
                  ->orWhere('variant', 'LIKE', "%{$model}%");
            });
        }

        // Year filter
        if (!empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        } elseif (!empty($filters['min_year'])) {
            $query->where('year', '>=', (int) $filters['min_year']);
        }

        // Price range filter
        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }
        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        // Transmission filter
        if (!empty($filters['transmission'])) {
            $transmission = $this->normalizeTransmission($filters['transmission']);
            if ($transmission) {
                $query->where('transmission', 'LIKE', "%{$transmission}%");
            }
        }

        // Fuel filter
        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', 'LIKE', "%{$filters['fuel_type']}%");
        }

        // Body type / category filter
        if (!empty($filters['body_type'])) {
            $type = strtoupper($filters['body_type']);
            if ($type === 'MPV') {
                $query->where(function ($q) {
                    $q->where('model', 'LIKE', '%Avanza%')
                      ->orWhere('model', 'LIKE', '%Innova%')
                      ->orWhere('model', 'LIKE', '%Mobilio%')
                      ->orWhere('model', 'LIKE', '%Xpander%')
                      ->orWhere('model', 'LIKE', '%Ertiga%')
                      ->orWhere('model', 'LIKE', '%Xenia%')
                      ->orWhere('model', 'LIKE', '%Calya%')
                      ->orWhere('model', 'LIKE', '%Sigra%');
                });
            } elseif ($type === 'SUV') {
                $query->where(function ($q) {
                    $q->where('model', 'LIKE', '%Fortuner%')
                      ->orWhere('model', 'LIKE', '%Pajero%')
                      ->orWhere('model', 'LIKE', '%HR-V%')
                      ->orWhere('model', 'LIKE', '%Terios%')
                      ->orWhere('model', 'LIKE', '%Raize%')
                      ->orWhere('model', 'LIKE', '%XL7%');
                });
            } elseif ($type === 'HATCHBACK') {
                $query->where(function ($q) {
                    $q->where('model', 'LIKE', '%Brio%')
                      ->orWhere('model', 'LIKE', '%City Hatchback%')
                      ->orWhere('model', 'LIKE', '%Yaris%')
                      ->orWhere('model', 'LIKE', '%Agya%')
                      ->orWhere('model', 'LIKE', '%Ayla%')
                      ->orWhere('model', 'LIKE', '%Baleno%');
                });
            }
        }

        // Keyword search across brand, model, variant, description
        if (!empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(function ($q) use ($keyword) {
                $q->where('brand', 'LIKE', "%{$keyword}%")
                  ->orWhere('model', 'LIKE', "%{$keyword}%")
                  ->orWhere('variant', 'LIKE', "%{$keyword}%")
                  ->orWhere('description', 'LIKE', "%{$keyword}%");
            });
        }

        // Order by latest year, then price ascending
        $query->orderBy('year', 'desc')->orderBy('price', 'asc');

        if (!empty($filters['all']) || (isset($filters['limit']) && $filters['limit'] === 'all')) {
            return $query->get();
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 10;
        return $query->limit($limit)->get();
    }

    /**
     * Get all currently available and published cars in stock.
     */
    public function getAllAvailableCars(): Collection
    {
        return Car::where('stock_status', 'TERSEDIA')
            ->where('is_published', true)
            ->orderBy('year', 'desc')
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Parse unstructured natural language text from Indonesian customer into structured search filters.
     */
    public function parseQueryFromText(string $text): array
    {
        $textLower = strtolower($text);
        $filters = [];

        // 1. Detect Brand
        $brands = [
            'toyota', 'honda', 'mitsubishi', 'daihatsu', 'suzuki',
            'hyundai', 'wuling', 'nissan', 'mazda', 'kia',
            'isuzu', 'ford', 'chevrolet', 'bmw', 'mercedes'
        ];
        foreach ($brands as $b) {
            if (str_contains($textLower, $b)) {
                $filters['brand'] = ucfirst($b);
                break;
            }
        }

        // 2. Detect Comprehensive Model variations (longest patterns first)
        $modelMap = [
            // Toyota
            'innova reborn' => 'Innova',
            'inova reborn' => 'Innova',
            'innova zenix' => 'Innova',
            'zenix' => 'Innova',
            'reborn' => 'Innova',
            'innova' => 'Innova',
            'inova' => 'Innova',
            'avanza veloz' => 'Veloz',
            'veloz' => 'Veloz',
            'avanza' => 'Avanza',
            'abanza' => 'Avanza',
            'fortuner' => 'Fortuner',
            'rush' => 'Rush',
            'raize' => 'Raize',
            'calya' => 'Calya',
            'agya' => 'Agya',
            'yaris cross' => 'Yaris Cross',
            'yaris' => 'Yaris',
            'corolla cross' => 'Corolla Cross',
            'corolla' => 'Corolla',
            'alphard' => 'Alphard',
            'voxy' => 'Voxy',
            'sienta' => 'Sienta',
            'camry' => 'Camry',
            'hilux' => 'Hilux',

            // Honda
            'mobilio' => 'Mobilio',
            'brio satya' => 'Brio Satya',
            'brio' => 'Brio',
            'hrv' => 'HR-V',
            'hr-v' => 'HR-V',
            'brv' => 'BR-V',
            'br-v' => 'BR-V',
            'crv' => 'CR-V',
            'cr-v' => 'CR-V',
            'wrv' => 'WR-V',
            'wr-v' => 'WR-V',
            'jazz' => 'Jazz',
            'city hatchback' => 'City Hatchback',
            'city' => 'City',
            'civic' => 'Civic',
            'accord' => 'Accord',
            'freed' => 'Freed',
            'odyssey' => 'Odyssey',

            // Mitsubishi
            'xpander cross' => 'Xpander Cross',
            'expander cross' => 'Xpander Cross',
            'xpander' => 'Xpander',
            'expander' => 'Xpander',
            'pajero sport' => 'Pajero Sport',
            'pajero' => 'Pajero Sport',
            'xforce' => 'Xforce',
            'outlander' => 'Outlander',
            'mirage' => 'Mirage',
            'triton' => 'Triton',

            // Daihatsu
            'xenia' => 'Xenia',
            'terios' => 'Terios',
            'sigra' => 'Sigra',
            'ayla' => 'Ayla',
            'rocky' => 'Rocky',
            'gran max' => 'Gran Max',
            'granmax' => 'Gran Max',
            'luxio' => 'Luxio',
            'sirion' => 'Sirion',

            // Suzuki
            'ertiga hybrid' => 'Ertiga Hybrid',
            'ertiga' => 'Ertiga',
            'xl7' => 'XL7',
            'xl-7' => 'XL7',
            'baleno' => 'Baleno',
            'ignis' => 'Ignis',
            'karimun wagon' => 'Karimun',
            'karimun' => 'Karimun',
            's-cross' => 'S-Cross',
            'grand vitara' => 'Grand Vitara',
            'jimny' => 'Jimny',
            'apv' => 'APV',

            // Hyundai
            'stargazer' => 'Stargazer',
            'creta' => 'Creta',
            'palisade' => 'Palisade',
            'santa fe' => 'Santa Fe',
            'ioniq' => 'Ioniq',

            // Wuling
            'confero' => 'Confero',
            'cortez' => 'Cortez',
            'almaz' => 'Almaz',
            'air ev' => 'Air EV',
            'binguo' => 'Binguo',

            // Nissan
            'grand livina' => 'Grand Livina',
            'livina' => 'Livina',
            'serena' => 'Serena',
            'x-trail' => 'X-Trail',
            'xtrail' => 'X-Trail',
            'march' => 'March',
            'juke' => 'Juke',

            // Mazda
            'cx-5' => 'CX-5',
            'cx-3' => 'CX-3',
            'mazda 2' => 'Mazda 2',
            'mazda 3' => 'Mazda 3',
        ];

        foreach ($modelMap as $key => $val) {
            if (str_contains($textLower, $key)) {
                $filters['model'] = $val;
                break;
            }
        }

        // 3. Detect Transmission (supports abbreviations: AT, A/T, Matic, CVT, MT, M/T, Manual)
        if (preg_match('/\b(matic|matoc|metiq|at|a\/t|cvt)\b/i', $textLower)) {
            $filters['transmission'] = 'Otomatis';
        } elseif (preg_match('/\b(manual|mt|m\/t|mnl)\b/i', $textLower)) {
            $filters['transmission'] = 'Manual';
        }

        // 4. Detect Year (e.g. 2015 - 2026, or "th 17", "thn 2018", "th 22")
        if (preg_match('/\b(201[0-9]|202[0-6])\b/', $textLower, $matches)) {
            $filters['year'] = (int) $matches[1];
        } elseif (preg_match('/\b(?:th|thn|tahun)\s*(\d{2})\b/i', $textLower, $matches)) {
            $filters['year'] = (int) ('20' . $matches[1]);
        }

        // 5. Detect Budget & DP (e.g., "150 juta", "150jt", "dp 20jt", "tdp 15 juta", "budget 150")
        if (preg_match('/(\d+)\s*(juta|jt)/', $textLower, $matches)) {
            $amount = (float) $matches[1] * 1000000;
            if (str_contains($textLower, 'bawah') || str_contains($textLower, 'budget') || str_contains($textLower, 'maks') || str_contains($textLower, 'dana')) {
                $filters['max_price'] = $amount;
            } elseif (preg_match('/\b(dp|tdp|uang muka)\b/i', $textLower)) {
                $filters['dp_amount'] = $amount;
            } else {
                $filters['max_price'] = $amount + 20000000; // tolerance
                $filters['min_price'] = max(0, $amount - 30000000);
            }
        }

        // 6. Detect Fuel type
        if (str_contains($textLower, 'diesel') || str_contains($textLower, 'solar')) {
            $filters['fuel_type'] = 'Diesel';
        } elseif (str_contains($textLower, 'bensin')) {
            $filters['fuel_type'] = 'Bensin';
        }

        // 7. Detect Body Type / Category
        if (str_contains($textLower, 'keluarga') || str_contains($textLower, 'mpv') || str_contains($textLower, '7 seater') || str_contains($textLower, '7-seater') || str_contains($textLower, 'tujuh kursi')) {
            $filters['body_type'] = 'MPV';
        } elseif (str_contains($textLower, 'suv') || str_contains($textLower, 'tinggi') || str_contains($textLower, 'gagah')) {
            $filters['body_type'] = 'SUV';
        } elseif (str_contains($textLower, 'city car') || str_contains($textLower, 'citycar') || str_contains($textLower, 'hatchback') || str_contains($textLower, 'mobil kecil') || str_contains($textLower, 'lincah')) {
            $filters['body_type'] = 'Hatchback';
        } elseif (str_contains($textLower, 'sedan')) {
            $filters['body_type'] = 'Sedan';
        }

        return $filters;
    }

    /**
     * Find car by specific ID or stock code.
     */
    public function find(int|string $identifier): ?Car
    {
        return Car::with('media')
            ->where('id', $identifier)
            ->orWhere('stock_code', $identifier)
            ->first();
    }

    /**
     * Check if a specific car or model is available in stock.
     */
    public function checkAvailability(string $modelOrBrand, ?int $year = null): array
    {
        $query = Car::query()
            ->where(function ($q) use ($modelOrBrand) {
                $q->where('model', 'LIKE', "%{$modelOrBrand}%")
                  ->orWhere('brand', 'LIKE', "%{$modelOrBrand}%");
            });

        if ($year) {
            $query->where('year', $year);
        }

        $allUnits = $query->get();
        $availableUnits = $allUnits->where('stock_status', 'TERSEDIA');
        $bookedUnits = $allUnits->where('stock_status', 'BOOKING');
        $soldUnits = $allUnits->where('stock_status', 'TERJUAL');

        return [
            'has_available' => $availableUnits->isNotEmpty(),
            'available_count' => $availableUnits->count(),
            'available_units' => $availableUnits,
            'booked_count' => $bookedUnits->count(),
            'sold_count' => $soldUnits->count(),
        ];
    }

    private function normalizeBrand(string $brand): string
    {
        return trim($brand);
    }

    private function normalizeModel(string $model): string
    {
        $map = [
            'inova' => 'Innova',
            'hrv' => 'HR-V',
            'crv' => 'CR-V',
        ];
        $lower = strtolower(trim($model));
        return $map[$lower] ?? trim($model);
    }

    private function normalizeTransmission(string $transmission): ?string
    {
        $lower = strtolower(trim($transmission));
        if (in_array($lower, ['matic', 'otomatis', 'at', 'cvt'])) {
            return 'Otomatis';
        }
        if (in_array($lower, ['manual', 'mt'])) {
            return 'Manual';
        }
        return null;
    }
}
