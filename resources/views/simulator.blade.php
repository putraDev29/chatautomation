<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Customer Simulator — Showroom Bintang Mobil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .wa-chat-bg {
            background-color: #efeae2;
            background-image: radial-gradient(#d1d7db 1px, transparent 1px);
            background-size: 20px 20px;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-900 min-h-screen flex flex-col items-center justify-center p-2 sm:p-4 text-slate-800">

    <!-- Top Navigation helper -->
    <div class="w-full max-w-md mb-3 flex items-center justify-between text-xs text-slate-300 px-2">
        <a href="{{ route('admin.dashboard') }}" class="text-blue-400 hover:text-blue-300 font-semibold flex items-center space-x-1">
            <span>&larr; Ke Dashboard Admin</span>
        </a>
        <div class="flex items-center space-x-2">
            <span class="text-slate-400">Ganti Customer:</span>
            <form method="GET" action="{{ route('simulator') }}">
                <select name="phone" onchange="this.form.submit()" class="bg-slate-800 text-white rounded px-2 py-1 text-xs border border-slate-700">
                    @foreach($allCustomers as $c)
                        <option value="{{ $c->whatsapp_number }}" {{ $customer->whatsapp_number === $c->whatsapp_number ? 'selected' : '' }}>
                            {{ $c->name }} ({{ substr($c->whatsapp_number, -4) }})
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <!-- Smartphone Frame Mockup -->
    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden border-8 border-slate-800 flex flex-col h-[740px]">
        
        <!-- Phone Top Status Bar -->
        <div class="bg-[#075e54] text-white px-5 pt-2 pb-1 flex justify-between items-center text-[11px] font-medium tracking-tight">
            <span>{{ date('H:i') }}</span>
            <div class="flex items-center space-x-1.5 opacity-90">
                <span>📶 4G</span>
                <span>🔋 98%</span>
            </div>
        </div>

        <!-- WhatsApp Chat Header -->
        <div class="bg-[#075e54] text-white p-3 px-4 flex items-center justify-between shadow-md z-10">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.conversations.index', ['selected' => $conversation->id]) }}" title="Buka di Inbox Admin" class="text-white hover:opacity-80">
                    &larr;
                </a>
                <div class="relative">
                    <div class="w-10 h-10 rounded-full bg-slate-900 border-2 border-emerald-400 flex items-center justify-center font-bold text-xs">
                        🚗
                    </div>
                </div>
                <div>
                    <div class="flex items-center space-x-1">
                        <h2 class="text-sm font-bold leading-tight">Showroom Bintang Mobil</h2>
                        <span class="text-emerald-400 text-xs" title="Official Verified Account">✓</span>
                    </div>
                    <span class="text-[11px] text-emerald-200 block">
                        {{ $conversation->is_bot_active ? 'online' : 'admin aktif membalas' }}
                    </span>
                </div>
            </div>
            
            <div class="flex items-center space-x-3 text-sm opacity-90">
                <span title="Status Bot" class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $conversation->is_bot_active ? 'bg-emerald-700 text-emerald-200' : 'bg-rose-700 text-rose-200' }}">
                    {{ $conversation->is_bot_active ? 'BOT AI' : 'ADMIN' }}
                </span>
            </div>
        </div>

        <!-- Chat Conversation Messages Body -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 wa-chat-bg" id="chatArea">
            <!-- Encryption notification badge -->
            <div class="text-center my-2">
                <span class="bg-[#ffeecd] text-[#54656f] text-[10px] font-medium px-3 py-1 rounded-lg shadow-sm inline-block">
                    🔒 Pesan dan panggilan terenkripsi end-to-end.
                </span>
            </div>

            @foreach($conversation->messages as $msg)
                @if($msg->direction === 'inbound')
                    <!-- Customer Bubble (Kanan Hijau Muda) -->
                    <div class="flex justify-end">
                        <div class="bg-[#d9fdd3] text-[#111b21] rounded-2xl rounded-tr-none px-3.5 py-2 max-w-[82%] shadow-sm text-xs space-y-1">
                            <p class="leading-relaxed whitespace-pre-line">{{ $msg->message_body }}</p>
                            <div class="flex items-center justify-end space-x-1 text-[10px] text-[#667781]">
                                <span>{{ $msg->created_at->format('H:i') }}</span>
                                <span class="text-blue-500 font-bold">✓✓</span>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Showroom Bot/Admin Bubble (Kiri Putih) -->
                    <div class="flex justify-start">
                        <div class="bg-white text-[#111b21] rounded-2xl rounded-tl-none px-3.5 py-2 max-w-[85%] shadow-sm text-xs space-y-2 border border-slate-100">
                            
                            <!-- Sender indicator -->
                            <div class="text-[10px] font-bold text-[#075e54] flex items-center space-x-1">
                                <span>{{ $msg->sender_type === 'admin' ? '👤 Admin Showroom' : '🤖 Bintang Mobil AI' }}</span>
                            </div>

                            @if($msg->media_url)
                                <div class="rounded-xl overflow-hidden bg-slate-900/5 border border-slate-200/80">
                                    @if($msg->message_type === 'video')
                                        <div class="aspect-video bg-slate-900 rounded flex flex-col items-center justify-center p-3 text-center">
                                            <span class="text-xl">🎬</span>
                                            <span class="text-white font-semibold text-[11px] mt-1">Video Unit Walkthrough</span>
                                            <span class="text-slate-400 text-[10px]">Klik untuk memutar</span>
                                        </div>
                                    @else
                                        <img src="{{ $msg->media_url }}" alt="Car" class="w-full h-36 object-cover rounded">
                                    @endif
                                </div>
                            @endif

                            <p class="leading-relaxed whitespace-pre-line">{{ $msg->message_body }}</p>
                            
                            <div class="text-right text-[10px] text-[#667781]">
                                {{ $msg->created_at->format('H:i') }}
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Quick Template Buttons -->
        <div class="bg-white border-t border-slate-200 p-2 overflow-x-auto flex gap-1.5 text-[11px]">
            <button type="button" onclick="fillMsg('Avanza 2022 ready gak min?')"
                class="bg-slate-100 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 text-slate-700 px-2.5 py-1 rounded-full border border-slate-200 whitespace-nowrap transition">
                Cek Avanza 2022
            </button>
            <button type="button" onclick="fillMsg('warna apa saja yang ready?')"
                class="bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full whitespace-nowrap font-medium transition">
                🎨 Warna Ready?
            </button>
            <button type="button" onclick="fillMsg('Kirim vidio Innova Reborn dong')"
                class="bg-slate-100 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 text-slate-700 px-2.5 py-1 rounded-full border border-slate-200 whitespace-nowrap transition">
                Video Innova Reborn
            </button>
            <button type="button" onclick="fillMsg('Mobil keluarga budget 150 jutaan ada apa?')"
                class="bg-slate-100 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 text-slate-700 px-2.5 py-1 rounded-full border border-slate-200 whitespace-nowrap transition">
                Budget 150 Juta
            </button>
            <button type="button" onclick="fillMsg('Bisa kirim ke Jakarta? Biaya berapa?')"
                class="bg-slate-100 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 text-slate-700 px-2.5 py-1 rounded-full border border-slate-200 whitespace-nowrap transition">
                Kirim Jakarta
            </button>
            <button type="button" onclick="fillMsg('Saya serius mau beli, Fortuner bisa nego 500 pas? Mau test drive')"
                class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-2.5 py-1 rounded-full whitespace-nowrap font-semibold transition">
                🔥 Nego & Test Drive (Handover)
            </button>
        </div>

        <!-- WhatsApp Input Box Form -->
        <form method="POST" action="{{ route('simulator.send') }}" class="bg-[#f0f2f5] p-2.5 flex items-center space-x-2 border-t border-slate-200">
            @csrf
            <input type="hidden" name="phone" value="{{ $customer->whatsapp_number }}">
            <input type="hidden" name="customer_name" value="{{ $customer->name }}">

            <div class="flex-1 relative">
                <input type="text" id="msgInput" name="message" required autocomplete="off"
                    placeholder="Ketik pesan seperti di WhatsApp..."
                    class="w-full bg-white rounded-full px-4 py-2 text-xs text-slate-800 border-none focus:outline-none focus:ring-1 focus:ring-emerald-500 shadow-sm">
            </div>

            <button type="submit"
                class="w-9 h-9 rounded-full bg-[#075e54] hover:bg-[#128c7e] text-white flex items-center justify-center shadow-md transition flex-shrink-0">
                <svg class="w-4 h-4 transform rotate-45 -translate-y-0.5 -translate-x-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path>
                </svg>
            </button>
        </form>

    </div>

    <script>
        function fillMsg(text) {
            document.getElementById('msgInput').value = text;
            document.getElementById('msgInput').focus();
        }

        // Auto-scroll chat area to bottom
        const chatArea = document.getElementById('chatArea');
        if (chatArea) {
            chatArea.scrollTop = chatArea.scrollHeight;
        }
    </script>
</body>
</html>
