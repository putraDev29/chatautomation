# BINTANG MOBIL WHATSAPP AI AUTO SALES

Sistem Customer Service & Sales Otomatis Showroom Mobil Bekas Berbasis WhatsApp Cloud API & AI Intelligence dengan Dashboard Pemantauan Admin Terintegrasi.

---

## 1. Arsitektur Aplikasi & Alur Kerja

Aplikasi dibangun menggunakan **Laravel 12 (PHP 8.2+)**, **MySQL lokal**, dan arsitektur berorientasi Service Layer yang memisahkan logika bisnis, gateway perpesanan, dan kecerdasan buatan:

```
[Customer WhatsApp / Simulation UI]
                │
                ▼
  [WhatsAppWebhookController / Simulation Endpoint]
                │
                ▼
   [WhatsAppMessageService (Idempotensi)]
                │
                ├──► Disimpan ke DB (wa_customers, wa_conversations, wa_messages)
                │
                ▼
   [AiSalesAssistantService]
       │
       ├──► Pengecekan Handover (Nego/Test Drive/Booking/Kredit/Admin)
       │       └──► Status NEEDS_ADMIN + Notifikasi Dashboard
       │
       ├──► Pencarian Mobil & Stok (CarSearchService) ──► wa_cars
       ├──► Media Mobil: Foto/Video (CarMediaService) ──► wa_car_media
       ├──► Estimasi Pengiriman Luar Kota (ShippingInformationService) ──► wa_shipping_rates
       │
       ▼
   [WhatsAppGatewayInterface]
       ├──► SimulatedWhatsAppGateway (Mode Default: Aman Lokal)
       └──► MetaWhatsAppCloudGateway (Mode Produksi: Meta Graph API)
```

### Dua Mode Operasional:
1. **Mode Simulasi (Default)**: Pesan masuk dan keluar diproses dan disimpan di MySQL lokal tanpa melakukan request keluar ke Meta WhatsApp Cloud API. Dashboard menyediakan tombol **Uji Kirim Pesan Customer (Simulasi)** untuk menguji respons bot secara langsung.
2. **Mode Produksi**: Menggunakan kredensial resmi Meta (`WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`) untuk mengirimkan pesan WhatsApp sungguhan. Dilindungi oleh proteksi `assertProductionModeAllowed()` yang mencegah pengiriman tidak disengaja selama mode simulasi aktif.

---

## 2. Prasyarat Sistem

