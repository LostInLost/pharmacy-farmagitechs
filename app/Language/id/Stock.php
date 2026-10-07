<?php

return [
    'title' => 'Laporan Stok',

    'filter' => [
        'on_date'  => 'Tanggal pemeriksaan kedaluwarsa',
        'status'   => 'Status batch',
        'status_all'       => 'Semua status',
        'status_available' => 'Hanya tersedia',
        'status_expired'   => 'Hanya kedaluwarsa',
        'show'     => 'Tampilkan',
        'hint'     => 'Jumlah stok selalu dihitung dari seluruh transaksi tersimpan; tanggal hanya menentukan status kedaluwarsa.',
    ],

    'table' => [
        'code'      => 'Kode',
        'medicine'  => 'Obat',
        'unit'      => 'Satuan',
        'physical'  => 'Fisik',
        'available' => 'Tersedia',
        'expired'   => 'Kedaluwarsa',
        'batches'   => 'Batch',
        'batch_no'  => 'Batch',
        'expires_on' => 'Kedaluwarsa',
        'quantity'  => 'Jumlah',
        'status'    => 'Status',
        'no_batch'  => 'belum ada batch',
        'batch_count' => '{0} batch',
        'status_available' => 'tersedia',
        'status_expired'   => 'kedaluwarsa',
    ],

    'api' => [
        'invalid_date' => 'on_date harus berformat YYYY-MM-DD.',
    ],

    'js' => [
        'load_failed'      => 'Gagal memuat laporan stok.',
        'contact_failed'   => 'Tidak dapat menghubungi server.',
        'no_batch'         => 'belum ada batch',
        'batch_count'      => '{0} batch',
        'status_available' => 'tersedia',
        'status_expired'   => 'kedaluwarsa',
        'filter_available' => 'Tidak ada batch tersedia pada tanggal ini.',
        'filter_expired'   => 'Tidak ada batch kedaluwarsa pada tanggal ini.',
        'filter_empty'     => 'Tidak ada obat yang cocok dengan status ini.',
    ],
];
