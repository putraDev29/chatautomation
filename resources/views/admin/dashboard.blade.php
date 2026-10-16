@extends('layouts.admin')

@section('title', 'Ringkasan Eksekutif & Statistik Showroom')

@section('content')
<div class="space-y-6">

    <!-- Header & Welcome -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold font-display text-slate-900">Dashboard Showroom Bintang Mobil</h1>
            <p class="text-sm text-slate-500 mt-0.5">Pemantauan otomatisasi WhatsApp, respons AI, dan handover tim sales secara real-time.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="{{ route('admin.conversations.index', ['status' => 'NEEDS_ADMIN']) }}"
                class="inline-flex items-center space-x-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-sm transition">
                <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                <span>Tinjau Menunggu Admin ({{ $waitingAdminCount }})</span>
            </a>
            <a href="{{ route('admin.conversations.index') }}"
                class="inline-flex items-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-sm transition">
                <span>Buka WhatsApp Inbox</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
        </div>
    </div>

    <!-- 6 Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- 1. Total Percakapan -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-blue-400 transition">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider block">Percakapan Hari Ini</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-display text-slate-900">{{ $totalConversationsToday }}</span>
                <span class="text-xs text-blue-600 font-semibold bg-blue-50 px-2 py-0.5 rounded-full">Aktif</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Total chat interaksi</p>
        </div>

        <!-- 2. Customer Baru -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-emerald-400 transition">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider block">Customer Baru</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-display text-emerald-600">{{ $newCustomersToday }}</span>
                <span class="text-xs text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full">+WA</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Kontak tersimpan</p>
        </div>

        <!-- 3. Menunggu Admin -->
        <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm bg-rose-50/20 hover:border-rose-400 transition">
            <span class="text-xs font-medium text-rose-600 uppercase tracking-wider block">Menunggu Admin</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-display text-rose-600">{{ $waitingAdminCount }}</span>
                <span class="text-xs text-rose-600 font-semibold bg-rose-100 px-2 py-0.5 rounded-full">Urgent</span>
            </div>
            <p class="text-[11px] text-rose-500 mt-1">Nego / Test Drive</p>
        </div>

        <!-- 4. Hot Leads -->
        <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm bg-amber-50/20 hover:border-amber-400 transition">
            <span class="text-xs font-medium text-amber-700 uppercase tracking-wider block">Hot Leads</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-display text-amber-600">{{ $hotLeadsCount }}</span>
                <span class="text-xs text-amber-700 font-semibold bg-amber-100 px-2 py-0.5 rounded-full">🔥 Siap Deal</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Peminat serius</p>
        </div>

        <!-- 5. Ditangani Bot AI -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-400 transition">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider block">Ditangani Bot AI</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-display text-indigo-600">{{ $botHandledCount }}</span>
                <span class="text-xs text-indigo-600 font-semibold bg-indigo-50 px-2 py-0.5 rounded-full">Auto</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Cek stok & info harga</p>
        </div>

        <!-- 6. Gagal Kirim -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-slate-400 transition">
            <span class="text-xs font-medium text-slate-500 uppercase tracking-wider block">Gagal Kirim</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold font-display {{ $failedMessagesCount > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $failedMessagesCount }}</span>
                <span class="text-xs {{ $failedMessagesCount > 0 ? 'text-rose-600 bg-rose-50' : 'text-emerald-600 bg-emerald-50' }} font-semibold px-2 py-0.5 rounded-full">
                    {{ $failedMessagesCount > 0 ? 'Error' : 'Aman' }}
                </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Status pengiriman WA</p>
        </div>
    </div>

    <!-- Charts & Pipeline Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Status Breakdown Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center justify-between">
                <span>Distribusi Status Percakapan</span>
                <span class="text-xs font-normal text-slate-400">Total: {{ array_sum($statusCounts) }}</span>
            </h2>

            <div class="space-y-3.5">
                @php
                    $totalChats = max(1, array_sum($statusCounts));
                @endphp
                
                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-700 mb-1">
                        <span class="flex items-center space-x-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <span>Bot Aktif (BOT_ACTIVE)</span>
                        </span>
                        <span>{{ $statusCounts['BOT_ACTIVE'] }} ({{ round(($statusCounts['BOT_ACTIVE'] / $totalChats) * 100) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ ($statusCounts['BOT_ACTIVE'] / $totalChats) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-700 mb-1">
                        <span class="flex items-center space-x-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                            <span>Menunggu Admin (NEEDS_ADMIN)</span>
                        </span>
                        <span class="font-bold text-rose-600">{{ $statusCounts['NEEDS_ADMIN'] }} ({{ round(($statusCounts['NEEDS_ADMIN'] / $totalChats) * 100) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-rose-500 h-2 rounded-full" style="width: {{ ($statusCounts['NEEDS_ADMIN'] / $totalChats) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-700 mb-1">
                        <span class="flex items-center space-x-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                            <span>Admin Sedang Menangani (ADMIN_ACTIVE)</span>
                        </span>
                        <span>{{ $statusCounts['ADMIN_ACTIVE'] }} ({{ round(($statusCounts['ADMIN_ACTIVE'] / $totalChats) * 100) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ ($statusCounts['ADMIN_ACTIVE'] / $totalChats) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-700 mb-1">
                        <span class="flex items-center space-x-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <span>Menunggu Respon Customer</span>
                        </span>
                        <span>{{ $statusCounts['WAITING_CUSTOMER'] }} ({{ round(($statusCounts['WAITING_CUSTOMER'] / $totalChats) * 100) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-amber-500 h-2 rounded-full" style="width: {{ ($statusCounts['WAITING_CUSTOMER'] / $totalChats) * 100 }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-xs font-medium text-slate-700 mb-1">
                        <span class="flex items-center space-x-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                            <span>Selesai / Deal / Ditutup (CLOSED)</span>
                        </span>
                        <span>{{ $statusCounts['CLOSED'] }} ({{ round(($statusCounts['CLOSED'] / $totalChats) * 100) }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-slate-400 h-2 rounded-full" style="width: {{ ($statusCounts['CLOSED'] / $totalChats) * 100 }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Showroom Stock Quick View -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block mb-2">Status Stok Showroom</span>
                <div class="grid grid-cols-2 gap-2 text-center text-xs">
                    <div class="bg-emerald-50 text-emerald-800 p-2 rounded-lg font-semibold border border-emerald-100">
                        <div class="text-lg font-bold">{{ $availableCars }} Unit</div>
                        <div class="text-[10px] text-emerald-600 uppercase">Tersedia Ready</div>
                    </div>
                    <div class="bg-blue-50 text-blue-800 p-2 rounded-lg font-semibold border border-blue-100">
                        <div class="text-lg font-bold">{{ $totalCars }} Unit</div>
                        <div class="text-[10px] text-blue-600 uppercase">Total Terdata</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Priority Handover Queue -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Antrean Handover Membutuhkan Tindakan Admin</h2>
                    <p class="text-xs text-slate-500">Percakapan otomatis dialihkan karena negosiasi, booking, atau permintaan customer.</p>
                </div>
                <span class="bg-rose-100 text-rose-700 text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ $waitingAdminCount }} antrean
                </span>
            </div>

            @if($priorityConversations->isEmpty())
                <div class="bg-slate-50 border border-dashed border-slate-200 rounded-xl p-8 text-center text-slate-500 text-sm">
                    <svg class="w-10 h-10 mx-auto text-emerald-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="font-semibold text-slate-800">Semua Percakapan Tertangani!</p>
                    <p class="text-xs text-slate-400 mt-1">Tidak ada percakapan yang saat ini menggantung membutuhkan campur tangan admin.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($priorityConversations as $pc)
                        <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 p-2 rounded-xl transition">
                            <div class="flex items-start space-x-3">
                                <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 font-bold flex items-center justify-center flex-shrink-0 text-sm">
                                    {{ substr($pc->customer->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <h3 class="text-sm font-bold text-slate-900">{{ $pc->customer->name }}</h3>
                                        <span class="text-xs text-slate-500 font-mono">{{ $pc->customer->whatsapp_number }}</span>
                                        <span class="text-[10px] font-bold bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded">NEEDS_ADMIN</span>
                                    </div>
                                    <p class="text-xs text-rose-600 font-medium mt-0.5">
                                        Alasan: {{ $pc->handover_reason ?: 'Permintaan penawaran dari customer.' }}
                                    </p>
                                    @if($pc->interestedCar)
                                        <p class="text-[11px] text-slate-500 mt-0.5">
                                            Mobil diminati: <strong class="text-slate-700">{{ $pc->interestedCar->full_name }}</strong> ({{ $pc->interestedCar->formatted_price }})
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 sm:self-center flex-shrink-0">
                                <form method="POST" action="{{ route('admin.conversations.takeover', $pc->id) }}">
                                    @csrf
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg shadow-sm transition">
                                        Ambil Alih Chat
                                    </button>
                                </form>
                                <a href="{{ route('admin.conversations.index', ['selected' => $pc->id]) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                    Lihat Detail
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Sales Leads -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Aktivitas Lead Penjualan Terbaru</h2>
                <p class="text-xs text-slate-500">Customer potensial yang terjaring dari obrolan WhatsApp otomatis.</p>
            </div>
            <a href="{{ route('admin.leads.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                Lihat Semua Lead &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Unit Diminati</th>
                        <th class="py-3 px-4">Budget / Pembayaran</th>
                        <th class="py-3 px-4">Kota</th>
                        <th class="py-3 px-4">Status Lead</th>
                        <th class="py-3 px-4">Minat</th>
                        <th class="py-3 px-4">Jadwal Test Drive</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentLeads as $lead)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4 font-semibold text-slate-900">
                                {{ $lead->customer->name }}
                                <span class="block text-[11px] text-slate-400 font-mono font-normal">{{ $lead->customer->whatsapp_number }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-700">
                                {{ $lead->car ? $lead->car->full_name : 'Belum Ditentukan' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $lead->budget ? 'Rp ' . number_format($lead->budget, 0, ',', '.') : '-' }}
                                <span class="block text-[10px] text-slate-400 uppercase">{{ $lead->payment_method }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $lead->destination_city ?: '-' }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                    @if($lead->lead_status === 'HOT') bg-rose-100 text-rose-700
                                    @elseif($lead->lead_status === 'NEGOTIATION') bg-amber-100 text-amber-700
                                    @elseif($lead->lead_status === 'QUALIFIED') bg-blue-100 text-blue-700
                                    @elseif($lead->lead_status === 'BOOKED') bg-emerald-100 text-emerald-700
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ $lead->lead_status }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-semibold text-slate-700">{{ $lead->interest_level }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $lead->test_drive_at ? $lead->test_drive_at->format('d M Y H:i') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('admin.conversations.index', ['selected' => $lead->customer->latestConversation?->id]) }}" class="text-blue-600 hover:text-blue-800 font-semibold">
                                    Buka Chat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-400">Belum ada aktivitas lead.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
