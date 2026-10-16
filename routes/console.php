<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('wa:bridge {action=start : start, status, or check}', function () {
    $action = $this->argument('action');

    if ($action === 'status') {
        $status = \Illuminate\Support\Facades\Cache::get('wa_bridge_status');
        if (!$status) {
            $this->warn('Status WhatsApp Bridge: Offline (Belum ada aktivitas)');
            return;
        }
        $this->info('Status WhatsApp Bridge : ' . ($status['status'] ?? 'unknown'));
        $this->line('Nomor HP B Terhubung  : ' . ($status['phone'] ?? '-'));
        $this->line('Terakhir Diperbarui    : ' . ($status['updated_at'] ?? '-'));
        return;
    }

    $this->info('Memeriksa status WhatsApp Bridge (HP B)...');
    $result = \App\Services\WhatsApp\WhatsAppBridgeManager::startBridgeInBackground();
    if ($result['success']) {
        $this->info('✓ ' . $result['message']);
    } else {
        $this->error('✗ ' . $result['message']);
    }
})->purpose('Jalankan atau periksa status WhatsApp QR Bridge');

