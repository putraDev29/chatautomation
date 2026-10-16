<?php

namespace App\Services\Shipping;

use App\Models\ShippingRate;

class ShippingInformationService
{
    /**
     * Get delivery information from Surabaya to customer's destination city.
     */
    public function getDeliveryEstimate(string $destinationCity): array
    {
        $city = trim($destinationCity);

        $rate = ShippingRate::where('is_active', true)
            ->where('destination_city', 'LIKE', "%{$city}%")
            ->first();

        if ($rate) {
            $minCost = 'Rp ' . number_format($rate->estimated_cost_min, 0, ',', '.');
            $maxCost = 'Rp ' . number_format($rate->estimated_cost_max, 0, ',', '.');

            return [
                'found' => true,
                'origin' => $rate->origin_city,
                'destination' => $rate->destination_city,
                'method' => $rate->transport_method,
                'cost_range' => "{$minCost} - {$maxCost}",
                'days_range' => "{$rate->estimated_days_min} - {$rate->estimated_days_max} hari kerja",
                'notes' => $rate->operational_notes,
                'text_summary' => "Pengiriman unit dari {$rate->origin_city} ke {$rate->destination_city} tersedia via {$rate->transport_method}. Estimasi biaya berkisar {$minCost} - {$maxCost} dengan estimasi waktu tiba {$rate->estimated_days_min}-{$rate->estimated_days_max} hari kerja (sudah termasuk asuransi perjalanan).",
            ];
        }

        return [
            'found' => false,
            'origin' => 'Surabaya',
            'destination' => $city,
            'text_summary' => "Showroom Bintang Mobil melayani pengiriman unit ke luar kota ke seluruh wilayah Indonesia (termasuk {$city}) via Single Towing atau Car Carrier. Biaya pasti dan jadwal keberangkatan akan dikonfirmasikan langsung oleh tim ekspedisi dan admin kami.",
        ];
    }

    /**
     * Extract destination city from message.
     */
    public function extractCity(string $text): ?string
    {
        $cities = [
            'jakarta', 'semarang', 'yogyakarta', 'jogja', 'bandung', 'bali', 'denpasar',
            'malang', 'solo', 'surakarta', 'kediri', 'madiun', 'jember', 'banyuwangi',
            'balikpapan', 'banjarmasin', 'makassar', 'medan', 'palembang', 'lampung'
        ];

        $lower = strtolower($text);
        foreach ($cities as $city) {
            if (str_contains($lower, $city)) {
                if ($city === 'jogja') return 'Yogyakarta';
                if ($city === 'bali') return 'Denpasar (Bali)';
                return ucfirst($city);
            }
        }

        return null;
    }
}
