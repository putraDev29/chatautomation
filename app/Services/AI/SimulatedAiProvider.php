<?php

namespace App\Services\AI;

use App\Models\Car;
use App\Services\Cars\CarMediaService;
use App\Services\Cars\CarSearchService;
use App\Services\Conversations\ConversationHandoverService;
use App\Services\Shipping\ShippingInformationService;

class SimulatedAiProvider implements AiProviderInterface
{
    public function __construct(
        protected CarSearchService $carSearchService,
        protected CarMediaService $carMediaService,
        protected ShippingInformationService $shippingService,
        protected ConversationHandoverService $handoverService
    ) {}

    public function getDriverName(): string
    {
        return 'simulation';
    }

    public function process(string $message, array $context = []): array
    {
        $textLower = strtolower(trim($message));
        $customerName = $context['customer_name'] ?? 'Kak';
        $currentCarId = $context['interested_car_id'] ?? null;
        $currentCar = $currentCarId ? Car::find($currentCarId) : null;

        // 1. Check if Customer provides their car's specifications and condition (Trade-In / Valuation)
        $customerCarDetails = $this->parseCustomerTradeInCarDetails($textLower);
        if ($customerCarDetails !== null) {
            $cBrand = $customerCarDetails['brand'];
            $cModel = $customerCarDetails['model'];
            $cYear = !empty($customerCarDetails['year']) ? "tahun {$customerCarDetails['year']}" : '';
            $cTrans = $customerCarDetails['transmission'] ?: 'Manual / Matic';
            $cKm = !empty($customerCarDetails['mileage']) ? "• Jarak Tempuh: **{$customerCarDetails['mileage']}**\n" : '';
            $cPlate = !empty($customerCarDetails['plate']) ? "• Plat Nomor: **{$customerCarDetails['plate']}**\n" : '';
            $cTax = !empty($customerCarDetails['tax']) ? "• Status Pajak: **{$customerCarDetails['tax']}**\n" : '';
            $cCond = !empty($customerCarDetails['conditions']) ? '• Kondisi: **' . implode(', ', $customerCarDetails['conditions']) . "**\n" : '';

            // Detect target showroom car if customer mentions one in text or active conversation
            $targetCar = null;
            $parsedFilters = $this->carSearchService->parseQueryFromText($textLower);
            if (!empty($parsedFilters['model']) && strtolower($parsedFilters['model']) !== strtolower($cModel)) {
                $targetResults = $this->carSearchService->search(['model' => $parsedFilters['model']]);
                $targetCar = $targetResults->first();
            } elseif ($currentCar && strtolower($currentCar->model) !== strtolower($cModel)) {
                $targetCar = $currentCar;
            }

            $reply = "Wah mantap sekali Kak {$customerName}! Data mobil lama Kakak sudah kami rekap dengan rapi: 🚗📋\n\n"
                . "📋 **Spesifikasi Mobil Trade-In Kakak**:\n"
                . "• Unit: **{$cBrand} {$cModel} {$cYear}**\n"
                . "• Transmisi: **{$cTrans}**\n"
                . $cKm
                . $cPlate
                . $cTax
                . $cCond
                . "\n🌟 **Estimasi Penilaian Showroom Bintang Mobil**:\n"
                . "Unit **{$cModel} {$cYear}** dengan kondisi terawat seperti ini pasarnya sangat bagus dan **nilai taksirannya tinggi & kompetitif** di showroom kami!\n\n";

            if ($targetCar) {
                $reply .= "🎯 **Rencana Tukar Tambah ke Unit Showroom ({$targetCar->full_name} - {$targetCar->formatted_price})**:\n"
                    . "• Nilai taksiran mobil lama Kakak langsung kami potongkan dari harga {$targetCar->model}.\n"
                    . "• Selisih pembayarannya sangat fleksibel: bisa dibayar **Cash/Transfer** atau dijadikan **DP murni untuk pengajuan kredit** cicilan ringan!\n\n";
            }

            $reply .= "📸 **Langkah Cepat Selanjutnya**:\n"
                . "1. Silakan kirimkan beberapa foto mobil Kakak (tampak depan, samping, interior/speedometer, & ruang mesin) langsung di chat ini untuk estimasi nominal awal.\n"
                . "2. Atau Kakak bisa langsung bawa unitnya ke showroom kami di **Jl. Raya Jemursari No. 120, Wonocolo, Surabaya** untuk **inspeksi fisik gratis & taksiran harga final instan** (15 menit beres!).\n\n"
                . "Kira-kira Kakak ada waktu luang untuk bawa unitnya cek fisik hari ini atau besok Kak? 😊";

            $wantsMedia = $this->carMediaService->detectRequestedMediaType($textLower) !== null;

            return [
                'reply_text' => $reply,
                'media_url' => ($wantsMedia && $targetCar) ? $targetCar->main_photo : null,
                'media_type' => ($wantsMedia && $targetCar) ? 'image' : null,
                'tool_called' => 'customerCarTradeInAppraisal',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $targetCar?->id ?: $currentCarId,
            ];
        }

        // 2. Check General Trade-In (TT / Tukar Tambah) Inquiry (with abbreviations TT, T.T, BT, Trade In)
        if (preg_match('/\b(tt|t\.t|bt|trade\s*in|tukar\s*tambah|tuker\s*tambah|tukar\s*mobil|tuker\s*mobil|tukar\s*unit|tuker\s*unit|barter)\b/i', $textLower) || (str_contains($textLower, 'tukar') && (str_contains($textLower, 'mobil') || str_contains($textLower, 'unit')))) {
            $parsedFilters = $this->carSearchService->parseQueryFromText($textLower);
            $targetCar = null;
            if (!empty($parsedFilters['model']) || !empty($parsedFilters['brand'])) {
                $carResults = $this->carSearchService->search($parsedFilters);
                $targetCar = $carResults->first();
            } elseif ($currentCar) {
                $targetCar = $currentCar;
            }

            if ($targetCar) {
                $reply = "Bisa banget Kak {$customerName}! Showroom Bintang Mobil sangat menyambut **TUKAR TAMBAH / TT (Trade-In)** ke unit **{$targetCar->full_name}**! 🚗🔄\n\n"
                    . "• Harga unit {$targetCar->model}: {$targetCar->formatted_price}\n"
                    . "• Nilai mobil lama Kakak bisa langsung dipotongkan dari harga unit, atau dijadikan DP murni jika pengajuan kredit.\n"
                    . "• Taksiran harga bersahabat, objektif, dan transparan oleh tim appraisal showroom kami.\n\n"
                    . "Untuk estimasi awal selisih harga tukar tambah, mohon infokan detail mobil lama Kakak:\n"
                    . "1. Merek, Tipe, & Tahun pembuatan:\n"
                    . "2. Transmisi (Manual/Matic) & perkiraan KM:\n"
                    . "3. Kota plat nomor & status pajak STNK:\n"
                    . "4. Kondisi fisik & mesin (bisa kirimkan foto unitnya):\n\n"
                    . "Bisa dikirimkan detailnya ya Kak, agar tim kami bisa langsung taksirkan harganya! 😊";

                $wantsMedia = $this->carMediaService->detectRequestedMediaType($textLower) !== null;

                return [
                    'reply_text' => $reply,
                    'media_url' => ($wantsMedia && $targetCar) ? $targetCar->main_photo : null,
                    'media_type' => ($wantsMedia && $targetCar) ? 'image' : null,
                    'tool_called' => 'tradeInInquiry',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $targetCar->id,
                ];
            } else {
                $reply = "Bisa banget Kak {$customerName}! Showroom Bintang Mobil Surabaya melayani **TUKAR TAMBAH / TT (Trade-In)** untuk semua merek dan tipe mobil lama Kakak. 🚗🔄\n\n"
                    . "🌟 **Keunggulan Tukar Tambah di Bintang Mobil:**\n"
                    . "1. **Harga Taksiran Fair & Tinggi**: Penilaian kondisi mobil objektif, transparan, dan kompetitif.\n"
                    . "2. **Proses Cepat 1 Hari Kerja**: Bisa tukar tambah ke Cash (kembalian dana jika unit lama lebih tinggi) maupun Kredit (mobil lama jadi DP murni).\n"
                    . "3. **Semua Merek Diterima**: Toyota, Honda, Daihatsu, Suzuki, Mitsubishi, Nissan, Wuling, Mazda, Hyundai, dll.\n"
                    . "4. **Pajak Mati Tetap Dibantu**: Kami siap bantu perpanjangan atau cabut berkas.\n\n"
                    . "Boleh tahu Kak, mobil lama yang ingin di-TT merek, tipe, dan tahun berapa? Dan berencana ingin tukar tambah dengan unit mobil apa di showroom kami? 😊";

                return [
                    'reply_text' => $reply,
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'tradeInInquiry',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCarId,
                ];
            }
        }

        // 2. Check Direct Purchase by Showroom (Customer ingin jual mobil ke showroom / Titip Jual)
        if (str_contains($textLower, 'jual mobil') || str_contains($textLower, 'mau jual') || str_contains($textLower, 'beli mobil bekas') || str_contains($textLower, 'titip jual') || str_contains($textLower, 'nawarin mobil') || str_contains($textLower, 'bisa beli mobil saya')) {
            return [
                'reply_text' => "Showroom Bintang Mobil Surabaya siap **BELI TUNAI (Direct Buy)** mobil bekas berkualitas dari Kakak dengan harga terbaik dan pencairan dana kilat hari ini juga! 💵🚗\n\n"
                    . "• **Kriteria Mobil yang Kami Beli**: Tahun 2014 ke atas (Toyota, Honda, Suzuki, Mitsubishi, Daihatsu, dll), surat BPKB & STNK resmi lengkap, serta 100% bebas tabrak berat & bebas banjir.\n"
                    . "• **Pembayaran Tunai**: Uang langsung ditransfer lunas ke rekening Kakak saat cek fisik & dokumen deal tanpa potongan aneh-aneh.\n\n"
                    . "Boleh infokan detail mobil yang ingin dijual (Merek, Tipe, Tahun, Transmisi, Kota Plat nomor, dan Kondisi) Kak? 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'sellCarToShowroom',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 3. Check Credit Terms & Financing FAQ
        if (preg_match('/\b(syarat kredit|simulasi kredit|dp berapa|tdp berapa|angsuran berapa|cicilan berapa|leasing apa|bisa kredit|kredit apa saja|pengajuan kredit|dp minim|tdp minimal|bisa dp minim)\b/i', $textLower) || ((str_contains($textLower, 'syarat') || str_contains($textLower, 'simulasi') || str_contains($textLower, 'pengajuan') || preg_match('/\b(dp|tdp)\b/i', $textLower)) && (str_contains($textLower, 'kredit') || str_contains($textLower, 'leasing') || str_contains($textLower, 'cicil') || str_contains($textLower, 'angsuran') || str_contains($textLower, 'berapa')))) {
            return [
                'reply_text' => "Untuk pengajuan kredit di Showroom Bintang Mobil Surabaya prosesnya sangat mudah dan kami dampingi sampai ACC (approval cepat 1-3 hari kerja) Kak! 📑🚗\n\n"
                    . "💼 **Rekanan Leasing Resmi Berizin OJK:**\n"
                    . "• BCA Finance, Mandiri Tunas Finance (MTF), Adira Finance, Maybank, CIMB Niaga Auto Finance, Mega Auto Finance (Tersedia skema Pembiayaan Syariah & Konvensional).\n\n"
                    . "📋 **Persyaratan Dokumen Pengajuan Kredit Perorangan:**\n"
                    . "1. Fotokopi KTP Pemohon & Pasangan (Suami/Istri)\n"
                    . "2. Kartu Keluarga (KK)\n"
                    . "3. PBB / Bukti Kepemilikan Rumah / Bukti Listrik\n"
                    . "4. Slip Gaji 3 bulan terakhir (Karyawan) atau SKU & nota usaha (Wiraswasta)\n"
                    . "5. Mutasi Rekening Tabungan 3 bulan terakhir\n\n"
                    . "DP / TDP mulai 15% - 20% dengan tenor fleksibel 1 hingga 5 tahun. Mau kami bantu hitungkan simulasi cicilannya untuk unit tertentu Kak? 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'creditFaqInquiry',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 4. Check Warranty, Condition & Inspection Standards FAQ
        if (str_contains($textLower, 'garansi') || str_contains($textLower, 'jaminan') || str_contains($textLower, 'bebas tabrak') || str_contains($textLower, 'bebas banjir') || str_contains($textLower, 'inspeksi') || str_contains($textLower, 'bawa montir') || str_contains($textLower, 'bawa bengkel') || str_contains($textLower, 'otospector') || preg_match('/\b(no pr|bebas laka|km asli|odo asli)\b/i', $textLower)) {
            return [
                'reply_text' => "Showroom Bintang Mobil Surabaya menjamin standar kualitas mobil bekas terbaik melalui **150+ titik inspeksi menyeluruh** Kak! 🛡️🌟\n\n"
                    . "🌟 **5 Jaminan Pasti Bintang Mobil:**\n"
                    . "1. **100% Bebas Tabrakan Berat**: Rangka sasis, pilar utama, dan bullhead utuh bawaan pabrik.\n"
                    . "2. **100% Bebas Rendaman Banjir**: Bebas karat abnormal, interior bersih wangi, jalur kelistrikan aman.\n"
                    . "3. **Odometer Asli & Terjamin**: Bukan kilometer putaran, riwayat servis resmi tercatat.\n"
                    . "4. **Dokumen 100% Absah**: BPKB, STNK, dan Faktur terverifikasi Samsat & Polda.\n"
                    . "5. **Garansi Mesin & Transmisi**: Jaminan kenyamanan pasca serah terima unit (Unit No PR & siap pakai luar kota).\n\n"
                    . "Kakak **sangat dipersilakan** membawa montir pribadi, teknisi bengkel resmi, atau jasa inspeksi independen (Otospector / Chekko) untuk cek unit sepuasnya di showroom kami! Ada unit yang ingin dicek hari ini Kak? 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'warrantyFaqInquiry',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 5. Check Documents, Tax Extension & Name Transfer FAQ (SS, BBN, Pajak)
        if (str_contains($textLower, 'balik nama') || str_contains($textLower, 'bbn') || str_contains($textLower, 'cabut berkas') || str_contains($textLower, 'mutasi') || (str_contains($textLower, 'bantu') && str_contains($textLower, 'pajak')) || str_contains($textLower, 'biro jasa') || preg_match('/\b(ss lengkap|ss komplit|surat lengkap|surat2|pjk on|pjk hidup)\b/i', $textLower)) {
            return [
                'reply_text' => "Semua unit mobil di Bintang Mobil berdokumen lengkap, asli, dan sah secara hukum (BPKB, STNK, Faktur Pembelian, NIK Pabrik) Kak. 📄\n\n"
                    . "Kami juga menyediakan **Layanan Biro Jasa Resmi Showroom**:\n"
                    . "• Bantuan perpanjangan STNK tahunan & 5 tahunan.\n"
                    . "• Bantuan proses Balik Nama (BBN) langsung ke atas nama Kakak.\n"
                    . "• Bantuan Cabut Berkas / Mutasi antar kota & antar provinsi di seluruh Indonesia.\n\n"
                    . "Kakak tidak perlu repot antre di Samsat, tim legal showroom siap mengurus sampai tuntas. Ada unit tertentu yang ingin dicek status pajaknya Kak? 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'documentsFaqInquiry',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 6. Check Booking & Down Payment Procedure FAQ
        if (str_contains($textLower, 'cara booking') || str_contains($textLower, 'prosedur booking') || str_contains($textLower, 'tanda jadi berapa') || str_contains($textLower, 'uang kembali') || str_contains($textLower, 'refundable')) {
            return [
                'reply_text' => "Prosedur booking unit di Bintang Mobil Surabaya sangat aman dan terpercaya Kak: 🔒\n\n"
                    . "1. **Kunci Unit (Tanda Jadi)**: Cukup mulai Rp 5.000.000 disertai Surat Tanda Terima & Kuitansi resmi berstempel showroom.\n"
                    . "2. **Unit Dikunci Eksklusif**: Mobil tidak akan ditawarkan ke customer lain selama masa booking.\n"
                    . "3. **100% Refundable (Uang Kembali Penuh)**: Jika saat cek fisik langsung kondisi mobil tidak sesuai deskripsi, atau jika pengajuan kredit ditolak pihak leasing, uang tanda jadi kami kembalikan 100% utuh tanpa potongan sepeser pun!\n\n"
                    . "Boleh saya bantu hubungkan dengan Admin Bintang Mobil untuk proses penguncian unit yang Kakak minati? 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'bookingFaqInquiry',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 7. Check Handover Trigger (Negotiation, Test Drive, Credit Calculation, Human Request)
        $handoverCheck = $this->handoverService->detectHandoverTrigger($message);
        if ($handoverCheck) {
            $carName = $currentCar ? $currentCar->full_name : 'unit yang Kakak minati';
            $reply = match ($handoverCheck['intent']) {
                'NEGOTIATION' => "Siap {$customerName}, terima kasih atas minat seriusnya pada {$carName}! 😊 Mengenai penawaran harga terbaik dan negosiasi unit sampai deal, saya bantu sambungkan langsung dengan Tim Admin Bintang Mobil agar mendapatkan harga spesial showroom ya. Mohon tunggu sebentar.",
                'TEST_DRIVE' => "Wah senang sekali mendengarnya Kak! 🚗 Untuk jadwal test drive dan inspeksi langsung unit {$carName} di showroom, saya teruskan ke Admin Showroom Bintang Mobil untuk mengunci slot jadwal Kakak hari ini ya.",
                'BOOKING' => "Siap {$customerName}! Untuk prosedur booking tanda jadi yang aman dan penguncian unit {$carName} agar tidak didahului customer lain, saya segera hubungkan dengan Admin Bintang Mobil ya. 😊",
                'CREDIT_CALCULATION' => "Untuk simulasi kredit {$carName} dengan DP paling ringan, angsuran fleksibel, dan rekanan leasing resmi terpercaya, saya bantu sambungkan dengan Konsultan Pembiayaan Showroom Bintang Mobil sekarang ya Kak.",
                'TALK_TO_HUMAN' => "Baik {$customerName}, saya langsung alihkan percakapan ini ke Admin Customer Service Bintang Mobil Surabaya. Mohon tunggu sebentar ya Kak, admin kami akan segera merespons. 🙏",
                default => "Terima kasih atas minat seriusnya {$customerName}. Saya bantu hubungkan dengan Admin Bintang Mobil agar prosesnya segera ditindaklanjuti ya. 😊",
            };

            return [
                'reply_text' => $reply,
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'requestAdminHandover',
                'handover_required' => true,
                'handover_reason' => $handoverCheck['reason'],
                'interested_car_id' => $currentCarId,
            ];
        }

        // 8. Check Media Inquiry (Photos, Interior, Engine, Video)
        $requestedMediaType = $this->carMediaService->detectRequestedMediaType($textLower);
        if ($requestedMediaType) {
            $targetCar = null;
            // Identify target car from query or previous context
            $parsedFilters = $this->carSearchService->parseQueryFromText($textLower);
            if (!empty($parsedFilters['model']) || !empty($parsedFilters['brand'])) {
                $carResults = $this->carSearchService->search($parsedFilters);
                $targetCar = $carResults->first();
            } elseif ($currentCar) {
                $targetCar = $currentCar;
            }

            if ($targetCar) {
                $mediaData = $this->carMediaService->getMediaForCar($targetCar, $requestedMediaType);
                if ($mediaData['has_media'] && !empty($mediaData['items'])) {
                    $firstMedia = $mediaData['items'][0];
                    $label = match ($requestedMediaType) {
                        'video' => 'video walkthrough & suara mesin',
                        'photo_interior' => 'foto interior & dashboard',
                        'photo_engine' => 'foto ruang mesin',
                        'photo_odometer' => 'foto speedometer/odometer',
                        default => 'foto eksterior',
                    };

                    return [
                        'reply_text' => "Berikut {$label} untuk unit {$targetCar->full_name}. Kondisi sangat terawat, orisinil, dan siap pakai! Mau saya bantu jadwalkan test drive atau cek unitnya langsung di showroom Kak? 😊",
                        'media_url' => $firstMedia['url'],
                        'media_type' => $firstMedia['is_video'] ? 'video' : 'image',
                        'tool_called' => 'getCarMedia',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => $targetCar->id,
                    ];
                } else {
                    return [
                        'reply_text' => "Mohon maaf {$customerName}, untuk unit {$targetCar->full_name} file {$requestedMediaType} sedang dalam proses update oleh tim showroom. Boleh saya hubungkan dengan admin untuk dikirimkan video/foto langsung?",
                        'media_url' => null,
                        'media_type' => null,
                        'tool_called' => 'getCarMedia',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => $targetCar->id,
                    ];
                }
            }
        }

        // 9. Check Intercity Delivery Inquiry (Shipping unit to another city)
        if (str_contains($textLower, 'kirim') || str_contains($textLower, 'ongkir') || str_contains($textLower, 'towing') || str_contains($textLower, 'ekspedisi')) {
            $city = $this->shippingService->extractCity($textLower);
            if ($city) {
                $estimate = $this->shippingService->getDeliveryEstimate($city);
                return [
                    'reply_text' => "Halo {$customerName}! " . $estimate['text_summary'] . " Apakah ada unit tertentu yang ingin dikirimkan ke {$city}?",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getDeliveryInformation',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCarId,
                ];
            } else {
                return [
                    'reply_text' => "Halo {$customerName}, Showroom Bintang Mobil melayani pengiriman mobil ke seluruh Indonesia via towing single maupun car carrier bergaransi resmi. Boleh tahu rencana pengiriman ke kota mana Kak? 😊",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getDeliveryInformation',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCarId,
                ];
            }
        }

        // 10. Check Showroom Location & Operating Hours FAQ
        if (str_contains($textLower, 'lokasi') || str_contains($textLower, 'alamat') || str_contains($textLower, 'dimana') || str_contains($textLower, 'jam buka') || str_contains($textLower, 'buka jam') || str_contains($textLower, 'jam operasional') || str_contains($textLower, 'tutup jam') || str_contains($textLower, 'jam berapa') || (str_contains($textLower, 'buka') && str_contains($textLower, 'jam'))) {
            return [
                'reply_text' => "Showroom **Bintang Mobil** berlokasi di Jl. Raya Jemursari No. 120, Wonocolo, Surabaya.\n\n"
                    . "⏰ **Jam Operasional Showroom**:\n"
                    . "• Buka **Setiap Hari** (Senin - Minggu): **08.30 - 18.00 WIB**\n"
                    . "• Melayani inspeksi unit langsung, konsultasi simulasi kredit, tukar tambah, dan test drive unit bergaransi.\n\n"
                    . "Kakak berencana berkunjung hari ini atau akhir pekan? Kami siap dampingi untuk cek unit langsung! 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'getShowroomHoursLocation',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 11. Check Color Availability Inquiry (Synchronized with active car / previous inquiry)
        if ($this->isColorInquiry($textLower)) {
            return $this->handleColorInquiry($textLower, $context, $customerName, $currentCar);
        }

        // 4. Check Complete Stock List / All Ready Units Inquiry (e.g. "mobil apa saja yg ready tampilkan semua listnya")
        if ($this->isAllReadyCarsInquiry($textLower)) {
            $allReadyCars = $this->carSearchService->getAllAvailableCars();

            if ($allReadyCars->isEmpty()) {
                $allReadyCars = Car::where('stock_status', 'TERSEDIA')->orderBy('year', 'desc')->get();
            }

            $reply = "Halo Kak {$customerName}! Berikut **DAFTAR LENGKAP** semua unit mobil yang **READY & SIAP PAKAI** di Showroom Bintang Mobil Surabaya saat ini: 🚗✨\n\n";

            foreach ($allReadyCars as $idx => $rCar) {
                $num = $idx + 1;
                $reply .= "{$num}. 🚗 **{$rCar->full_name}**\n"
                    . "   • Harga: **{$rCar->formatted_price}**\n"
                    . "   • Transmisi: {$rCar->transmission} | Warna: {$rCar->color}\n"
                    . "   • Odometer: {$rCar->formatted_mileage} (asli)\n"
                    . "   • Pajak: STNK {$rCar->stnk_status} (BPKB: {$rCar->bpkb_status} Ready)\n\n";
            }

            $reply .= "🌟 **Keunggulan Unit di Bintang Mobil**:\n"
                . "• 100% Lulus inspeksi ketat 150+ titik, bergaransi bebas banjir & bebas tabrakan.\n"
                . "• Pembayaran fleksibel: **Cash keras**, **Kredit DP ringan / cicilan murah**, maupun **Tukar Tambah (Trade-In)**.\n\n"
                . "Dari daftar seluruh unit ready di atas, ada tipe mobil yang paling Kakak minati untuk cek foto detail, simulasi cicilan, atau test drive langsung Kak? 😊";

            return [
                'reply_text' => $reply,
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'listAllAvailableCars',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $allReadyCars->first()?->id,
            ];
        }

        // 5. Check Specific Model, Brand, Budget, or Year Inquiry
        $parsedFilters = $this->carSearchService->parseQueryFromText($textLower);

        // Check if user specifically asks about availability of a model, brand, budget, or body type
        if (!empty($parsedFilters['model']) || !empty($parsedFilters['brand']) || !empty($parsedFilters['max_price']) || !empty($parsedFilters['body_type'])) {
            $searchResults = $this->carSearchService->search($parsedFilters);

            if ($searchResults->isNotEmpty()) {
                if ($searchResults->count() === 1) {
                    $car = $searchResults->first();
                    $isSwitching = ($currentCarId && $currentCar && $car->id !== $currentCarId);
                    $greetingPrefix = $isSwitching
                        ? "Siap Kak {$customerName}, beralih ke unit **{$car->full_name}** ya! 🚗\n\nUntuk unit ini masih **READY TERSEDIA** di {$car->location}:\n\n"
                        : "Halo {$customerName}! Unit **{$car->full_name}** warna {$car->color} masih **READY TERSEDIA** di {$car->location}. 🚗\n\n";

                    $isAskingPrice = str_contains($textLower, 'harga') || str_contains($textLower, 'berapa') || str_contains($textLower, 'biaya');

                    if ($isAskingPrice) {
                        $reply = "Halo {$customerName}! Untuk unit **{$car->full_name}**, harga kami tawarkan di **{$car->formatted_price}** (masih bisa nego tipis di showroom ya Kak). 😊\n\n"
                            . "• Transmisi: {$car->transmission}\n"
                            . "• Warna: {$car->color}\n"
                            . "• Odometer: {$car->formatted_mileage} (asli)\n"
                            . "• Pajak: STNK {$car->stnk_status} (BPKB: {$car->bpkb_status})\n"
                            . "• Pembayaran: Cash, Kredit DP ringan, atau Tukar Tambah\n\n"
                            . "Apakah Kakak ingin dikirimkan foto interiornya atau simulasi kredit DP-nya? 🚗";
                    } else {
                        $reply = $greetingPrefix
                            . "• Harga: {$car->formatted_price} (Melayani Cash / Kredit / Tukar Tambah)\n"
                            . "• Transmisi: {$car->transmission}\n"
                            . "• Odometer: {$car->formatted_mileage} (Kilometer asli service record)\n"
                            . "• Pajak: STNK {$car->stnk_status} (BPKB: {$car->bpkb_status} Lengkap)\n"
                            . "• Kondisi: 100% Bebas banjir & bebas tabrak, mesin istimewa siap pakai!\n\n"
                            . "Apakah Kakak ingin saya kirimkan foto detail interior atau video unit {$car->model} ini? 😊";
                    }

                    $wantsMedia = $this->carMediaService->detectRequestedMediaType($textLower) !== null;

                    return [
                        'reply_text' => $reply,
                        'media_url' => ($wantsMedia && $car->main_photo) ? $car->main_photo : null,
                        'media_type' => ($wantsMedia && $car->main_photo) ? 'image' : null,
                        'tool_called' => 'getCarDetails',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => $car->id,
                    ];
                } else {
                    // Multiple cars match
                    $reply = "Halo {$customerName}! Berikut beberapa pilihan unit yang cocok dengan pencarian Kakak di Bintang Mobil:\n\n";
                    foreach ($searchResults->take(4) as $idx => $car) {
                        $num = $idx + 1;
                        $reply .= "{$num}. **{$car->full_name}** - {$car->formatted_price} ({$car->transmission}, {$car->color}, {$car->formatted_mileage})\n";
                    }
                    $reply .= "\nSemua unit lulus inspeksi & bergaransi bebas banjir. Tertarik melihat detail atau foto unit nomor berapa Kak? 😊";

                    return [
                        'reply_text' => $reply,
                        'media_url' => null,
                        'media_type' => null,
                        'tool_called' => 'searchCars',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => $searchResults->first()->id,
                    ];
                }
            } else {
                // SPECIAL LOGIC: If a specific year was requested (e.g. Innova Reborn 2022) but that year is not in stock,
                // check if the showroom has that same model (or brand) with alternative years (e.g. Innova Reborn 2021).
                $searchedYear = $parsedFilters['year'] ?? null;
                $searchedModel = $parsedFilters['model'] ?? null;
                $searchedBrand = $parsedFilters['brand'] ?? null;

                if ($searchedYear && ($searchedModel || $searchedBrand)) {
                    $filtersNoYear = $parsedFilters;
                    unset($filtersNoYear['year']);
                    if (isset($filtersNoYear['min_year'])) {
                        unset($filtersNoYear['min_year']);
                    }

                    $alternativeCars = $this->carSearchService->search($filtersNoYear);

                    if ($alternativeCars->isNotEmpty()) {
                        $modelOrBrandName = $searchedModel ?: $searchedBrand;

                        if ($alternativeCars->count() === 1) {
                            $altCar = $alternativeCars->first();
                            $reply = "Untuk **{$altCar->model} tahun {$searchedYear}** saat ini kebetulan **belum ada** ya Kak {$customerName}.\n\n"
                                . "Namun unit yang **READY saat ini** di Showroom Bintang Mobil Surabaya ada **{$altCar->full_name}**:\n\n"
                                . "• Harga: **{$altCar->formatted_price}** (Bisa Nego / Cash / Kredit / Tukar Tambah)\n"
                                . "• Transmisi: {$altCar->transmission}\n"
                                . "• Warna: {$altCar->color}\n"
                                . "• Odometer: {$altCar->formatted_mileage} (Kilometer asli service record)\n"
                                . "• Pajak: STNK {$altCar->stnk_status} (BPKB: {$altCar->bpkb_status} Lengkap & sah)\n"
                                . "• Kondisi: Istimewa terawat, 100% bebas banjir & bebas tabrak, mesin sangat prima siap pakai!\n\n"
                                . "Kondisi unit tahun {$altCar->year} ini sangat istimewa dan tidak kalah dengan tahun {$searchedYear}. Yang ready unit ini Kak, apakah berminat cek unitnya langsung atau mau kami kirimkan foto detailnya Kak? 😊";

                            $wantsMedia = $this->carMediaService->detectRequestedMediaType($textLower) !== null;

                            return [
                                'reply_text' => $reply,
                                'media_url' => ($wantsMedia && $altCar->main_photo) ? $altCar->main_photo : null,
                                'media_type' => ($wantsMedia && $altCar->main_photo) ? 'image' : null,
                                'tool_called' => 'suggestSameModelAlternativeYear',
                                'handover_required' => false,
                                'handover_reason' => null,
                                'interested_car_id' => $altCar->id,
                            ];
                        } else {
                            $reply = "Untuk **{$modelOrBrandName} tahun {$searchedYear}** saat ini kebetulan **belum ada** ya Kak {$customerName}.\n\n"
                                . "Namun unit **{$modelOrBrandName}** yang **READY saat ini** di Showroom Bintang Mobil Surabaya ada:\n\n";

                            foreach ($alternativeCars as $idx => $altCar) {
                                $num = $idx + 1;
                                $reply .= "{$num}. 🚗 **{$altCar->full_name}** - {$altCar->formatted_price} ({$altCar->transmission}, {$altCar->color}, {$altCar->formatted_mileage})\n";
                            }

                            $reply .= "\nSemua unit di atas terawat istimewa & bergaransi bebas banjir/tabrak. Yang ready unit-unit ini Kak, dari daftar di atas ada yang ingin ditanyakan atau mau kami kirimkan fotonya? 😊";

                            $wantsMedia = $this->carMediaService->detectRequestedMediaType($textLower) !== null;
                            $firstCar = $alternativeCars->first();

                            return [
                                'reply_text' => $reply,
                                'media_url' => ($wantsMedia && $firstCar->main_photo) ? $firstCar->main_photo : null,
                                'media_type' => ($wantsMedia && $firstCar->main_photo) ? 'image' : null,
                                'tool_called' => 'suggestSameModelAlternativeYear',
                                'handover_required' => false,
                                'handover_reason' => null,
                                'interested_car_id' => $firstCar->id,
                            ];
                        }
                    }
                }

                // Not found or check availability for sold/booked units
                $searchQuery = $parsedFilters['model'] ?? ($parsedFilters['brand'] ?? 'yang dicari');
                $availability = $this->carSearchService->checkAvailability($searchQuery);

                if ($availability['sold_count'] > 0 && !$availability['has_available']) {
                    return [
                        'reply_text' => "Mohon maaf sekali {$customerName}, untuk unit **{$searchQuery}** saat ini statusnya sudah **TERJUAL**. 🙏 Namun kami ada beberapa rekomendasi unit sekelas yang masih ready di showroom. Mau saya carikan alternatifnya Kak?",
                        'media_url' => null,
                        'media_type' => null,
                        'tool_called' => 'checkAvailability',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => null,
                    ];
                }

                if ($availability['booked_count'] > 0 && !$availability['has_available']) {
                    return [
                        'reply_text' => "Untuk unit **{$searchQuery}** saat ini statusnya sedang **BOOKING** oleh customer lain Kak. Jika prosesnya batal, bisa kami prioritaskan untuk Kakak. Boleh saya hubungkan dengan admin showroom?",
                        'media_url' => null,
                        'media_type' => null,
                        'tool_called' => 'checkAvailability',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => null,
                    ];
                }

                // Suggest alternative ready units in stock
                $availableSuggestions = Car::where('stock_status', 'TERSEDIA')
                    ->orderBy('year', 'desc')
                    ->limit(3)
                    ->get();

                $suggestionText = "";
                if ($availableSuggestions->isNotEmpty()) {
                    $suggestionText = "\nNamun sebagai rekomendasi mobil sekelas yang masih READY dan siap pakai di Bintang Mobil Surabaya:\n";
                    foreach ($availableSuggestions as $sIdx => $sCar) {
                        $sNum = $sIdx + 1;
                        $suggestionText .= "{$sNum}. **{$sCar->full_name}** - {$sCar->formatted_price} ({$sCar->transmission})\n";
                    }
                }

                return [
                    'reply_text' => "Mohon maaf {$customerName}, untuk unit **{$searchQuery}** saat ini stok kami di showroom sedang kosong / belum ready. 🙏"
                        . $suggestionText
                        . "\nApakah ada unit di atas yang ingin Kakak tanyakan, atau mau kami hubungkan ke admin showroom untuk dibantu dicarikan unit {$searchQuery}? 😊",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'searchCars',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => null,
                ];
            }
        }

        // 5. Check Follow-up Questions on Currently Active Car (if customer already discussed a car)
        if ($currentCar) {
            // Follow-up: Price inquiry
            if (str_contains($textLower, 'harga') || str_contains($textLower, 'berapa') || str_contains($textLower, 'biaya') || str_contains($textLower, 'cash') || str_contains($textLower, 'kredit')) {
                return [
                    'reply_text' => "Halo {$customerName}! Untuk unit **{$currentCar->full_name}**, harga kami tawarkan di **{$currentCar->formatted_price}** ya Kak. 😊\n\nKami melayani pembayaran cash keras, kredit syariah/konvensional dengan DP fleksibel, maupun tukar tambah (trade-in). Mau dibantu simulasi cicilan atau nego harga langsung dengan admin showroom? 🚗",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getCarPrice',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCar->id,
                ];
            }

            // Follow-up: Tax & Document inquiry
            if (str_contains($textLower, 'pajak') || str_contains($textLower, 'stnk') || str_contains($textLower, 'bpkb') || str_contains($textLower, 'surat') || str_contains($textLower, 'dokumen') || str_contains($textLower, 'faktur')) {
                return [
                    'reply_text' => "Untuk kelengkapan surat unit **{$currentCar->full_name}** dijamin 100% aman dan resmi Kak:\n"
                        . "• STNK: {$currentCar->stnk_status} (Pajak hidup tertib)\n"
                        . "• BPKB: {$currentCar->bpkb_status} (Tangan pertama)\n"
                        . "• Faktur pembelian, sertifikat, & kunci serep lengkap.\n\n"
                        . "Dokumen siap cek fisik di Samsat kapan saja. Mau cek unit langsung di showroom Kak? 😊",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getCarDocuments',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCar->id,
                ];
            }

            // Follow-up: Condition & Quality inquiry
            if (str_contains($textLower, 'kondisi') || str_contains($textLower, 'minus') || str_contains($textLower, 'tabrak') || str_contains($textLower, 'banjir') || str_contains($textLower, 'mesin') || str_contains($textLower, 'lecet') || str_contains($textLower, 'rusak')) {
                return [
                    'reply_text' => "Kondisi unit **{$currentCar->full_name}** sangat prima dan istimewa Kak! 🌟\n"
                        . "• Mesin: Halus, kering, bertenaga, tanpa rembesan oli\n"
                        . "• Interior: Bersih, wangi, orisinil pabrik (non-smoker)\n"
                        . "• Kaki-kaki: Senyap dan nyaman dipakai jarak jauh\n"
                        . "• Garansi Showroom: 100% Bebas tabrak rangka & bebas rendaman banjir!\n\n"
                        . "Apakah Kakak ingin dikirimkan video walkthrough suara mesinnya, atau mau test drive hari ini? 😊",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getCarCondition',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCar->id,
                ];
            }

            // Follow-up: Transmission, Mileage, or Specs
            if (str_contains($textLower, 'matic') || str_contains($textLower, 'manual') || str_contains($textLower, 'km') || str_contains($textLower, 'kilometer') || str_contains($textLower, 'odometer') || str_contains($textLower, 'jarak')) {
                return [
                    'reply_text' => "Detail spesifikasi unit **{$currentCar->full_name}**:\n"
                        . "• Transmisi: {$currentCar->transmission}\n"
                        . "• Odometer: {$currentCar->formatted_mileage} (Kilometer asli service record resmi)\n"
                        . "• Bahan Bakar: {$currentCar->fuel_type}\n"
                        . "• Warna: {$currentCar->color}\n\n"
                        . "Unit sangat responsif dan siap pakai luar kota. Tertarik untuk cek langsung Kak? 😊",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getCarSpecs',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCar->id,
                ];
            }

            // Follow-up: Showroom Location
            if (str_contains($textLower, 'lokasi') || str_contains($textLower, 'alamat') || str_contains($textLower, 'dimana') || str_contains($textLower, 'showroom')) {
                return [
                    'reply_text' => "Unit **{$currentCar->full_name}** saat ini standby di **{$currentCar->location}** ya Kak. Showroom kami buka setiap hari pukul 08.00 - 20.00 WIB. Kakak berencana datang hari ini atau besok? Kami bisa siapkan slot dan kuncinya untuk test drive! 🚗",
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'getShowroomLocation',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $currentCar->id,
                ];
            }
        }

        // 6. Stock list / All Available Units inquiry
        if (str_contains($textLower, 'stok') || str_contains($textLower, 'ready apa') || str_contains($textLower, 'mobil apa aja') || str_contains($textLower, 'list mobil') || str_contains($textLower, 'pilihan')) {
            $readyCars = $this->carSearchService->getAllAvailableCars();
            if ($readyCars->isEmpty()) {
                $readyCars = Car::where('stock_status', 'TERSEDIA')->orderBy('year', 'desc')->get();
            }

            $reply = "Halo Kak {$customerName}! Berikut **DAFTAR LENGKAP** semua unit mobil yang **READY & SIAP PAKAI** di Showroom Bintang Mobil Surabaya saat ini: 🚗✨\n\n";
            foreach ($readyCars as $idx => $rCar) {
                $num = $idx + 1;
                $reply .= "{$num}. 🚗 **{$rCar->full_name}**\n"
                    . "   • Harga: **{$rCar->formatted_price}**\n"
                    . "   • Transmisi: {$rCar->transmission} | Warna: {$rCar->color}\n"
                    . "   • Odometer: {$rCar->formatted_mileage} (asli)\n"
                    . "   • Pajak: STNK {$rCar->stnk_status} (BPKB: {$rCar->bpkb_status} Ready)\n\n";
            }
            $reply .= "🌟 Semua unit di atas 100% bergaransi bebas tabrak & banjir. Ada tipe mobil atau budget tertentu yang ingin Kakak tanyakan lebih detail? 😊";

            return [
                'reply_text' => $reply,
                'media_url' => null,
                'media_type' => null,
                'tool_called' => 'listAllAvailableCars',
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $readyCars->first()?->id,
            ];
        }

        // 7. Friendly greeting
        if (str_contains($textLower, 'halo') || str_contains($textLower, 'pagi') || str_contains($textLower, 'siang') || str_contains($textLower, 'sore') || str_contains($textLower, 'malam') || str_contains($textLower, 'assalam')) {
            return [
                'reply_text' => "Halo {$customerName}! Selamat datang di Showroom Bintang Mobil Surabaya. 🚗 Kami menyediakan mobil bekas pilihan bergaransi resmi (Toyota Avanza, Innova, Honda Brio, Mobilio, Mitsubishi Xpander, Fortuner, dll). Ada tipe mobil atau budget tertentu yang sedang Kakak cari hari ini? 😊",
                'media_url' => null,
                'media_type' => null,
                'tool_called' => null,
                'handover_required' => false,
                'handover_reason' => null,
                'interested_car_id' => $currentCarId,
            ];
        }

        // 8. Default Fallback
        return [
            'reply_text' => "Terima kasih sudah menghubungi Bintang Mobil Surabaya! Ada yang bisa kami bantu mengenai cek stok mobil (Avanza, Mobilio, Xpander, Innova, dll), info harga & kredit, foto/video unit, atau pengiriman ke luar kota Kak? 😊",
            'media_url' => null,
            'media_type' => null,
            'tool_called' => null,
            'handover_required' => false,
            'handover_reason' => null,
            'interested_car_id' => $currentCarId,
        ];
    }

    /**
     * Parse and extract customer vehicle specifications and condition for Trade-In (TT) or Direct Sale.
     */
    public function parseCustomerTradeInCarDetails(string $text): ?array
    {
        $textLower = strtolower($text);

        $brands = [
            'toyota', 'daihatsu', 'honda', 'mitsubishi', 'suzuki',
            'nissan', 'wuling', 'hyundai', 'mazda', 'isuzu', 'ford', 'kia', 'chevrolet'
        ];

        $models = [
            'xenia', 'avanza', 'veloz', 'innova reborn', 'innova', 'brio', 'mobilio', 'agya', 'ayla',
            'calya', 'sigra', 'ertiga', 'xl7', 'xpander cross', 'xpander', 'pajero sport', 'pajero', 'fortuner', 'rush',
            'terios', 'hrv', 'hr-v', 'crv', 'cr-v', 'brv', 'br-v', 'yaris', 'jazz',
            'livina', 'grand livina', 'serena', 'baleno', 'ignis', 'karimun', 'raize',
            'rocky', 'stargazer', 'creta', 'confero', 'cortez', 'almaz', 'sienta', 'freed'
        ];

        $detectedModel = null;
        $detectedBrand = null;

        foreach ($models as $m) {
            if (preg_match('/\b' . preg_quote($m, '/') . '\b/i', $textLower)) {
                $detectedModel = ucwords($m);
                break;
            }
        }

        foreach ($brands as $b) {
            if (preg_match('/\b' . preg_quote($b, '/') . '\b/i', $textLower)) {
                $detectedBrand = ucfirst($b);
                break;
            }
        }

        // Infer brand if model known
        if ($detectedModel && !$detectedBrand) {
            $brandMap = [
                'Xenia' => 'Daihatsu', 'Ayla' => 'Daihatsu', 'Sigra' => 'Daihatsu', 'Terios' => 'Daihatsu', 'Rocky' => 'Daihatsu',
                'Avanza' => 'Toyota', 'Veloz' => 'Toyota', 'Innova' => 'Toyota', 'Innova Reborn' => 'Toyota', 'Agya' => 'Toyota', 'Calya' => 'Toyota', 'Rush' => 'Toyota', 'Raize' => 'Toyota', 'Yaris' => 'Toyota', 'Fortuner' => 'Toyota', 'Sienta' => 'Toyota',
                'Brio' => 'Honda', 'Mobilio' => 'Honda', 'Hrv' => 'Honda', 'Hr-v' => 'Honda', 'Crv' => 'Honda', 'Cr-v' => 'Honda', 'Brv' => 'Honda', 'Br-v' => 'Honda', 'Jazz' => 'Honda', 'Freed' => 'Honda',
                'Ertiga' => 'Suzuki', 'Xl7' => 'Suzuki', 'Baleno' => 'Suzuki', 'Ignis' => 'Suzuki', 'Karimun' => 'Suzuki',
                'Xpander' => 'Mitsubishi', 'Xpander Cross' => 'Mitsubishi', 'Pajero' => 'Mitsubishi', 'Pajero Sport' => 'Mitsubishi',
                'Livina' => 'Nissan', 'Grand Livina' => 'Nissan', 'Serena' => 'Nissan',
                'Stargazer' => 'Hyundai', 'Creta' => 'Hyundai',
                'Confero' => 'Wuling', 'Cortez' => 'Wuling', 'Almaz' => 'Wuling',
            ];
            $detectedBrand = $brandMap[$detectedModel] ?? 'Unit';
        }

        // Year detection (e.g. 2012 - 2026, or "th 17", "thn 2018")
        $detectedYear = null;
        if (preg_match('/\b(201[0-9]|202[0-6])\b/', $textLower, $ym)) {
            $detectedYear = $ym[1];
        } elseif (preg_match('/\b(?:th|thn|tahun)\s*(\d{2,4})\b/i', $textLower, $ym)) {
            $detectedYear = strlen($ym[1]) === 2 ? ('20' . $ym[1]) : $ym[1];
        }

        // Transmission detection (supports manual, mt, m/t, matic, at, a/t, cvt)
        $detectedTrans = null;
        if (preg_match('/\b(matic|matoc|metiq|at|a\/t|cvt)\b/i', $textLower)) {
            $detectedTrans = 'Matic (Otomatis)';
        } elseif (preg_match('/\b(manual|mt|m\/t|mnl)\b/i', $textLower)) {
            $detectedTrans = 'Manual';
        }

        // Mileage / KM detection (e.g. "km 45rb", "km 60.000", "50rb km", "km rendah")
        $detectedKm = null;
        if (preg_match('/(?:km|odo|odometer|jarak)\s*[:=]?\s*(\d+[\.,]?\d*)\s*(?:rb|ribu|k)?/i', $textLower, $kmm)) {
            $hasThousand = str_contains($textLower, 'rb') || str_contains($textLower, 'ribu') || str_contains($textLower, 'k');
            $detectedKm = $kmm[1] . ($hasThousand ? '.000 KM' : ' KM');
        } elseif (preg_match('/\b(\d+)\s*(?:rb|ribu|k)\s*km\b/i', $textLower, $kmm)) {
            $detectedKm = $kmm[1] . '.000 KM';
        } elseif (preg_match('/\b(km rendah|odo rendah|kilometer rendah)\b/i', $textLower)) {
            $detectedKm = 'Kilometer Rendah';
        }

        // Plate / City (e.g. Plat L, Plat W, Plat B, Surabaya, Sidoarjo, dll.)
        $detectedPlate = null;
        if (preg_match('/\bplat\s*([a-z]{1,2})\b/i', $textLower, $pm)) {
            $code = strtoupper($pm[1]);
            $cityMap = ['L' => 'Surabaya', 'W' => 'Sidoarjo/Gresik', 'B' => 'Jakarta/Bekasi/Depok', 'N' => 'Malang', 'AG' => 'Kediri/Blitar', 'AE' => 'Madiun', 'D' => 'Bandung'];
            $detectedPlate = "Plat {$code}" . (isset($cityMap[$code]) ? " ({$cityMap[$code]})" : '');
        } elseif (str_contains($textLower, 'surabaya')) {
            $detectedPlate = 'Plat L (Surabaya)';
        } elseif (str_contains($textLower, 'sidoarjo') || str_contains($textLower, 'gresik')) {
            $detectedPlate = 'Plat W (Sidoarjo/Gresik)';
        }

        // Tax / Pajak (e.g. pajak hidup, pajak on, pajak jalan, pajak panjang, pajak off, pjk hidup, pjk on)
        $detectedTax = null;
        if (preg_match('/\b(?:pajak|pjk)\s*(hidup|on|jalan|panjang|aktif)\b/i', $textLower) || str_contains($textLower, 'pajak hidup') || str_contains($textLower, 'pjk on') || str_contains($textLower, 'pajak on') || str_contains($textLower, 'pajak panjang')) {
            $detectedTax = 'Pajak Hidup / Panjang (STNK Aktif)';
        } elseif (preg_match('/\b(?:pajak|pjk)\s*(mati|off|tewas|lewat)\s*(\d+)?/i', $textLower, $tm)) {
            $detectedTax = 'Pajak Off/Mati' . (!empty($tm[2]) ? " ({$tm[2]}x)" : '') . ' (Siap dibantu showroom)';
        }

        // Condition indicators
        $conditions = [];
        if (preg_match('/\b(mulus|mls|kaleng|cat ori)\b/i', $textLower)) $conditions[] = 'Body Mulus Kaleng';
        if (preg_match('/\b(no pr|tanpa pr|tidak ada pr|siap gass|siap gas|siap pakai)\b/i', $textLower)) $conditions[] = 'Tanpa PR / Siap Pakai';
        if (preg_match('/\b(ori|orisinil|full ori|asli)\b/i', $textLower)) $conditions[] = 'Kondisi Orisinil';
        if (preg_match('/\b(terawat|istimewa|service record)\b/i', $textLower)) $conditions[] = 'Sangat Terawat';
        if (preg_match('/\b(tangan 1|tangan pertama|tgn 1|tgn pertama|pemilik pertama|an sendiri|a\/n sendiri)\b/i', $textLower)) $conditions[] = 'Tangan Pertama / A.N Sendiri';
        if (preg_match('/\b(ss lengkap|ss komplit|surat lengkap|surat komplit|bpkb ready|stnk bpkb lengkap)\b/i', $textLower)) $conditions[] = 'Surat-Surat (SS) Lengkap & Sah';

        // Check whether this text represents a customer offering their car
        $hasCustomerCarSignal = str_contains($textLower, 'mobil saya') ||
            str_contains($textLower, 'mobilku') ||
            str_contains($textLower, 'punya saya') ||
            str_contains($textLower, 'punyaku') ||
            str_contains($textLower, 'ada mobil') ||
            str_contains($textLower, 'saya ada') ||
            str_contains($textLower, 'saya punya') ||
            str_contains($textLower, 'unit saya') ||
            str_contains($textLower, 'spek mobil') ||
            str_contains($textLower, 'kondisi mobil') ||
            str_contains($textLower, 'tt sama') ||
            str_contains($textLower, 'tt sm') ||
            str_contains($textLower, 'tt dengan') ||
            str_contains($textLower, 'mau tt') ||
            str_contains($textLower, 'tukar sama') ||
            str_contains($textLower, 'tukar dengan') ||
            str_contains($textLower, 'tukar tambah sama') ||
            str_contains($textLower, 'tukar tambah dengan');

        // Or has Model + Year + at least one spec/condition detail
        $hasCarIdentity = $detectedModel && ($detectedYear || $detectedTrans);
        $hasSpecsOrCondition = (!empty($detectedKm) || !empty($detectedTax) || !empty($detectedPlate) || !empty($conditions));

        if (($hasCustomerCarSignal && $detectedModel) || ($hasCarIdentity && $hasSpecsOrCondition)) {
            return [
                'brand' => $detectedBrand ?: 'Unit',
                'model' => $detectedModel,
                'year' => $detectedYear,
                'transmission' => $detectedTrans,
                'mileage' => $detectedKm,
                'plate' => $detectedPlate,
                'tax' => $detectedTax,
                'conditions' => $conditions,
            ];
        }

        return null;
    }

    /**
     * Check if customer is asking for the complete list of ready / available cars.
     */
    public function isAllReadyCarsInquiry(string $textLower): bool
    {
        $phrases = [
            'mobil apa saja yg ready',
            'mobil apa saja yang ready',
            'mobil apa aja yg ready',
            'mobil apa aja yang ready',
            'mobil apa yg ready',
            'mobil apa yang ready',
            'mobil yang ready apa',
            'mobil yg ready apa',
            'mobil ready apa saja',
            'mobil ready apa aja',
            'unit apa saja yg ready',
            'unit apa saja yang ready',
            'unit apa aja yg ready',
            'unit apa aja yang ready',
            'unit yang ready apa',
            'unit yg ready apa',
            'unit ready apa saja',
            'unit ready apa aja',
            'stok apa saja yg ready',
            'stok apa saja yang ready',
            'stok apa aja yg ready',
            'stok apa aja yang ready',
            'stok yang ready apa saja',
            'stok yang ready apa aja',
            'stok yg ready apa saja',
            'stok yg ready apa aja',
            'stok ready apa saja',
            'stok ready apa aja',
            'ada mobil apa saja',
            'ada mobil apa aja',
            'ada mobil apa',
            'ada unit apa saja',
            'ada unit apa aja',
            'ada unit apa',
            'ada stok apa saja',
            'ada stok apa aja',
            'ada stok apa',
            'tampilkan semua list',
            'tampilkan semua listnya',
            'tampilkan listnya',
            'tampilkan semua mobil',
            'tampilkan list mobil',
            'tampilkan semua unit',
            'tampilkan daftar mobil',
            'list semua mobil',
            'list mobil ready',
            'list mobil yang ready',
            'list mobil yg ready',
            'list unit ready',
            'daftar mobil ready',
            'daftar mobil yang ready',
            'daftar mobil yg ready',
            'daftar stok mobil',
            'semua mobil ready',
            'semua unit ready',
            'semua list mobil',
            'semua listnya',
            'spill mobil yang ready',
            'spill mobil ready',
            'spill stok',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($textLower, $phrase)) {
                return true;
            }
        }

        $hasNoun = str_contains($textLower, 'mobil') || str_contains($textLower, 'unit') || str_contains($textLower, 'stok');
        $hasReady = str_contains($textLower, 'ready') || str_contains($textLower, 'tersedia') || str_contains($textLower, 'standby');
        $hasListOrQuery = str_contains($textLower, 'apa') || str_contains($textLower, 'semua') || str_contains($textLower, 'list') || str_contains($textLower, 'daftar');

        $parsed = $this->carSearchService->parseQueryFromText($textLower);
        $hasSpecificModelOrBrand = !empty($parsed['model']) || !empty($parsed['brand']);

        if ($hasNoun && $hasReady && $hasListOrQuery && !$hasSpecificModelOrBrand) {
            return true;
        }

        return false;
    }

    /**
     * Check if customer is asking about car colors.
     */
    public function isColorInquiry(string $textLower): bool
    {
        $phrases = [
            'warna apa saja yang ready',
            'warna apa saja yg ready',
            'warna apa aja yang ready',
            'warna apa aja yg ready',
            'warna apa yg ready',
            'warna apa yang ready',
            'warna apa saja yang ada',
            'warna apa saja yg ada',
            'warna apa aja yang ada',
            'warna apa aja yg ada',
            'warna apa yang ada',
            'warna apa yg ada',
            'warna apa saja ready',
            'warna apa aja ready',
            'warna apa ready',
            'warna apa saja',
            'warna apa aja',
            'warna apa',
            'ada warna apa saja',
            'ada warna apa aja',
            'ada warna apa',
            'ready warna apa saja',
            'ready warna apa aja',
            'ready warna apa',
            'tersedia warna apa saja',
            'tersedia warna apa aja',
            'tersedia warna apa',
            'pilihan warna apa saja',
            'pilihan warna apa aja',
            'pilihan warna apa',
            'pilihan warnanya apa',
            'pilihan warnanya apa saja',
            'pilihan warnanya apa aja',
            'opsi warna apa saja',
            'opsi warna apa aja',
            'opsi warna',
            'warnanya apa saja',
            'warnanya apa aja',
            'warnanya apa',
            'warna mobilnya apa',
            'warna unitnya apa',
            'warna mobil apa saja yang ready',
            'warna mobil apa aja yang ready',
            'warna mobil apa yang ready',
            'warna unit apa saja yang ready',
            'warna unit apa aja yang ready',
            'stok warna apa',
            'stok warna apa saja',
            'stok warna apa aja',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($textLower, $phrase)) {
                return true;
            }
        }

        if (preg_match('/\bwarna\b.*\b(ready|ada|tersedia|stok|pilihan|opsi|apa)\b/i', $textLower)) {
            return true;
        }

        if (preg_match('/\b(ready|ada|tersedia|stok|pilihan|opsi)\b.*\bwarna\b/i', $textLower)) {
            return true;
        }

        if (preg_match('/\b(?:ada|ready|tersedia)\s*warna\s*(putih|hitam|silver|abu|grey|gray|merah|kuning|coklat|orange|biru)\b/i', $textLower) ||
            preg_match('/\bwarna\s*(putih|hitam|silver|abu|grey|gray|merah|kuning|coklat|orange|biru)\s*(?:ada|ready|tersedia|gak|nggak|kah|bisa)\b/i', $textLower)) {
            return true;
        }

        return false;
    }

    /**
     * Extract specific color name mentioned in query.
     */
    public function extractSpecificColor(string $textLower): ?string
    {
        $colors = [
            'putih mutiara' => 'Putih Mutiara',
            'putih' => 'Putih',
            'hitam metalik' => 'Hitam Metalik',
            'hitam mika' => 'Hitam Mika',
            'hitam' => 'Hitam',
            'silver metalik' => 'Silver Metalik',
            'silver' => 'Silver',
            'abu-abu meteoroid gray' => 'Abu-abu Meteoroid Gray',
            'abu-abu metalik' => 'Abu-abu Metalik',
            'abu-abu' => 'Abu-abu',
            'abu abu' => 'Abu-abu',
            'abu' => 'Abu-abu',
            'grey' => 'Abu-abu',
            'gray' => 'Abu-abu',
            'kuning carnival' => 'Kuning Carnival',
            'kuning' => 'Kuning',
            'merah two-tone hitam' => 'Merah Two-Tone Hitam',
            'merah rallye' => 'Merah',
            'merah' => 'Merah',
            'orange two-tone hitam' => 'Orange Two-Tone Hitam',
            'orange' => 'Orange',
            'oranye' => 'Orange',
            'coklat metalik' => 'Coklat Metalik',
            'coklat' => 'Coklat',
            'biru' => 'Biru',
        ];

        foreach ($colors as $key => $colorName) {
            if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $textLower)) {
                return $colorName;
            }
        }

        return null;
    }

    /**
     * Handle color inquiries synchronized with previously discussed or currently mentioned car.
     */
    public function handleColorInquiry(string $textLower, array $context, string $customerName, ?Car $currentCar): array
    {
        // 1. Check if customer explicitly mentions a specific car model/brand in this message
        $parsedFilters = $this->carSearchService->parseQueryFromText($textLower);
        $targetCar = null;

        if (!empty($parsedFilters['model']) || !empty($parsedFilters['brand'])) {
            $carResults = $this->carSearchService->search($parsedFilters);
            $targetCar = $carResults->first();
        } elseif ($currentCar) {
            // SINKRONISASI DENGAN MOBIL YANG DITANYAKAN SEBELUMNYA!
            $targetCar = $currentCar;
        }

        // 2. Check if a specific color name was asked (e.g. "ada warna hitam?")
        $requestedColor = $this->extractSpecificColor($textLower);

        // 3. CASE A: Target car IS IDENTIFIED (either from previous question or current question)
        if ($targetCar) {
            // Find all available units for this model or brand in showroom stock
            $matchingCars = Car::available()
                ->where(function ($q) use ($targetCar) {
                    $q->where('model', 'LIKE', "%{$targetCar->model}%")
                      ->orWhere('variant', 'LIKE', "%{$targetCar->model}%");
                })
                ->orderBy('year', 'desc')
                ->get();

            if ($matchingCars->isEmpty()) {
                $matchingCars = collect([$targetCar]);
            }

            // Case A.1: Customer asked for a SPECIFIC COLOR (e.g. "ada warna putih?")
            if ($requestedColor) {
                $colorMatch = $matchingCars->first(function ($c) use ($requestedColor) {
                    return stripos($c->color, $requestedColor) !== false;
                });

                if ($colorMatch) {
                    $reply = "Ada banget Kak {$customerName}! 🚗✨ Untuk unit **{$colorMatch->full_name}** yang ready di showroom kami warnanya memang **{$colorMatch->color}**!\n\n"
                        . "• Kondisi Cat: Cat orisinil mulus kaleng, terawat, dan kinclong (bebas baret berat)\n"
                        . "• Harga: **{$colorMatch->formatted_price}** (Cash / Kredit / Tukar Tambah)\n"
                        . "• Transmisi: {$colorMatch->transmission} | KM: {$colorMatch->formatted_mileage} (asli)\n"
                        . "• Pajak: STNK {$colorMatch->stnk_status} (BPKB: {$colorMatch->bpkb_status} Lengkap & Sah)\n\n"
                        . "Unit warna **{$colorMatch->color}** ini standby di showroom dan siap pakai luar kota. Apakah Kakak ingin saya kirimkan foto detail eksterior & interiornya langsung di WhatsApp sekarang? 😊";

                    return [
                        'reply_text' => $reply,
                        'media_url' => $colorMatch->main_photo,
                        'media_type' => $colorMatch->main_photo ? 'image' : null,
                        'tool_called' => 'getCarColorAvailability',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => $colorMatch->id,
                    ];
                } else {
                    $readyColorList = $matchingCars->map(fn($c) => "• **{$c->color}** ({$c->full_name} - {$c->formatted_price})")->implode("\n");

                    $reply = "Untuk **{$targetCar->brand} {$targetCar->model}** warna **{$requestedColor}** saat ini kebetulan **belum ready** ya Kak {$customerName}.\n\n"
                        . "Namun unit **{$targetCar->model}** yang **READY & SIAP PAKAI** di Showroom Bintang Mobil Surabaya saat ini ada pilihan warna:\n"
                        . $readyColorList . "\n\n"
                        . "Semua unit di atas kondisi catnya sangat mulus orisinil dan terawat. Apakah Kakak berminat cek unit warna yang ready ini, atau mau kami bantu hubungkan ke admin showroom untuk dicarikan unit warna {$requestedColor}? 😊";

                    return [
                        'reply_text' => $reply,
                        'media_url' => $matchingCars->first()->main_photo,
                        'media_type' => $matchingCars->first()->main_photo ? 'image' : null,
                        'tool_called' => 'getCarColorAvailability',
                        'handover_required' => false,
                        'handover_reason' => null,
                        'interested_car_id' => $matchingCars->first()->id,
                    ];
                }
            }

            // Case A.2: Customer asks general color question for this car: "warna apa saja yang ready?", "warna apa yang ada?", "warnanya apa?"
            if ($matchingCars->count() === 1) {
                $singleCar = $matchingCars->first();
                $reply = "Halo Kak {$customerName}! Untuk unit **{$singleCar->full_name}** yang Kakak tanyakan, pilihan warna yang **READY & SIAP PAKAI** di Showroom Bintang Mobil saat ini adalah:\n\n"
                    . "🎨 **Warna: {$singleCar->color}**\n"
                    . "• Kondisi Cat: Cat orisinil mulus kaleng, terawat, dan kinclong istimewa\n"
                    . "• Spesifikasi: {$singleCar->transmission} | Odometer {$singleCar->formatted_mileage} (asli service record)\n"
                    . "• Harga: **{$singleCar->formatted_price}** (Cash / Kredit DP ringan / Tukar Tambah)\n"
                    . "• Pajak: STNK {$singleCar->stnk_status} (BPKB {$singleCar->bpkb_status} Ready)\n\n"
                    . "Unit warna **{$singleCar->color}** ini 100% bebas tabrak & bebas banjir, siap jalan jauh. Apakah Kakak memang menyukai warna **{$singleCar->color}** ini, atau sedang mencari warna lain? Mau saya kirimkan foto detail unitnya Kak? 😊";

                return [
                    'reply_text' => $reply,
                    'media_url' => $singleCar->main_photo,
                    'media_type' => $singleCar->main_photo ? 'image' : null,
                    'tool_called' => 'getCarColorAvailability',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $singleCar->id,
                ];
            } else {
                $reply = "Halo Kak {$customerName}! Untuk unit **{$targetCar->brand} {$targetCar->model}** yang Kakak tanyakan, pilihan warna yang **READY & SIAP PAKAI** di Showroom Bintang Mobil Surabaya saat ini:\n\n";

                foreach ($matchingCars as $idx => $mCar) {
                    $num = $idx + 1;
                    $reply .= "{$num}. 🎨 **Warna: {$mCar->color}**\n"
                        . "   • Unit: **{$mCar->full_name}**\n"
                        . "   • Harga: **{$mCar->formatted_price}** | Transmisi: {$mCar->transmission}\n"
                        . "   • Odometer: {$mCar->formatted_mileage} | Pajak STNK: {$mCar->stnk_status}\n\n";
                }

                $reply .= "Semua unit di atas catnya mulus orisinil dan 100% bergaransi bebas banjir & tabrak.\n\n"
                    . "Dari pilihan warna di atas, Kakak paling berminat dengan warna yang mana? Bisa kami kirimkan foto detail unitnya sekarang Kak! 😊";

                return [
                    'reply_text' => $reply,
                    'media_url' => $matchingCars->first()->main_photo,
                    'media_type' => $matchingCars->first()->main_photo ? 'image' : null,
                    'tool_called' => 'getCarColorAvailability',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $matchingCars->first()->id,
                ];
            }
        }

        // 4. CASE B: NO TARGET CAR IDENTIFIED (No car discussed previously and no car in message)
        $allReady = Car::available()->orderBy('year', 'desc')->get();

        $colorGroups = [
            'Hitam' => [],
            'Putih' => [],
            'Silver / Abu-abu' => [],
            'Warna Favorit Lainnya' => [],
        ];

        foreach ($allReady as $car) {
            $cLower = strtolower($car->color);
            if (str_contains($cLower, 'hitam')) {
                $colorGroups['Hitam'][] = "{$car->brand} {$car->model} ({$car->year})";
            } elseif (str_contains($cLower, 'putih')) {
                $colorGroups['Putih'][] = "{$car->brand} {$car->model} ({$car->year})";
            } elseif (str_contains($cLower, 'silver') || str_contains($cLower, 'abu') || str_contains($cLower, 'gray') || str_contains($cLower, 'grey')) {
                $colorGroups['Silver / Abu-abu'][] = "{$car->brand} {$car->model} ({$car->year})";
            } else {
                $colorGroups['Warna Favorit Lainnya'][] = "{$car->brand} {$car->model} ({$car->year} - {$car->color})";
            }
        }

        $reply = "Halo Kak {$customerName}! Di Showroom Bintang Mobil Surabaya saat ini unit yang **READY & SIAP PAKAI** tersedia dalam berbagai pilihan warna favorit: 🚗✨\n\n";

        if (!empty($colorGroups['Putih'])) {
            $reply .= "⚪ **Pilihan Warna Putih / Putih Mutiara**:\n• " . implode("\n• ", array_slice($colorGroups['Putih'], 0, 4)) . "\n\n";
        }
        if (!empty($colorGroups['Hitam'])) {
            $reply .= "⚫ **Pilihan Warna Hitam Metalik / Mika**:\n• " . implode("\n• ", array_slice($colorGroups['Hitam'], 0, 4)) . "\n\n";
        }
        if (!empty($colorGroups['Silver / Abu-abu'])) {
            $reply .= "🔘 **Pilihan Warna Silver / Abu-abu Metalik**:\n• " . implode("\n• ", array_slice($colorGroups['Silver / Abu-abu'], 0, 4)) . "\n\n";
        }
        if (!empty($colorGroups['Warna Favorit Lainnya'])) {
            $reply .= "🟡 **Pilihan Warna Spesial (Kuning/Merah/Orange/Coklat)**:\n• " . implode("\n• ", array_slice($colorGroups['Warna Favorit Lainnya'], 0, 4)) . "\n\n";
        }

        $reply .= "Boleh kami tahu Kak, kira-kira Kakak sedang mencari **model atau tipe mobil apa** (misalnya Avanza, Innova, Brio, Xpander, Fortuner, atau Pajero Sport)? Agar kami bisa infokan ketersediaan warna yang ready khusus untuk unit yang Kakak minati! 😊";

        return [
            'reply_text' => $reply,
            'media_url' => null,
            'media_type' => null,
            'tool_called' => 'getAvailableColorsOverview',
            'handover_required' => false,
            'handover_reason' => null,
            'interested_car_id' => null,
        ];
    }
}
