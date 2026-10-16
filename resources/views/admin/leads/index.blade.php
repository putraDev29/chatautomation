@extends('layouts.admin')

@section('title', 'Manajemen Lead & Penjualan')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold font-display text-slate-900">Manajemen Lead & Pipeline Penjualan</h1>
            <p class="text-sm text-slate-500 mt-0.5">Pantau calon pembeli potensial, jadwal test drive, dan status negosiasi unit.</p>
        </div>

        <div class="flex items-center space-x-2">
            <span class="text-xs text-slate-500">Filter Status:</span>
            <div class="flex flex-wrap gap-1">
                @foreach(['' => 'Semua', 'HOT' => '🔥 HOT', 'NEGOTIATION' => 'Nego', 'QUALIFIED' => 'Qualified', 'BOOKED' => 'Booked', 'WON' => 'Deal'] as $val => $lbl)
                    <a href="{{ route('admin.leads.index', ['status' => $val]) }}"
                        class="px-2.5 py-1 rounded-lg text-xs font-semibold transition {{ $status === $val ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                        {{ $lbl }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Leads Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Customer</th>
                        <th class="py-3.5 px-4">Unit Diminati</th>
                        <th class="py-3.5 px-4">Budget / Cara Bayar</th>
                        <th class="py-3.5 px-4">Kota</th>
                        <th class="py-3.5 px-4">Status & Minat</th>
                        <th class="py-3.5 px-4">Jadwal Test Drive</th>
                        <th class="py-3.5 px-4">Catatan Follow Up</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-4 px-4 font-semibold text-slate-900">
                                {{ $lead->customer->name }}
                                <span class="block text-[11px] text-slate-400 font-mono font-normal">{{ $lead->customer->whatsapp_number }}</span>
                            </td>
                            <td class="py-4 px-4 text-slate-700">
                                @if($lead->car)
                                    <strong class="text-slate-900">{{ $lead->car->full_name }}</strong>
                                    <span class="block text-[11px] text-blue-600 font-semibold">{{ $lead->car->formatted_price }}</span>
                                @else
                                    <span class="text-slate-400">Belum Ditentukan</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-slate-600">
                                {{ $lead->budget ? 'Rp ' . number_format($lead->budget, 0, ',', '.') : '-' }}
                                <span class="block text-[10px] text-slate-500 uppercase font-semibold">{{ $lead->payment_method }}</span>
                            </td>
                            <td class="py-4 px-4 text-slate-700 font-medium">
                                {{ $lead->destination_city ?: '-' }}
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                    @if($lead->lead_status === 'HOT') bg-rose-100 text-rose-700
                                    @elseif($lead->lead_status === 'NEGOTIATION') bg-amber-100 text-amber-700
                                    @elseif($lead->lead_status === 'QUALIFIED') bg-blue-100 text-blue-700
                                    @elseif($lead->lead_status === 'BOOKED') bg-emerald-100 text-emerald-700
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ $lead->lead_status }}
                                </span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">Minat: {{ $lead->interest_level }}</span>
                            </td>
                            <td class="py-4 px-4 text-slate-700 font-medium">
                                {{ $lead->test_drive_at ? $lead->test_drive_at->format('d M Y H:i') : '-' }}
                            </td>
                            <td class="py-4 px-4 text-slate-600 max-w-xs">
                                <p class="line-clamp-2">{{ $lead->notes ?: 'Tidak ada catatan.' }}</p>
                            </td>
                            <td class="py-4 px-4 text-right space-x-2">
                                <a href="{{ route('admin.conversations.index', ['selected' => $lead->customer->latestConversation?->id]) }}" class="text-blue-600 hover:text-blue-800 font-semibold">
                                    Buka Chat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">Belum ada data lead penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-t border-slate-100">
            {{ $leads->links() }}
        </div>
    </div>
</div>
@endsection
