<?php

return [
    'title' => 'Master Pemasok',

    'table' => [
        'name'      => 'Nama Pemasok',
        'status'    => 'Status',
        'actions'   => 'Aksi',
        'active'    => 'aktif',
        'inactive'  => 'nonaktif',
        'empty'     => 'Belum ada pemasok yang cocok.',
        'add'       => 'Tambah Pemasok',
        'edit'      => 'Ubah',
    ],

    'form' => [
        'create_title' => 'Tambah Pemasok',
        'edit_title'   => 'Ubah Pemasok',
        'name'         => 'Nama Pemasok',
        'is_active'    => 'Status',
        'active_hint'  => 'Pemasok nonaktif tidak muncul di pilihan form penerimaan, tetapi tetap tampil di daftar ini.',
        'save'         => 'Simpan',
        'cancel'       => 'Batal',
    ],

    'filter' => [
        'search'             => 'Cari',
        'search_placeholder' => 'Nama pemasok',
        'status'             => 'Status',
        'status_all'         => 'Semua status',
        'status_active'      => 'Hanya aktif',
        'status_inactive'    => 'Hanya nonaktif',
        'show'               => 'Tampilkan',
    ],

    'api' => [
        'not_found'      => 'Pemasok tidak ditemukan.',
        'created'        => 'Pemasok dibuat.',
        'updated'        => 'Pemasok diperbarui.',
        'failed'         => 'Gagal.',
        'store_failed'   => 'Gagal menyimpan pemasok: {0}',
        'update_failed'  => 'Gagal memperbarui pemasok: {0}',
        'forbidden'      => 'Anda tidak berhak mengubah master pemasok.',
        'invalid_status' => 'status harus salah satu dari all, active, inactive.',
    ],

    'validation' => [
        'name_required'     => 'name wajib diisi.',
        'name_too_long'     => 'name maksimal 150 karakter.',
        'name_taken'        => 'name {0} sudah dipakai pemasok lain.',
        'is_active_invalid' => 'is_active harus bernilai true atau false.',
    ],
];
