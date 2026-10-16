<?php

$dir = __DIR__ . '/../../public/demo-cars';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$cars = [
    'avanza' => ['name' => 'Toyota Avanza 1.5 G TSS CVT', 'color' => '#1e293b', 'badge' => 'PUTIH MUTIARA - 2022', 'price' => 'Rp 218.000.000'],
    'innova' => ['name' => 'Toyota Innova Reborn 2.4 V AT', 'color' => '#0f172a', 'badge' => 'HITAM METALIK - DIESEL 2021', 'price' => 'Rp 355.000.000'],
    'brio' => ['name' => 'Honda Brio 1.2 RS CVT Facelift', 'color' => '#854d0e', 'badge' => 'KUNING CARNIVAL - 2022', 'price' => 'Rp 188.000.000'],
    'hrv' => ['name' => 'All New Honda HR-V 1.5 SE CVT', 'color' => '#334155', 'badge' => 'METEOROID GRAY - 2022', 'price' => 'Rp 368.000.000'],
    'xpander' => ['name' => 'Mitsubishi Xpander Ultimate CVT', 'color' => '#475569', 'badge' => 'SILVER METALIK - 2022', 'price' => 'Rp 262.000.000'],
    'mobilio' => ['name' => 'Honda Mobilio 1.5 E CVT Facelift', 'color' => '#1e3a8a', 'badge' => 'MODERN STEEL - 2019', 'price' => 'Rp 175.000.000'],
    'fortuner' => ['name' => 'Toyota Fortuner 2.8 GR Sport 4x2', 'color' => '#111827', 'badge' => 'HITAM METALIK - DIESEL 2022', 'price' => 'Rp 535.000.000'],
    'interior' => ['name' => 'Interior & Dashboard Bergaransi', 'color' => '#18181b', 'badge' => 'KONDISI MULUS TERAWAT - WANGI', 'price' => '100% BEBAS BANJIR'],
    'engine' => ['name' => 'Ruang Mesin & Sasis Standar Pabrik', 'color' => '#1c1917', 'badge' => '100% BEBAS TABRAKAN - NO REMBES', 'price' => 'GARANSI RESMI SHOWROOM'],
    'odometer' => ['name' => 'Speedometer & Odometer Asli', 'color' => '#0c4a6e', 'badge' => 'SERVICE RECORD RESMI TERVERIFIKASI', 'price' => 'BUKAN PUTARAN'],
    'car-default' => ['name' => 'Showroom Mobil Bekas Bintang Mobil', 'color' => '#1e1b4b', 'badge' => 'UNIT PILIHAN BERKUALITAS', 'price' => 'SURABAYA']
];

foreach ($cars as $slug => $info) {
    $width = 800;
    $height = 600;
    $img = imagecreatetruecolor($width, $height);

    // Parse base color
    $hex = ltrim($info['color'], '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Gradient background
    for ($y = 0; $y < $height; $y++) {
        $factor = 1.0 - ($y / $height) * 0.45;
        $color = imagecolorallocate($img, (int)($r * $factor), (int)($g * $factor), (int)($b * $factor));
        imageline($img, 0, $y, $width, $y, $color);
    }

    // Border and accent grid
    $white = imagecolorallocate($img, 255, 255, 255);
    $cyan = imagecolorallocate($img, 56, 189, 248);
    $gold = imagecolorallocate($img, 250, 204, 21);
    $emerald = imagecolorallocate($img, 52, 211, 153);
    $darkBorder = imagecolorallocate($img, 51, 65, 85);

    // Header bar
    imagefilledrectangle($img, 0, 0, $width, 70, imagecolorallocate($img, 15, 23, 42));
    imagestring($img, 5, 30, 25, "BINTANG MOBIL SURABAYA - OFFICIAL SHOWROOM CERTIFIED", $cyan);

    // Car silhouette / container box
    imagefilledrectangle($img, 50, 100, $width - 50, 420, imagecolorallocate($img, 30, 41, 59));
    imagerectangle($img, 50, 100, $width - 50, 420, $cyan);

    // Draw stylized car graphic in box
    // Car roof & body outline
    $carBlue = imagecolorallocate($img, 96, 165, 250);
    imagefilledellipse($img, 400, 260, 480, 180, imagecolorallocate($img, 15, 23, 42));
    imagefilledrectangle($img, 160, 240, 640, 310, $carBlue);
    
    // Windows
    imagefilledrectangle($img, 260, 180, 520, 240, imagecolorallocate($img, 186, 230, 253));

    // Wheels
    imagefilledellipse($img, 260, 320, 90, 90, imagecolorallocate($img, 15, 23, 42));
    imagefilledellipse($img, 540, 320, 90, 90, imagecolorallocate($img, 15, 23, 42));
    imagefilledellipse($img, 260, 320, 45, 45, imagecolorallocate($img, 148, 163, 184));
    imagefilledellipse($img, 540, 320, 45, 45, imagecolorallocate($img, 148, 163, 184));

    // Badge over photo
    imagefilledrectangle($img, 70, 120, 450, 160, imagecolorallocate($img, 15, 23, 42));
    imagerectangle($img, 70, 120, 450, 160, $gold);
    imagestring($img, 4, 85, 132, $info['badge'], $gold);

    // Bottom info box
    imagefilledrectangle($img, 50, 440, $width - 50, 560, imagecolorallocate($img, 15, 23, 42));
    imagerectangle($img, 50, 440, $width - 50, 560, $darkBorder);

    // Car title
    imagestring($img, 5, 70, 460, strtoupper($info['name']), $white);

    // Price tag
    imagestring($img, 5, 70, 495, "HARGA SHOWROOM : " . $info['price'], $emerald);
    imagestring($img, 4, 70, 525, "GARANSI 100% BEBAS BANJIR & TABRAK | SURAT-SURAT LENGKAP & SAH", $cyan);

    // Save as JPEG
    $filePathJpg = "{$dir}/{$slug}.jpg";
    $filePathPng = "{$dir}/{$slug}.png";
    imagejpeg($img, $filePathJpg, 92);
    imagepng($img, $filePathPng);
    imagedestroy($img);
    echo "Saved: {$filePathJpg} & {$filePathPng}\n";
}

// Also overwrite car-placeholder.svg or create companion car-placeholder.jpg
copy("{$dir}/car-default.jpg", "{$dir}/car-placeholder.jpg");
copy("{$dir}/interior.jpg", "{$dir}/car-interior.jpg");
copy("{$dir}/car-default.jpg", "{$dir}/car-video.jpg");
echo "Done!\n";
