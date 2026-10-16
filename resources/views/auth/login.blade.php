<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Bintang Mobil WhatsApp AI Auto Sales</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden p-8">
        
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold font-display tracking-tight text-white">BINTANG MOBIL</h1>
            <p class="text-xs font-semibold tracking-wider uppercase text-blue-400 mt-1">AI WhatsApp Auto Sales Portal</p>
            <p class="text-xs text-slate-400 mt-2">Masuk ke dashboard untuk memantau chat WhatsApp dan mengelola unit mobil.</p>
        </div>

        @if($errors->any())
            <div class="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3 text-rose-400 text-xs mb-6">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', 'admin@bintangmobil.test') }}" required autofocus
                    class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Password</label>
                <input type="password" id="password" name="password" value="admin123456" required
                    class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <label class="flex items-center text-slate-400 hover:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded bg-slate-800 border-slate-700 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2">Ingat saya</span>
                </label>
                <span class="text-slate-500">Demo Akses Lokal</span>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-blue-600/30 transition text-sm flex items-center justify-center space-x-2">
                <span>Masuk ke Dashboard</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </form>

        <!-- Quick Demo Credentials Box -->
        <div class="mt-6 pt-6 border-t border-slate-800/80 text-center">
            <p class="text-xs text-slate-400 mb-2 font-medium">Akun Demo Lokal:</p>
            <div class="bg-slate-800/50 rounded-lg p-2.5 text-xs text-slate-300 font-mono flex items-center justify-between">
                <span>admin@bintangmobil.test / admin123456</span>
                <span class="text-emerald-400 font-sans font-bold text-[10px] uppercase bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-800">Ready</span>
            </div>
        </div>
    </div>
</body>
</html>
