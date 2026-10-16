<?php

namespace App\Services\Cars;

use App\Models\Car;
use App\Models\CarMedia;

class CarMediaService
{
    /**
     * Get primary or requested media for a given car.
     *
     * @param Car $car
     * @param string|null $mediaType photo_exterior | photo_interior | video | null
     * @return array
     */
    public function getMediaForCar(Car $car, ?string $mediaType = null): array
    {
        $query = $car->media()->where('is_active', true);

        if ($mediaType) {
            $query->where('media_type', $mediaType);
        }

        $mediaList = $query->get();

        if ($mediaList->isEmpty() && $mediaType) {
            // Fallback to any active media if specific type not found
            $mediaList = $car->media()->where('is_active', true)->get();
        }

        if ($mediaList->isEmpty()) {
            return [
                'has_media' => false,
                'message' => "Mohon maaf Kak, file {$mediaType} untuk {$car->full_name} sedang dalam proses update oleh tim showroom kami. Boleh saya bantu hubungkan dengan admin untuk kirim foto langsung via WhatsApp?",
                'items' => [],
            ];
        }

        $items = $mediaList->map(function (CarMedia $item) use ($car) {
            $url = $item->file_url ?: url($item->file_path);
            return [
                'id' => $item->id,
                'media_type' => $item->media_type,
                'url' => $url,
                'caption' => $item->caption ?: "{$car->full_name} - {$car->formatted_price}",
                'is_video' => $item->media_type === 'video',
            ];
        })->toArray();

        return [
            'has_media' => true,
            'car_id' => $car->id,
            'car_name' => $car->full_name,
            'items' => $items,
        ];
    }

    /**
     * Detect what type of media customer is asking for from message text.
     */
    public function detectRequestedMediaType(string $text): ?string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'vidio') || str_contains($lower, 'video') || str_contains($lower, 'rekaman')) {
            return 'video';
        }

        if (str_contains($lower, 'interior') || str_contains($lower, 'dalam') || str_contains($lower, 'jok') || str_contains($lower, 'dashboard')) {
            return 'photo_interior';
        }

        if (str_contains($lower, 'mesin') || str_contains($lower, 'kap mesin') || str_contains($lower, 'ruang mesin')) {
            return 'photo_engine';
        }

        if (str_contains($lower, 'odometer') || str_contains($lower, 'spido') || str_contains($lower, 'kilometer')) {
            return 'photo_odometer';
        }

        if (str_contains($lower, 'foto') || str_contains($lower, 'gambar') || str_contains($lower, 'penampakan') || str_contains($lower, 'spill') || str_contains($lower, 'pic') || str_contains($lower, 'pict')) {
            return 'photo_exterior';
        }

        return null;
    }
}
