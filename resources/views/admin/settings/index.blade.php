@extends('layouts.admin')

@section('title', 'Pengaturan Integrasi WhatsApp & AI')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <h1 class="text-2xl font-bold font-display text-slate-900">Pengaturan Sistem & Integrasi</h1>
        <p class="text-sm text-slate-500 mt-0.5">Konfigurasi operasional gateway WhatsApp Cloud API Meta, provider AI Sales, dan handover admin.</p>
    </div>

    <!-- Live QR Scanner Card for HP B (WhatsApp Bisnis) -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-lg border border-slate-700">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="space-y-2 max-w-xl">
                <div class="inline-flex items-center space-x-2 bg-emerald-950/80 text-emerald-300 border border-emerald-700/60 px-3 py-1 rounded-full text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Konektor WhatsApp HP B (WhatsApp Bisnis)</span>
                </div>
                <h2 class="text-xl font-bold font-display text-white">Sambungkan HP B via Scan QR Code Langsung di Web</h2>
                <p class="text-xs text-slate-300 leading-relaxed">
                    Scan QR code di samping menggunakan aplikasi <strong>WhatsApp Bisnis di HP B</strong> Anda (Menu &gt; Perangkat Tertaut &gt; Tautkan Perangkat). Setelah terhubung, chat dari HP A (Customer) akan otomatis dibalas oleh bot Laravel showroom!
                </p>
                <div class="pt-2 flex items-center space-x-3">
                    <button type="button" onclick="openQrModal()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-lg shadow-emerald-600/30 transition flex items-center space-x-2">
                        <span>📲 Buka Pop-up Scanner QR</span>
                    </button>
                    <span class="text-xs text-slate-400 font-mono">Status: <span id="settingsStatusText" class="text-amber-400 font-bold">Mengecek...</span></span>
                </div>
            </div>

            <!-- Mini Live QR View Container -->
            <div class="bg-white p-3.5 rounded-2xl shadow-xl flex-shrink-0 text-center text-slate-900">
                <div id="settingsQrContainer" class="w-48 h-48 flex items-center justify-center border border-slate-200 rounded-xl bg-slate-50">
                    <span class="text-xs text-slate-400 font-medium">Memuat QR...</span>
                </div>
                <span class="text-[10px] text-slate-500 block mt-2 font-semibold uppercase tracking-wider">QR Code Live (Auto-Refresh)</span>
            </div>
        </div>
    </div>

    <!-- Status Cards (Queue & Gateway) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Gateway Mode Status -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-start space-x-4">
            <div class="w-10 h-10 rounded-xl {{ $integration->whatsapp_mode === 'simulation' ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600' }} flex items-center justify-center font-bold text-lg">
                {{ $integration->whatsapp_mode === 'simulation' ? '⚡' : '🚀' }}
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase">Mode Operasi</span>
                <h3 class="text-base font-bold text-slate-900 uppercase">{{ $integration->whatsapp_mode }}</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ $integration->whatsapp_mode === 'simulation' ? 'Simulasi lokal aman dari pengiriman pesan sungguhan.' : 'Terhubung ke Meta WhatsApp Cloud API.' }}
                </p>
            </div>
        </div>

        <!-- AI Engine Status -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-start space-x-4">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-lg">
                🤖
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase">AI Driver</span>
                <h3 class="text-base font-bold text-slate-900 uppercase">{{ $integration->ai_driver }}</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Model: <strong class="text-slate-700">{{ $integration->ai_model }}</strong> (Penggunaan hari ini: {{ $integration->current_daily_ai_usage }}/{{ $integration->daily_ai_limit }})
                </p>
            </div>
        </div>

        <!-- Queue Worker Status -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-start space-x-4">
            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-lg">
                ⚙️
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase">Background Queue</span>
                <h3 class="text-base font-bold text-slate-900">{{ $pendingJobs }} Antrean</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Driver: <strong class="text-slate-700">{{ config('queue.default') }}</strong> (Gagal: {{ $failedJobs }})
                </p>
            </div>
        </div>
    </div>

    <!-- Webhook Integration Information -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
            <span>Konfigurasi Webhook Meta WhatsApp Cloud API</span>
        </h2>
        <p class="text-xs text-slate-500">
            Daftarkan endpoint URL dan Callback Verification Token berikut pada App Dashboard Meta for Developers (WhatsApp > Configuration > Webhook):
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <span class="text-slate-500 font-sans font-semibold block mb-1">Callback URL (Webhook Endpoint):</span>
                <input type="text" readonly value="{{ url('/api/whatsapp/webhook') }}"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-slate-800 text-xs">
            </div>

            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <span class="text-slate-500 font-sans font-semibold block mb-1">Verify Token:</span>
                <input type="text" readonly value="{{ $verifyToken }}"
                    class="w-full bg-white border border-slate-200 rounded-lg p-2 text-slate-800 text-xs">
            </div>
        </div>

        <div class="text-[11px] text-slate-500 bg-blue-50/50 p-3 rounded-xl border border-blue-100">
            <strong>Catatan Keamanan:</strong> Kredensial rahasia (Access Token & App Secret) disimpan secara aman melalui file <code>.env</code> dan tidak pernah ditampilkan secara penuh pada dashboard demi privasi.
            <div class="mt-1 font-mono text-[10px] text-slate-600">
                Access Token Terpasang: {{ $maskedMetaToken }} | AI Key: {{ $maskedAiKey }}
            </div>
        </div>
    </div>

    <!-- Settings Update Form -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <h2 class="text-base font-bold text-slate-900 mb-4">Pengaturan Parameter Operasional</h2>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Mode WhatsApp -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Mode Operasional WhatsApp</label>
                    <select name="whatsapp_mode" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-blue-500">
                        <option value="simulation" {{ $integration->whatsapp_mode === 'simulation' ? 'selected' : '' }}>
                            ⚡ Mode Simulasi (Local Testing - Aman dari pengiriman WA asli)
                        </option>
                        <option value="production" {{ $integration->whatsapp_mode === 'production' ? 'selected' : '' }}>
                            🚀 Mode Produksi (Kirim WA asli via Meta Cloud API)
                        </option>
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Hanya pilih produksi apabila token resmi Meta sudah terkonfigurasi di .env.</p>
                </div>

                <!-- Provider AI -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Provider AI Sales</label>
                    <select name="ai_driver" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800 focus:ring-2 focus:ring-blue-500">
                        <option value="simulation" {{ $integration->ai_driver === 'simulation' ? 'selected' : '' }}>
                            🤖 Simulated AI (Cerdas Bahasa Indonesia Lokal - Bebas Token)
                        </option>
                        <option value="openai" {{ $integration->ai_driver === 'openai' ? 'selected' : '' }}>
                            🧠 OpenAI API (GPT-4o Mini / Function Calling)
                        </option>
                        <option value="gemini" {{ $integration->ai_driver === 'gemini' ? 'selected' : '' }}>
                            🌟 Google Gemini API
                        </option>
                    </select>
                </div>

                <!-- Model AI & Limit -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Model AI</label>
                    <input type="text" name="ai_model" value="{{ old('ai_model', $integration->ai_model) }}" required
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Batas Penggunaan AI Harian</label>
                    <input type="number" name="daily_ai_limit" value="{{ old('daily_ai_limit', $integration->daily_ai_limit) }}" required min="10"
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800">
                </div>

                <!-- Jam Operasional -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Jam Buka Showroom</label>
                    <input type="time" name="business_hours_start" value="{{ substr($integration->business_hours_start, 0, 5) }}" required
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Jam Tutup Showroom</label>
                    <input type="time" name="business_hours_end" value="{{ substr($integration->business_hours_end, 0, 5) }}" required
                        class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800">
                </div>
            </div>

            <!-- Handover Keywords -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Kata Kunci Pemicu Handover Otomatis ke Admin</label>
                <input type="text" name="handover_keywords" value="{{ old('handover_keywords', $integration->handover_keywords) }}"
                    class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800">
                <p class="text-[11px] text-slate-500 mt-1">Pisahkan dengan koma (contoh: nego, diskon, test drive, booking, ketemu, kredit).</p>
            </div>

            <!-- Default Greeting -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Pesan Salam Pembuka Default</label>
                <textarea name="default_greeting" rows="2" required
                    class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-800">{{ old('default_greeting', $integration->default_greeting) }}</textarea>
            </div>

            <div class="flex items-center space-x-6 pt-2">
                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_ai_enabled" value="1" {{ $integration->is_ai_enabled ? 'checked' : '' }}
                        class="rounded bg-slate-100 border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>Aktifkan Bot AI Penjawab Otomatis</span>
                </label>

                <label class="flex items-center space-x-2 text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="handover_auto_trigger" value="1" {{ $integration->handover_auto_trigger ? 'checked' : '' }}
                        class="rounded bg-slate-100 border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>Otomatis Alihkan ke Admin Saat Minat Beli Tinggi / Nego</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs px-6 py-2.5 rounded-xl shadow-sm transition">
                    Simpan Perubahan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    async function updateSettingsQrCard(autoStart = false) {
        try {
            const url = autoStart ? '/api/whatsapp/bridge-status?auto_start=1' : '/api/whatsapp/bridge-status';
            const res = await fetch(url);
            const data = await res.json();

            const container = document.getElementById('settingsQrContainer');
            const statusText = document.getElementById('settingsStatusText');

            if (data.status === 'connected') {
                container.innerHTML = `
                    <div class="text-center p-3">
                        <div class="text-3xl text-emerald-500 mb-1">✓</div>
                        <span class="text-xs font-bold text-slate-800 block">Terhubung</span>
                        <span class="text-[11px] font-mono text-emerald-600 block mt-0.5">${data.phone || 'HP B'}</span>
                    </div>
                `;
                statusText.innerText = 'TERHUBUNG';
                statusText.className = 'text-emerald-400 font-bold';
            } else if (data.status === 'qr_ready' && data.qr_code) {
                container.innerHTML = `<img src="${data.qr_code}" alt="QR Code" class="w-full h-full object-contain rounded-lg">`;
                statusText.innerText = 'SIAP SCAN';
                statusText.className = 'text-amber-400 font-bold animate-pulse';
            } else {
                container.innerHTML = `
                    <div class="text-center p-2 text-slate-400 text-xs">
                        <span>Bridge Offline</span>
                        <button onclick="startBridgeNow()" class="mt-2 text-[10px] bg-blue-600 hover:bg-blue-700 text-white font-semibold px-2.5 py-1 rounded-lg block mx-auto transition">▶ Jalankan Bridge</button>
                    </div>
                `;
                statusText.innerText = data.auto_starting ? 'MEMULAI BRIDGE...' : 'BRIDGE OFFLINE';
                statusText.className = 'text-slate-400 font-bold';
            }
        } catch (e) {
            console.warn(e);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateSettingsQrCard(true);
        setInterval(() => updateSettingsQrCard(false), 2500);
    });
</script>
@endsection
