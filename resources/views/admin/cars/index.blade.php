@extends('layouts.admin')

@section('title', 'Katalog Stok Mobil Showroom')

@section('content')
<div class="space-y-6">

    <!-- Header & Filter Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold font-display text-slate-900">Katalog Unit Showroom Bintang Mobil</h1>
            <p class="text-sm text-slate-500 mt-0.5">Database kendaraan sumber kebenaran (source of truth) untuk AI Sales Bot dan customer service WhatsApp.</p>
        </div>

        <form method="GET" action="{{ route('admin.cars.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari tipe/merek/kode..."
                class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[180px]">

            <select name="status" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700">
                <option value="">Semua Status Stok</option>
                <option value="TERSEDIA" {{ $status === 'TERSEDIA' ? 'selected' : '' }}>🟢 TERSEDIA</option>
                <option value="BOOKING" {{ $status === 'BOOKING' ? 'selected' : '' }}>🟡 BOOKING</option>
                <option value="TERJUAL" {{ $status === 'TERJUAL' ? 'selected' : '' }}>🔴 TERJUAL</option>
                <option value="TIDAK AKTIF" {{ $status === 'TIDAK AKTIF' ? 'selected' : '' }}>⚪ TIDAK AKTIF</option>
            </select>

            <select name="brand" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700">
                <option value="">Semua Merek</option>
                @foreach($brands as $b)
                    <option value="{{ $b }}" {{ $brand === $b ? 'selected' : '' }}>{{ $b }}</option>
                @endforeach
            </select>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs px-4 py-2 rounded-xl transition">
                Cari Unit
            </button>
            @if($search || $status || $brand)
                <a href="{{ route('admin.cars.index') }}" class="text-xs text-rose-600 hover:underline">Reset</a>
            @endif
        </form>
    </div>

    <!-- Cars Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($cars as $car)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:border-blue-300 transition">
                <div class="relative h-44 bg-slate-900">
                    <img src="{{ $car->main_photo ?: '/demo-cars/car-default.jpg' }}" alt="{{ $car->full_name }}" class="w-full h-full object-cover">
                    <div class="absolute top-2.5 left-2.5 flex items-center space-x-1.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                            @if($car->stock_status === 'TERSEDIA') bg-emerald-600 text-white
                            @elseif($car->stock_status === 'BOOKING') bg-amber-500 text-amber-950 font-extrabold
                            @elseif($car->stock_status === 'TERJUAL') bg-rose-600 text-white
                            @else bg-slate-700 text-slate-200 @endif">
                            {{ $car->stock_status }}
                        </span>
                        @if($car->is_demo)
                            <span class="bg-blue-950 text-blue-300 border border-blue-700 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase">
                                DEMO
                            </span>
                        @endif
                    </div>
                    <div class="absolute bottom-2 right-2 bg-black/70 backdrop-blur px-2 py-0.5 rounded text-[11px] font-mono text-white">
                        {{ $car->stock_code }}
                    </div>
                </div>

                <div class="p-4 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs font-semibold text-blue-600 uppercase">{{ $car->brand }}</span>
                            <span class="text-xs font-bold text-slate-500 font-mono">{{ $car->year }}</span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mt-0.5 line-clamp-1">{{ $car->full_name }}</h3>
                        
                        <div class="mt-2 flex items-baseline justify-between">
                            <span class="text-base font-bold font-display text-blue-600">{{ $car->formatted_price }}</span>
                            <span class="text-[11px] text-slate-400 font-mono" title="Harga Khusus Internal">
                                Int: Rp {{ number_format($car->internal_special_price, 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-slate-600 border-t border-slate-100 pt-3">
                            <div>Transmisi: <strong class="text-slate-800">{{ $car->transmission }}</strong></div>
                            <div>Odometer: <strong class="text-slate-800">{{ $car->formatted_mileage }}</strong></div>
                            <div>Warna: <strong class="text-slate-800">{{ $car->color }}</strong></div>
                            <div>Pajak: <strong class="text-slate-800">{{ $car->stnk_status }}</strong></div>
                        </div>

                        <p class="text-xs text-slate-500 mt-3 line-clamp-2">{{ $car->description }}</p>
                    </div>

                    <!-- Update Status Form -->
                    <form method="POST" action="{{ route('admin.cars.updateStatus', $car->id) }}" class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                        @csrf
                        @method('PUT')
                        <select name="stock_status" class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs text-slate-700 font-semibold flex-1">
                            <option value="TERSEDIA" {{ $car->stock_status === 'TERSEDIA' ? 'selected' : '' }}>TERSEDIA</option>
                            <option value="BOOKING" {{ $car->stock_status === 'BOOKING' ? 'selected' : '' }}>BOOKING</option>
                            <option value="TERJUAL" {{ $car->stock_status === 'TERJUAL' ? 'selected' : '' }}>TERJUAL</option>
                            <option value="TIDAK AKTIF" {{ $car->stock_status === 'TIDAK AKTIF' ? 'selected' : '' }}>TIDAK AKTIF</option>
                        </select>
                        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 py-1 rounded-lg transition">
                            Ubah
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white p-12 text-center text-slate-400 rounded-2xl border border-slate-200">
                Tidak ada mobil yang sesuai dengan pencarian Anda.
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $cars->links() }}
    </div>
</div>
@endsection
