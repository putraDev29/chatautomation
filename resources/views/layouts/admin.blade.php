<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Bintang Mobil AI Auto Sales</title>

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Outfit', sans-serif; }
        /* Custom scrollbar for chat inbox */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased flex flex-col min-h-screen">

    <!-- Main App Navigation -->
    <header class="bg-slate-900 text-white sticky top-0 z-40 border-b border-slate-800 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand Logo -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/30 group-hover:scale-105 transition-transform">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-lg font-bold font-display tracking-tight text-white block">BINTANG MOBIL</span>
                        </div>
                    </a>

                    <!-- Nav Links Desktop -->
                    <nav class="hidden md:flex items-center space-x-1 ml-8">
                        <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('admin.conversations.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition flex items-center space-x-1.5 {{ request()->routeIs('admin.conversations.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <span>Inbox WhatsApp</span>
                            @php
                                $needsAdminCount = \App\Models\Conversation::where('status', 'NEEDS_ADMIN')->count();
                            @endphp
                            @if($needsAdminCount > 0)
                                <span class="bg-rose-500 text-white text-[11px] font-bold px-1.5 py-0.5 rounded-full animate-bounce">
                                    {{ $needsAdminCount }}
                                </span>
                            @endif
                        </a>
                        <a href="{{ route('admin.cars.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.cars.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            Katalog Mobil
                        </a>
                        <a href="{{ route('admin.leads.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.leads.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            Leads & Penjualan
                        </a>
                        <a href="{{ route('admin.settings.index') }}" class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.settings.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            Pengaturan
                        </a>
                        <button type="button" onclick="openQrModal()" id="navWaBridgeBtn" class="px-3 py-1.5 rounded-lg text-xs font-bold transition bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 flex items-center space-x-1.5 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-amber-400" id="navWaBridgeDot"></span>
                            <span id="navWaBridgeText">📱 Scan QR WA (HP B)</span>
                        </button>
                    </nav>
                </div>

                <!-- Right Profile & Quick Actions -->
                <div class="flex items-center space-x-4">
                    <div class="hidden sm:flex flex-col text-right">
                        <span class="text-xs font-semibold text-white">{{ Auth::user()->name ?? 'Admin Showroom' }}</span>
                        <span class="text-[11px] text-slate-400">{{ Auth::user()->email ?? 'admin@bintangmobil.test' }}</span>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-rose-300 hover:text-white bg-slate-800 hover:bg-rose-600 px-3 py-1.5 rounded-lg border border-slate-700 transition">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Multi-Admin WhatsApp Barcode Scanner Modal Component -->
    <div id="waQrModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200 transform transition-all flex flex-col max-h-[92vh]">
            <!-- Modal Header -->
            <div class="bg-[#075e54] text-white p-4 px-6 flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <span class="text-xl">📱</span>
                    <div>
                        <h3 class="font-bold text-sm leading-tight">Barcode WhatsApp Multi-Admin</h3>
                        <p class="text-[11px] text-emerald-200">Scan QR Code Sesuai Nomor WhatsApp Bisnis Masing-Masing Admin</p>
                    </div>
                </div>
                <button type="button" onclick="closeQrModal()" class="text-white/80 hover:text-white text-2xl font-bold leading-none">&times;</button>
            </div>

            <!-- Admin Session Switcher Tabs -->
            <div class="bg-slate-100 p-2 px-4 border-b border-slate-200 overflow-x-auto flex items-center space-x-2" id="adminSessionTabs">
                <span class="text-xs text-slate-400">Memuat sesi admin...</span>
            </div>

            <!-- Modal Body -->
            <div class="p-6 text-center space-y-4 overflow-y-auto">
                <div id="activeAdminBadge" class="inline-flex items-center space-x-2 bg-slate-100 px-3 py-1 rounded-full text-xs font-semibold text-slate-700">
                    <span id="activeAdminDot" class="w-2 h-2 rounded-full bg-slate-400"></span>
                    <span id="activeAdminTitle">Memuat Admin...</span>
                </div>

                <!-- Status Container -->
                <div id="qrLoadingState" class="space-y-3 py-6">
                    <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs text-slate-600 font-medium">Menyiapkan barcode admin...</p>
                </div>

                <div id="qrReadyState" class="hidden space-y-4">
                    <div class="inline-block p-3 bg-white border-2 border-dashed border-emerald-500 rounded-2xl shadow-inner">
                        <img id="qrImageElement" src="" alt="Scan Barcode" class="w-64 h-64 object-contain mx-auto rounded-lg">
                    </div>
                    
                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-left text-xs text-slate-600 space-y-1">
                        <p class="font-bold text-slate-800 text-[11px] uppercase tracking-wider mb-1">Petunjuk Scan HP:</p>
                        <p>1. Buka aplikasi <strong>WhatsApp Bisnis</strong> di HP admin yang bersangkutan</p>
                        <p>2. Ketuk <strong>Titik Tiga &gt; Perangkat Tertaut (Linked Devices) &gt; Tautkan Perangkat</strong></p>
                        <p>3. Arahkan kamera ke Barcode di atas. Chat ke nomor ini otomatis masuk ke admin ini saja!</p>
                    </div>
                    <p class="text-[11px] text-slate-400 animate-pulse">Barcode aktif diperbarui realtime</p>
                </div>

                <div id="qrConnectedState" class="hidden space-y-3 py-6">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto shadow-md">
                        ✓
                    </div>
                    <h4 class="font-bold text-slate-900 text-base" id="qrConnectedAdminName">Admin Terhubung!</h4>
                    <p class="text-xs text-slate-600">
                        Nomor WA: <strong class="text-emerald-700 font-mono text-sm" id="qrConnectedPhone">-</strong>
                    </p>
                    <div class="bg-emerald-50 text-emerald-800 text-xs p-3 rounded-xl border border-emerald-200 text-left">
                        <strong>Chat Terisolasi:</strong> Percakapan dari customer yang masuk ke nomor ini khusus ditangani oleh admin ini dan tidak tampak oleh admin lain.
                    </div>
                </div>

                <div id="qrOfflineState" class="hidden space-y-3 py-4">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-xl mx-auto">
                        ⚠️
                    </div>
                    <h4 class="font-bold text-slate-900 text-sm">Sesi Belum Terhubung</h4>
                    <p class="text-xs text-slate-500 max-w-xs mx-auto">
                        Sistem sedang menunggu inisialisasi barcode untuk sesi admin ini...
                    </p>
                    <div class="flex items-center justify-center space-x-2 pt-1">
                        <button type="button" onclick="startBridgeNow()" id="btnStartBridge" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition shadow shadow-emerald-600/20 flex items-center space-x-1.5">
                            <span>🚀 Jalankan / Refresh Sesi</span>
                        </button>
                        <button type="button" onclick="checkBridgeStatus(false)" class="bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold px-3 py-2 rounded-xl transition">
                            Cek Status
                        </button>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="bg-slate-50 p-3 px-6 border-t border-slate-100 flex items-center justify-between">
                <span class="text-[11px] text-slate-500">Tiap admin memiliki barcode & akun mandiri</span>
                <button type="button" onclick="closeQrModal()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold px-4 py-2 rounded-xl transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Flash Notifications -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
        @if(session('success'))
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3 text-emerald-800 text-sm font-medium">
                    <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 text-lg">&times;</button>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-sm mb-4">
                <div class="text-rose-800 text-sm font-medium mb-1">Terjadi kesalahan input:</div>
                <ul class="list-disc list-inside text-xs text-rose-700 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <!-- Main Page Content -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

    <!-- Multi-Admin QR Script -->
    <script>
        let bridgePollTimer = null;
        let activeSessionId = 'admin_{{ Auth::id() ?: 1 }}';
        const isSuperAdminUser = {{ (Auth::user() && Auth::user()->isSuperAdmin()) ? 'true' : 'false' }};
        const currentUserId = {{ Auth::id() ?: 1 }};

        function openQrModal() {
            document.getElementById('waQrModal').classList.remove('hidden');
            checkBridgeStatus();
            if (!bridgePollTimer) {
                bridgePollTimer = setInterval(checkBridgeStatus, 2500);
            }
        }

        function closeQrModal() {
            document.getElementById('waQrModal').classList.add('hidden');
            if (bridgePollTimer) {
                clearInterval(bridgePollTimer);
                bridgePollTimer = null;
            }
        }

        function selectAdminSession(sessionId) {
            activeSessionId = sessionId;
            checkBridgeStatus();
        }

        async function checkBridgeStatus(autoStart = false) {
            try {
                const url = autoStart ? '/api/whatsapp/bridge-status?auto_start=1' : '/api/whatsapp/bridge-status';
                const res = await fetch(url);
                const data = await res.json();

                const loading = document.getElementById('qrLoadingState');
                const ready = document.getElementById('qrReadyState');
                const connected = document.getElementById('qrConnectedState');
                const offline = document.getElementById('qrOfflineState');
                const dot = document.getElementById('navWaBridgeDot');
                const btnText = document.getElementById('navWaBridgeText');
                const tabsContainer = document.getElementById('adminSessionTabs');

                const sessions = data.sessions || [];

                // Filter sessions to display:
                // Superadmin sees all admin tabs; Regular sales only sees their own tab!
                const visibleSessions = isSuperAdminUser ? sessions : sessions.filter(s => s.user_id == currentUserId);

                if (visibleSessions.length > 0 && !visibleSessions.some(s => s.session_id === activeSessionId)) {
                    activeSessionId = visibleSessions[0].session_id;
                }

                // Render tabs
                if (tabsContainer && visibleSessions.length > 0) {
                    tabsContainer.innerHTML = visibleSessions.map(s => {
                        const isSelected = s.session_id === activeSessionId;
                        const statusDotClass = s.status === 'connected' ? 'bg-emerald-500' : (s.status === 'qr_ready' ? 'bg-amber-400 animate-pulse' : 'bg-slate-400');
                        const activeClass = isSelected ? 'bg-white text-slate-900 shadow-sm border border-slate-300 font-bold' : 'bg-slate-200/70 text-slate-600 hover:bg-slate-200';
                        return `
                            <button type="button" onclick="selectAdminSession('${s.session_id}')"
                                class="px-3 py-1.5 rounded-xl text-xs flex items-center space-x-1.5 transition whitespace-nowrap ${activeClass}">
                                <span class="w-2 h-2 rounded-full ${statusDotClass}"></span>
                                <span>${s.name}</span>
                            </button>
                        `;
                    }).join('');
                }

                // Find currently active session object
                const activeSession = sessions.find(s => s.session_id === activeSessionId) || sessions[0] || {};
                const activeName = activeSession.name || 'Admin';

                document.getElementById('activeAdminTitle').innerText = activeName;
                const activeDot = document.getElementById('activeAdminDot');
                activeDot.className = activeSession.status === 'connected' ? 'w-2 h-2 rounded-full bg-emerald-500' : (activeSession.status === 'qr_ready' ? 'w-2 h-2 rounded-full bg-amber-400 animate-pulse' : 'w-2 h-2 rounded-full bg-slate-400');

                loading.classList.add('hidden');
                ready.classList.add('hidden');
                connected.classList.add('hidden');
                offline.classList.add('hidden');

                if (activeSession.status === 'connected') {
                    connected.classList.remove('hidden');
                    document.getElementById('qrConnectedAdminName').innerText = `${activeName} Terhubung!`;
                    document.getElementById('qrConnectedPhone').innerText = activeSession.phone || 'Nomor Terhubung';
                } else if (activeSession.status === 'qr_ready' && activeSession.qr_code) {
                    ready.classList.remove('hidden');
                    document.getElementById('qrImageElement').src = activeSession.qr_code;
                } else {
                    offline.classList.remove('hidden');
                }

                // Top Navigation status badge (reflects logged in user session or primary)
                const mySession = sessions.find(s => s.user_id == currentUserId) || activeSession;
                if (mySession.status === 'connected') {
                    dot.className = 'w-2 h-2 rounded-full bg-emerald-400';
                    btnText.innerText = `● ${mySession.name} (${mySession.phone || 'Terhubung'})`;
                } else if (mySession.status === 'qr_ready') {
                    dot.className = 'w-2 h-2 rounded-full bg-amber-400 animate-ping';
                    btnText.innerText = `📱 Scan Barcode (${mySession.name})`;
                } else {
                    dot.className = 'w-2 h-2 rounded-full bg-slate-500';
                    btnText.innerText = data.auto_starting ? '⏳ Menjalankan Bridge...' : '📱 Barcode WhatsApp Multi-Admin';
                }

            } catch (err) {
                console.warn('Bridge status check failed:', err);
            }
        }

        async function startBridgeNow() {
            const btn = document.getElementById('btnStartBridge');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>⏳ Memulai Bridge...</span>';
            }
            try {
                await fetch('/api/whatsapp/bridge-start', { method: 'POST' });
                setTimeout(() => checkBridgeStatus(false), 2000);
            } catch (e) {
                console.error(e);
            } finally {
                if (btn) {
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<span>🚀 Jalankan / Refresh Sesi</span>';
                    }, 3000);
                }
            }
        }

        // Initial background check on page load: automatically starts bridge if offline!
        document.addEventListener('DOMContentLoaded', () => {
            checkBridgeStatus(true);
        });
    </script>

    @yield('scripts')
</body>
</html>
