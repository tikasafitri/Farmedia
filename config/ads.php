<?php

return [
    // Harga per placement & durasi (IDR)
    'packages' => [
        'home' => [
            7  => 25000,
            14 => 45000,
            30 => 90000,
        ],
        'category' => [
            7  => 15000,
            14 => 27000,
            30 => 54000,
        ],
    ],

    // Info rekening tujuan transfer (tampilan di halaman mitra)
    'bank' => [
        'nama'   => env('PLATFORM_BANK_NAMA', 'RIAU KEPRI SYARIAH (PERSERODA)'),
        'nomor'  => env('PLATFORM_BANK_NOMOR', '1083 1011 46'),
        'pemilik'=> env('PLATFORM_BANK_PEMILIK', 'FARMEDIA'),
    ],
];