- **PHP**: Versi 8.2 atau lebih baru (ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl`, `fileinfo` aktif)
- **Composer**: Versi 2.x
- **MySQL / MariaDB**: XAMPP atau MySQL server lokal aktif di port `3306`
- **Node.js & npm** (opsional): Node.js 18+ (tampilan frontend dashboard sudah menyertakan styling Tailwind modern responsif)

---

## 3. Instalasi dan Setup Cepat

### Langkah 1: Kloning / Buka Direktori Project
```bash
cd c:\project\chatAutomation
```

### Langkah 2: Dependensi PHP
```bash
composer install
```

### Langkah 3: Konfigurasi Environment (`.env`)
Salin file `.env.example` ke `.env` jika belum ada:
```bash
copy .env.example .env
php artisan key:generate
```

Pastikan konfigurasi koneksi database MySQL lokal pada file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bintang_mobil_wa
DB_USERNAME=root
DB_PASSWORD=

# Bintang Mobil WhatsApp AI Configuration
WHATSAPP_MODE=simulation
WHATSAPP_API_VERSION=v21.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_VERIFY_TOKEN=bintang_mobil_verify_token_2026
WHATSAPP_APP_SECRET=
WHATSAPP_WEBHOOK_ENABLED=false

# AI Configuration
AI_DRIVER=simulation
AI_API_KEY=
AI_MODEL=gpt-4o-mini
AI_MAX_OUTPUT_TOKENS=500
AI_TIMEOUT=30
AI_MAX_TOOL_CALLS=5
```

### Langkah 4: Buat Database MySQL & Jalankan Migrasi
Buat database `bintang_mobil_wa` di MySQL lokal:
```sql
CREATE DATABASE IF NOT EXISTS bintang_mobil_wa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Jalankan migration:
```bash
php artisan migrate
```

### Langkah 5: Jalankan Seeder Demo Idempotent
```bash
php artisan db:seed
```

Seeder akan membuat:
- **16 Unit Mobil Demo** (Toyota Avanza TSS 2022, Innova Reborn Diesel 2021, Fortuner GR Sport 2022, Honda Brio RS 2022, HR-V 2022, Mitsubishi Xpander 2022, Pajero Sport 2021, Suzuki Ertiga, XL7, Daihatsu Terios, Calya, Sigra, dll.) lengkap dengan data status stok (`TERSEDIA`, `BOOKING`, `TERJUAL`).
- **Media Demo** (placeholder SVG untuk foto eksterior, interior dashboard, dan video walkthrough).
- **5 Customer Demo** nomor internasional (`6281234567801` s/d `6281234567805`).
- **10 Skenario Percakapan Demo** (tanya ketersediaan, cek harga, minta foto/video interior, tanya tarif kirim towing luar kota, hingga negosiasi dan jadwal test drive).
- **Rute Pengiriman Luar Kota** (Surabaya ke Jakarta, Semarang, Yogyakarta, Bandung, Bali).
- **Akun Admin Showroom Demo**.

---

## 4. Menjalankan Aplikasi & Queue Worker

### Menjalankan Server Web Lokal:
```bash
php artisan serve
```
Akses di browser: [http://127.0.0.1:8000](http://127.0.0.1:8000)

### Menjalankan Queue Worker (Untuk Pemrosesan Pesan Asinkron):
```bash
php artisan queue:work --tries=3
```

---

## 5. Akses Dashboard Admin & Akun Demo

Akses halaman login di: [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)

**Kredensial Demo Lokal:**
- **Email**: `admin@bintangmobil.test`
- **Password**: `admin123456`

### Halaman yang Tersedia di Dashboard:
1. **Ringkasan Eksekutif (`/admin/dashboard`)**:
   - Kartu statistik real-time (Percakapan hari ini, Customer baru, Menunggu admin, Hot leads, Bot handled, Gagal kirim).
   - Grafik distribusi status percakapan dan ringkasan stok mobil langsung dari database.
   - Antrean prioritas handover yang memerlukan respons admin cepat.
2. **Inbox WhatsApp Desktop 3 Kolom (`/admin/conversations`)**:
   - **Kolom Kiri**: Daftar chat dengan filter status (`BOT_ACTIVE`, `NEEDS_ADMIN`, `ADMIN_ACTIVE`), search nama/nomor, dan preview pesan.
   - **Kolom Tengah**: Riwayat bubble chat inbound/outbound (customer, bot, admin), preview foto/video, status terkirim/sampai/dibaca, formulir balasan manual admin, tombol **Ambil Alih (Stop Bot)**, tombol **Aktifkan Kembali Bot**, dan formulir **⚡ Uji Kirim Pesan Customer (Simulasi)**.
   - **Kolom Kanan**: Profil customer, unit mobil yang diminati lengkap dengan harga, harga khusus internal admin, status stok, riwayat audit handover, serta update status pipeline lead.
3. **Katalog Unit Showroom (`/admin/cars`)**:
   - Tampilan unit mobil, harga jual, harga khusus internal, spesifikasi (kilometer, transmisi, bahan bakar, masa pajak), dan dropdown pembaruan status stok (`TERSEDIA`, `BOOKING`, `TERJUAL`, `TIDAK AKTIF`).
4. **Pipeline Lead & Penjualan (`/admin/leads`)**:
   - Pemantauan status lead (`NEW`, `QUALIFIED`, `HOT`, `NEGOTIATION`, `BOOKED`, `WON`, `LOST`), jadwal test drive, dan catatan follow up.
5. **Pengaturan Integrasi (`/admin/settings`)**:
   - Toggle switch Mode Simulasi vs Produksi.
   - Pemilihan Provider AI (`simulation`, `openai`, `gemini`).
   - Panduan pendaftaran Webhook Meta (Callback URL & Verify Token).
   - Jam operasional showroom dan daftar kata kunci pemicu handover otomatis.

---

## 6. Cara Menguji Bot Percakapan & Simulasi Pesan

### Metode A: Melalui Dashboard Admin (Paling Praktis)
1. Buka [http://127.0.0.1:8000/admin/conversations](http://127.0.0.1:8000/admin/conversations).
2. Pilih percakapan salah satu customer di kolom kiri.
3. Buka accordion **⚡ Uji Kirim Pesan Customer (Simulasi Lokal)** di bawah chat thread.
4. Masukkan pesan customer, contoh:
   - `Avanza 2022 ready gak min?` &rarr; Bot membalas bahwa unit Avanza 1.5 G TSS CVT ready seharga Rp 218.000.000, kilometer 24.500 km, dan menawarkan foto/video.
   - `Kirim vidio Innova Reborn dong` &rarr; Bot mengirim video walkthrough unit.
   - `Mobil keluarga budget 150 jutaan ada apa?` &rarr; Bot merekomendasikan Calya dan Sigra.
   - `Bisa kirim ke Jakarta gak min? Biaya berapa?` &rarr; Bot menginformasikan estimasi towing Rp 2,5jt - Rp 3,8jt (2-4 hari kerja) bergaransi asuransi.
   - `Saya serius mau beli, Fortuner bisa nego 500 pas? Mau test drive besok` &rarr; Bot mendeteksi intent negosiasi, membalas sopan menghubungkan admin, menandai percakapan **`NEEDS_ADMIN`**, mematikan balasan bot otomatis, dan menaikkan status lead ke **`HOT`**.
5. Admin dapat menekan tombol **Ambil Alih** untuk membalas pesan secara manual.

### Metode B: Melalui Endpoint API Simulasi (cURL / Postman)
```bash
curl -X POST http://127.0.0.1:8000/api/whatsapp/simulate \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "6281234567801",
    "customer_name": "Budi Santoso",
    "message": "Innova Reborn diesel harga berapa min?"
  }'
