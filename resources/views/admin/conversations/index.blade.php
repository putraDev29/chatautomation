@extends('layouts.admin')

@section('title', 'Inbox Percakapan WhatsApp')

@section('content')
<div class="h-[calc(100vh-140px)] flex flex-col">
    <!-- Top Action & Filter Bar -->
    <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3 mb-4">
        <!-- Search & Filter Forms -->
        <form method="GET" action="{{ route('admin.conversations.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative min-w-[200px] flex-1 max-w-sm">
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama / no. WhatsApp..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <select name="status" onchange="this.form.submit()"
                class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Status Chat</option>
                <option value="BOT_ACTIVE" {{ $statusFilter === 'BOT_ACTIVE' ? 'selected' : '' }}>🤖 Bot Aktif (BOT_ACTIVE)</option>
                <option value="NEEDS_ADMIN" {{ $statusFilter === 'NEEDS_ADMIN' ? 'selected' : '' }}>🚨 Menunggu Admin (NEEDS_ADMIN)</option>
                <option value="ADMIN_ACTIVE" {{ $statusFilter === 'ADMIN_ACTIVE' ? 'selected' : '' }}>👤 Ditangani Admin (ADMIN_ACTIVE)</option>
                <option value="WAITING_CUSTOMER" {{ $statusFilter === 'WAITING_CUSTOMER' ? 'selected' : '' }}>⏳ Menunggu Customer</option>
                <option value="CLOSED" {{ $statusFilter === 'CLOSED' ? 'selected' : '' }}>📁 Ditutup (CLOSED)</option>
            </select>

            @if(Auth::user()->isSuperAdmin() && !empty($adminList))
                <select name="admin_id" onchange="this.form.submit()"
                    class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Admin / Sales</option>
                    @foreach($adminList as $adm)
                        <option value="{{ $adm->id }}" {{ ($adminFilter == $adm->id) ? 'selected' : '' }}>
                            👤 {{ $adm->name }} ({{ $adm->role }})
                        </option>
                    @endforeach
                </select>
            @else
                <div class="inline-flex items-center space-x-1.5 bg-blue-50 text-blue-700 px-2.5 py-1 rounded-xl text-xs font-semibold border border-blue-200/60">
                    <span>🔒 Chat Milik: {{ Auth::user()->name }}</span>
                </div>
            @endif

            <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-3 py-1.5 rounded-xl transition">
                Filter
            </button>
            @if($search || $statusFilter || $adminFilter)
                <a href="{{ route('admin.conversations.index') }}" class="text-xs text-rose-600 hover:underline">Reset</a>
            @endif
        </form>

        <!-- Quick Status Indicator -->
        <div class="flex items-center space-x-2 text-xs">
            <span class="text-slate-500">Gateway:</span>
            <span class="font-bold px-2 py-0.5 rounded uppercase {{ $integration->whatsapp_mode === 'simulation' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                {{ $integration->whatsapp_mode }}
            </span>
        </div>
    </div>

    <!-- 3-Column Desktop Chat Layout -->
    <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-4 overflow-hidden">
        
        <!-- Column 1: Conversations List (3.5 cols) -->
        <div class="md:col-span-4 lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden">
            <div class="p-3 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Daftar Percakapan</span>
                <span class="text-[11px] text-slate-400 font-semibold">{{ $conversations->total() }} kontak</span>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                @forelse($conversations as $conv)
                    @php
                        $isSelected = $activeConversation && $activeConversation->id === $conv->id;
                    @endphp
                    <a href="{{ route('admin.conversations.index', array_merge(request()->query(), ['selected' => $conv->id])) }}"
                        class="p-3.5 block transition {{ $isSelected ? 'bg-blue-50/70 border-l-4 border-blue-600' : 'hover:bg-slate-50' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-9 h-9 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-xs
                                    {{ $conv->status === 'NEEDS_ADMIN' ? 'bg-rose-100 text-rose-700' : ($conv->status === 'ADMIN_ACTIVE' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700') }}">
                                    {{ substr($conv->customer->name, 0, 1) }}
                                </div>
                                <div class="truncate">
                                    <h4 class="text-xs font-bold text-slate-900 truncate">{{ $conv->customer->name }}</h4>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $conv->customer->whatsapp_number }}</span>
                                </div>
                            </div>
                            <span class="text-[10px] text-slate-400 whitespace-nowrap">
                                {{ $conv->last_message_at ? $conv->last_message_at->format('H:i') : '' }}
                            </span>
                        </div>

                        <!-- Status & Badges -->
                        <div class="mt-2 flex items-center justify-between gap-1 text-[10px]">
                            <div class="flex items-center space-x-1">
                                <span class="font-bold px-1.5 py-0.5 rounded uppercase
                                    @if($conv->status === 'NEEDS_ADMIN') bg-rose-100 text-rose-700 font-extrabold animate-pulse
                                    @elseif($conv->status === 'ADMIN_ACTIVE') bg-blue-100 text-blue-700
                                    @elseif($conv->status === 'BOT_ACTIVE') bg-emerald-100 text-emerald-700
                                    @else bg-slate-100 text-slate-600 @endif">
                                    {{ $conv->status }}
                                </span>
                                @if($conv->assignedAdmin)
                                    <span class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded font-semibold text-[9px] truncate max-w-[90px]" title="Ditangani: {{ $conv->assignedAdmin->name }}">
                                        👤 {{ $conv->assignedAdmin->name }}
                                    </span>
                                @endif
                            </div>

                            @if($conv->interestedCar)
                                <span class="text-slate-500 truncate max-w-[100px]" title="{{ $conv->interestedCar->full_name }}">
                                    🚗 {{ $conv->interestedCar->model }}
                                </span>
                            @endif
                        </div>

                        <!-- Preview Last Message -->
                        <p class="text-xs text-slate-500 truncate mt-1.5">
                            {{ $conv->latestMessage?->message_body ?: 'Belum ada pesan' }}
                        </p>
                    </a>
                @empty
                    <div class="p-8 text-center text-slate-400 text-xs">
                        Tidak ada percakapan yang cocok dengan filter.
                    </div>
                @endforelse
            </div>
            
            @if($conversations->hasPages())
                <div class="p-2 border-t border-slate-100 bg-slate-50 text-xs">
                    {{ $conversations->links() }}
                </div>
            @endif
        </div>

        @if($activeConversation)
            <!-- Column 2: Chat Stream & Message Input (5.5 cols) -->
            <div class="md:col-span-8 lg:col-span-6 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col overflow-hidden">
                
                <!-- Chat Header -->
                <div class="p-3.5 border-b border-slate-200 bg-white flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full font-bold flex items-center justify-center text-sm
                            {{ $activeConversation->status === 'NEEDS_ADMIN' ? 'bg-rose-100 text-rose-700' : ($activeConversation->status === 'ADMIN_ACTIVE' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700') }}">
                            {{ substr($activeConversation->customer->name, 0, 1) }}
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-bold text-slate-900">{{ $activeConversation->customer->name }}</h3>
                                <span class="text-xs text-slate-500 font-mono">{{ $activeConversation->customer->whatsapp_number }}</span>
                            </div>
                            <div class="flex items-center space-x-2 text-[11px] mt-0.5">
                                <span class="font-semibold
                                    {{ $activeConversation->is_bot_active ? 'text-emerald-600' : 'text-slate-500' }}">
                                    {{ $activeConversation->is_bot_active ? '● Bot AI Aktif Membalas' : '○ Bot Dijeda / Admin Aktif' }}
                                </span>
                                @if($activeConversation->assignedAdmin)
                                    <span class="text-slate-400">&bull; Ditangani: {{ $activeConversation->assignedAdmin->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Takeover, Resume & Delete Action Controls -->
                    <div class="flex items-center space-x-2">
                        @if($activeConversation->status === 'NEEDS_ADMIN' || $activeConversation->is_bot_active)
                            <form method="POST" action="{{ route('admin.conversations.takeover', $activeConversation->id) }}">
                                @csrf
                                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span>Ambil Alih (Stop Bot)</span>
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.conversations.resume', $activeConversation->id) }}">
                                @csrf
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    </svg>
                                    <span>Aktifkan Kembali Bot</span>
                                </button>
                            </form>
                        @endif

                        <!-- Tombol Hapus Chat Percakapan -->
                        <form method="POST" action="{{ route('admin.conversations.destroy', $activeConversation->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus chat percakapan ini? Semua pesan dalam obrolan ini akan dihapus secara permanen.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-slate-200 hover:border-rose-200 transition flex items-center space-x-1" title="Hapus Chat Ini">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <span>Hapus</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Handover Notice Alert If Needs Admin -->
                @if($activeConversation->status === 'NEEDS_ADMIN')
                    <div class="bg-rose-50 border-b border-rose-200 p-2.5 px-4 text-xs flex items-center justify-between text-rose-800">
                        <div class="flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-rose-600 animate-ping"></span>
                            <span class="font-bold">PERHATIAN:</span>
                            <span>{{ $activeConversation->handover_reason ?: 'Customer memerlukan tanggapan sales showroom.' }}</span>
                        </div>
                        <span class="font-mono text-[10px] text-rose-500">{{ $activeConversation->handover_at?->diffForHumans() }}</span>
                    </div>
                @endif

                <!-- Chat History Messages Scroll Area -->
                <div class="flex-1 p-4 overflow-y-auto space-y-3 bg-slate-50/70" id="chatStream">
                    @foreach($activeConversation->messages as $msg)
                        @if($msg->direction === 'inbound')
                            <!-- Customer Inbound Bubble -->
                            <div class="flex flex-col items-start max-w-[85%]">
                                <div class="bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm text-xs space-y-1">
                                    <span class="text-[10px] font-bold text-blue-600 uppercase block">{{ $activeConversation->customer->name }}</span>
                                    <p class="leading-relaxed whitespace-pre-line">{{ $msg->message_body }}</p>
                                </div>
                                <span class="text-[10px] text-slate-400 mt-1 pl-1">
                                    {{ $msg->created_at->format('H:i') }} &bull; Inbound WA
                                </span>
                            </div>
                        @else
                            <!-- Outbound (Bot or Admin) Bubble -->
                            <div class="flex flex-col items-end max-w-[85%] ml-auto">
                                <div class="rounded-2xl rounded-tr-sm px-4 py-2.5 shadow-sm text-xs space-y-2
                                    {{ $msg->sender_type === 'admin' ? 'bg-gradient-to-r from-blue-700 to-indigo-700 text-white' : 'bg-slate-900 text-slate-100' }}">
                                    <div class="flex items-center justify-between gap-3 text-[10px] opacity-80">
                                        <span class="font-bold uppercase tracking-wider">
                                            {{ $msg->sender_type === 'admin' ? '👤 Admin Showroom' : '🤖 AI Sales Bot' }}
                                        </span>
                                        <span class="font-mono">
                                            {{ $msg->delivery_status === 'sent' ? '✓ Terkirim' : ($msg->delivery_status === 'delivered' ? '✓✓ Sampai' : ($msg->delivery_status === 'read' ? '✓✓ Dibaca' : $msg->delivery_status)) }}
                                        </span>
                                    </div>

                                    @if($msg->media_url)
                                        <div class="bg-slate-800/80 rounded-xl overflow-hidden p-1.5 border border-slate-700/50">
                                            @if($msg->message_type === 'video')
                                                <div class="aspect-video bg-black/40 rounded flex items-center justify-center text-center p-4">
                                                    <span class="text-xs text-blue-300 font-semibold">🎬 [Video Lampiran WhatsApp Dikirimkan]</span>
                                                </div>
                                            @else
                                                <img src="{{ $msg->media_url }}" alt="Media" class="w-full h-40 object-cover rounded">
                                            @endif
                                        </div>
                                    @endif

                                    <p class="leading-relaxed whitespace-pre-line">{{ $msg->message_body }}</p>
                                </div>
                                <span class="text-[10px] text-slate-400 mt-1 pr-1">
                                    {{ $msg->created_at->format('H:i') }}
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>

                <!-- Admin Message Reply Form -->
                <div class="p-3.5 border-t border-slate-200 bg-white">
                    <form method="POST" action="{{ route('admin.conversations.reply', $activeConversation->id) }}" class="space-y-2">
                        @csrf
                        <div class="relative">
                            <textarea name="message" rows="2" required
                                placeholder="Ketik balasan admin resmi ke customer (WhatsApp)..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">
                                Mode gateway saat ini: <strong>{{ $integration->whatsapp_mode }}</strong>
                            </span>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs px-4 py-2 rounded-xl shadow-sm transition flex items-center space-x-1.5">
                                <span>Kirim Pesan Admin</span>
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Column 3: Customer Profile & Interested Car (3 cols) -->
            <div class="hidden lg:flex lg:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm flex-col overflow-y-auto p-4 space-y-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Profil & Catatan Customer</h3>
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 text-xs space-y-1.5">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Nama:</span>
                            <span class="font-bold text-slate-900">{{ $activeConversation->customer->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">WhatsApp:</span>
                            <span class="font-mono text-slate-800">{{ $activeConversation->customer->whatsapp_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kota:</span>
                            <span class="font-semibold text-slate-800">{{ $activeConversation->customer->city ?: 'Belum diset' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Tingkat Minat:</span>
                            <span class="font-bold text-rose-600">{{ $activeConversation->interest_level }}</span>
                        </div>
                    </div>
                </div>

                <!-- Interested Car Card -->
                <div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Mobil yang Diminati</h3>
                    @if($activeConversation->interestedCar)
                        @php $car = $activeConversation->interestedCar; @endphp
                        <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 text-xs space-y-2">
                            <img src="{{ $car->main_photo ?: '/demo-cars/car-default.jpg' }}" alt="Car" class="w-full h-28 object-cover rounded-lg border border-slate-200">
                            <div>
                                <h4 class="font-bold text-slate-900 text-sm leading-tight">{{ $car->full_name }}</h4>
                                <span class="text-blue-600 font-bold text-sm block mt-0.5">{{ $car->formatted_price }}</span>
                                <span class="text-[11px] text-slate-500 font-mono">Internal: Rp {{ number_format($car->internal_special_price, 0, ',', '.') }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-1.5 text-[11px] pt-1 border-t border-slate-200 text-slate-600">
                                <div>Stok: <strong class="text-emerald-600">{{ $car->stock_status }}</strong></div>
                                <div>Transmisi: <strong>{{ $car->transmission }}</strong></div>
                                <div>Odo: <strong>{{ $car->formatted_mileage }}</strong></div>
                                <div>Bahan Bakar: <strong>{{ $car->fuel_type }}</strong></div>
                            </div>
                        </div>
                    @else
                        <div class="bg-slate-50 rounded-xl p-3 border border-dashed border-slate-200 text-xs text-slate-400 text-center">
                            Belum ada unit mobil spesifik yang dikaitkan.
                        </div>
                    @endif
                </div>

                <!-- Update Details Form -->
                <form method="POST" action="{{ route('admin.conversations.details', $activeConversation->id) }}" class="space-y-3 pt-2 border-t border-slate-100">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Kaitkan Unit Mobil</label>
                        <select name="interested_car_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-800">
                            <option value="">-- Pilih Mobil --</option>
                            @foreach($availableCars as $c)
                                <option value="{{ $c->id }}" {{ $activeConversation->interested_car_id === $c->id ? 'selected' : '' }}>
                                    {{ $c->full_name }} ({{ $c->formatted_price }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Status Lead Penjualan</label>
                        <select name="lead_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-800">
                            @foreach(['NEW', 'QUALIFIED', 'HOT', 'NEGOTIATION', 'BOOKED', 'WON', 'LOST'] as $st)
                                <option value="{{ $st }}" {{ ($activeLead?->lead_status ?? '') === $st ? 'selected' : '' }}>
                                    {{ $st }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Catatan Internal Sales</label>
                        <textarea name="internal_notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-800">{{ $activeConversation->internal_notes }}</textarea>
                    </div>

                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold py-2 rounded-xl transition">
                        Simpan Catatan
                    </button>
                </form>

                <!-- Handover Audit History -->
                <div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-2">Riwayat Audit Handover</h3>
                    <div class="space-y-2 text-[11px]">
                        @forelse($activeConversation->handoverLogs as $log)
                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                <div class="flex justify-between font-bold text-slate-800">
                                    <span>{{ $log->action }}</span>
                                    <span class="text-slate-400 font-normal font-mono">{{ $log->created_at->format('d/m H:i') }}</span>
                                </div>
                                <p class="text-slate-600 mt-0.5">{{ $log->reason }}</p>
                            </div>
                        @empty
                            <p class="text-slate-400 text-xs">Belum ada riwayat handover.</p>
                        @endforelse
                    </div>
                </div>

            </div>
        @else
            <!-- Empty state when no conversation is selected -->
            <div class="md:col-span-8 lg:col-span-9 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center justify-center p-8 text-center text-slate-400">
                <div>
                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <p class="font-semibold text-slate-700 text-sm">Pilih percakapan dari daftar di sebelah kiri.</p>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection

@section('scripts')
<script>
    // Auto-scroll chat to bottom
    const chatStream = document.getElementById('chatStream');
    if (chatStream) {
        chatStream.scrollTop = chatStream.scrollHeight;
    }
</script>
@endsection
