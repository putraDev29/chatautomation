<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppSettingsController extends Controller
{
    public function index()
    {
        $integration = WhatsAppIntegration::current();

        // Check Queue worker status (pending jobs count)
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Masked secrets for security
        $metaToken = config('whatsapp.access_token');
        $maskedMetaToken = !empty($metaToken) ? substr($metaToken, 0, 6) . '••••••••' . substr($metaToken, -4) : 'Belum Dikonfigurasi';

        $aiKey = config('ai.api_key');
        $maskedAiKey = !empty($aiKey) ? substr($aiKey, 0, 4) . '••••••••' . substr($aiKey, -4) : 'Belum Dikonfigurasi (Menggunakan Simulasi AI)';

        $verifyToken = config('whatsapp.verify_token', 'bintang_mobil_verify_token_2026');

        return view('admin.settings.index', compact(
            'integration',
            'pendingJobs',
            'failedJobs',
            'maskedMetaToken',
            'maskedAiKey',
            'verifyToken'
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_mode' => 'required|in:simulation,production',
            'ai_driver' => 'required|in:simulation,openai,gemini',
            'is_ai_enabled' => 'nullable|boolean',
            'ai_model' => 'required|string',
            'business_hours_start' => 'required|date_format:H:i',
            'business_hours_end' => 'required|date_format:H:i',
            'handover_auto_trigger' => 'nullable|boolean',
            'handover_keywords' => 'nullable|string',
            'default_greeting' => 'required|string',
            'daily_ai_limit' => 'required|integer|min:10',
        ]);

        $integration = WhatsAppIntegration::current();

        // Safety verification if switching to production
        if ($validated['whatsapp_mode'] === 'production') {
            if (empty(config('whatsapp.phone_number_id')) || empty(config('whatsapp.access_token'))) {
                return back()->withErrors([
                    'whatsapp_mode' => 'Mode Produksi tidak dapat diaktifkan sebelum WHATSAPP_PHONE_NUMBER_ID dan WHATSAPP_ACCESS_TOKEN dikonfigurasi di file .env!',
                ]);
            }
        }

        $integration->update([
            'whatsapp_mode' => $validated['whatsapp_mode'],
            'whatsapp_provider' => $validated['whatsapp_mode'] === 'production' ? 'meta' : 'simulated',
            'ai_driver' => $validated['ai_driver'],
            'is_ai_enabled' => $request->boolean('is_ai_enabled', true),
            'ai_model' => $validated['ai_model'],
            'business_hours_start' => $validated['business_hours_start'],
            'business_hours_end' => $validated['business_hours_end'],
            'handover_auto_trigger' => $request->boolean('handover_auto_trigger', true),
            'handover_keywords' => $validated['handover_keywords'],
            'default_greeting' => $validated['default_greeting'],
            'daily_ai_limit' => $validated['daily_ai_limit'],
        ]);

        return back()->with('success', 'Pengaturan integrasi Bintang Mobil WhatsApp AI berhasil disimpan.');
    }
}
