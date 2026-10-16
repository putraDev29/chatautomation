<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppBridgeManager
{
    /**
     * Check if WhatsApp Bridge is currently reporting active status.
     */
    public static function isBridgeActive(): bool
    {
        $status = Cache::get('wa_bridge_status');
        if (!$status) {
            return false;
        }

        $state = $status['status'] ?? '';
        if (in_array($state, ['connected', 'qr_ready'])) {
            if (!empty($status['updated_at'])) {
                try {
                    $lastUpdated = Carbon::parse($status['updated_at']);
                    if ($lastUpdated->diffInSeconds(now()) < 30) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    return true;
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Launch start-bridge.bat in the background on Windows.
     */
    public static function startBridgeInBackground(): array
    {
        if (self::isBridgeActive()) {
            return [
                'success' => true,
                'message' => 'WhatsApp Bridge sudah aktif berjalan.',
                'already_running' => true,
            ];
        }

        $bridgeDir = base_path('whatsapp-bridge');
        $serverJs = $bridgeDir . DIRECTORY_SEPARATOR . 'server.js';

        if (!file_exists($serverJs)) {
            return [
                'success' => false,
                'message' => 'File server.js tidak ditemukan di folder whatsapp-bridge.',
                'already_running' => false,
            ];
        }

        try {
            // Non-blocking background launch on Windows
            $cmd = 'start "Bintang Mobil WhatsApp Bridge" /min cmd /c "cd /d ' . escapeshellarg($bridgeDir) . ' && node server.js"';
            pclose(popen($cmd, 'r'));

            Log::info('[BRIDGE MANAGER] start-bridge.bat / node server.js berhasil dijalankan di background.');

            return [
                'success' => true,
                'message' => 'WhatsApp Bridge berhasil dijalankan otomatis di background.',
                'already_running' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('[BRIDGE MANAGER] Gagal menjalankan WhatsApp Bridge: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menjalankan bridge: ' . $e->getMessage(),
                'already_running' => false,
            ];
        }
    }
}