```

---

## 7. Cara Mengaktifkan Mode Produksi (Meta WhatsApp Cloud API)

1. Dapatkan akun **Meta for Developers** dan buat WhatsApp Business App di [developers.facebook.com](https://developers.facebook.com/).
2. Konfigurasikan file `.env`:
   ```env
   WHATSAPP_MODE=production
   WHATSAPP_PHONE_NUMBER_ID=1092837465xxxxx
   WHATSAPP_BUSINESS_ACCOUNT_ID=98273645xxxxx
   WHATSAPP_ACCESS_TOKEN=EAAG...
   WHATSAPP_VERIFY_TOKEN=bintang_mobil_verify_token_2026
   WHATSAPP_APP_SECRET=a1b2c3d4e5f6...
   WHATSAPP_WEBHOOK_ENABLED=true
   ```
3. Expose server lokal ke HTTPS menggunakan Ngrok / Cloudflare Tunnel:
   ```bash
   ngrok http 8000
   ```
4. Di Meta Developer App Dashboard (WhatsApp > Configuration):
   - **Callback URL**: `https://<domain-ngrok-anda>/api/whatsapp/webhook`
   - **Verify Token**: Nilai dari `WHATSAPP_VERIFY_TOKEN` (default: `bintang_mobil_verify_token_2026`)
   - Klik **Verify and save**.
   - Pada kolom Webhook fields, centang event: `messages`.

---

## 8. Menjalankan Automated Tests

Aplikasi dilengkapi dengan test suite lengkap berbasis PHPUnit:

```bash
php artisan test
```

### Hasil Pengujian yang Dijalankan:
```
PASS  Tests\Unit\ExampleTest (1/1)
PASS  Tests\Feature\AdminDashboardSecurityTest (4/4)
PASS  Tests\Feature\AiSalesAssistantTest (5/5)
PASS  Tests\Feature\CarSearchTest (6/6)
PASS  Tests\Feature\DatabaseAndModelTest (5/5)
PASS  Tests\Feature\ExampleTest (1/1)
PASS  Tests\Feature\HandoverAndAdminTakeoverTest (3/3)
PASS  Tests\Feature\WhatsAppIntegrationTest (5/5)

Tests:    30 passed (117 assertions)
Duration: 8.07s
```

---

## 9. Troubleshooting Error Umum

1. **MySQL Connection Refused (`SQLSTATE[HY000] [2002]`)**:
   - Pastikan XAMPP MySQL berjalan di port `3306`.
   - Cek database `bintang_mobil_wa` sudah dibuat di phpMyAdmin / MySQL CLI.
2. **Webhook Challenge 403 Forbidden**:
   - Pastikan token verify di query parameter `hub.verify_token` sama persis dengan `WHATSAPP_VERIFY_TOKEN` di file `.env`.
3. **Queue Jobs Tidak Berjalan**:
   - Jika `QUEUE_CONNECTION=database`, jalankan worker dengan `php artisan queue:work`. Untuk testing instan, `QUEUE_CONNECTION` dapat diset ke `sync`.

---

## 10. Estimasi Komponen Biaya Operasional (Production)

| Komponen | Provider | Estimasi Biaya |
|---|---|---|
| **WhatsApp Service Conversation** | Meta Cloud API | Gratis untuk 1.000 percakapan service per bulan (customer-initiated window 24 jam). Setelah itu ~Rp 300 - Rp 450 per percakapan (sesuai tarif Meta ID). |
| **AI LLM API (gpt-4o-mini)** | OpenAI API | ~$0.15 per 1 juta input token / ~$0.60 per 1 juta output token (~Rp 15.000 - Rp 30.000 untuk 1.000 percakapan). |
| **Hosting & Database VPS** | Cloud VPS (DigitalOcean / IDCloudHost) | ~Rp 100.000 - Rp 250.000 / bulan (2 vCPU, 4GB RAM, SSD NVMe). |
| **Towing Luar Kota** | Rekanan Ekspedisi | Dibebankan kepada pembeli mobil (sudah tercover di penawaran). |

---

## 11. Status Fitur

- [x] Database MySQL Lokal dengan Skema `wa_*` Terisolasi
- [x] Seeder Idempotent (16 unit mobil demo, 5 customer, 10 percakapan, media, rute pengiriman)
- [x] Service Pencarian Mobil dengan Normalisasi Bahasa Indonesia Bebas
- [x] Service Media Mobil (Foto Eksterior, Interior, Video)
- [x] Handover Percakapan Otomatis & Manual (Stop bot otomatis saat admin ambil alih)
- [x] Simulated WhatsApp Gateway (Aman di lingkungan lokal)
- [x] Meta WhatsApp Cloud API Gateway Resmi dengan Guardrail Keselamatan
- [x] Webhook Verification Challenge & HMAC SHA256 Signature Validator
- [x] Idempotensi Pesan Inbound (Anti duplikasi webhook)
- [x] Simulated AI Sales Assistant (Bahasa Indonesia showroom, anti-halusinasi)
- [x] OpenAI Integration Provider (dengan Fallback aman)
- [x] Dashboard Eksekutif Statistik Real-Time
- [x] WhatsApp Inbox Desktop 3 Kolom & Responsif Mobile
- [x] Fitur Simulasi Kirim Pesan Customer Langsung dari Dashboard
- [x] Manajemen Stok Showroom & Pipeline Penjualan
- [x] 30 Automated Feature & Unit Tests (100% Passed)
#   c h a t a u t o m a t i o n  
 