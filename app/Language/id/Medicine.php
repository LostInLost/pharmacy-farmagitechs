<?php

return [
    'title' => 'Master Obat',

    'table' => [
        'code'      => 'Kode',
        'name'      => 'Nama Obat',
        'unit'      => 'Satuan',
        'status'    => 'Status',
        'actions'   => 'Aksi',
        'active'    => 'aktif',
        'inactive'  => 'nonaktif',
        'empty'     => 'Belum ada obat yang cocok.',
        'add'       => 'Tambah Obat',
        'edit'      => 'Ubah',
    ],

    'form' => [
        'create_title' => 'Tambah Obat',
        'edit_title'   => 'Ubah Obat',
        'code'         => 'Kode',
        'name'         => 'Nama Obat',
        'unit'         => 'Satuan',
        'is_active'    => 'Status',
        'active_hint'  => 'Obat nonaktif tidak muncul di pilihan form penerimaan, tetapi tetap tampil di daftar ini.',
        'save'         => 'Simpan',
        'cancel'       => 'Batal',
    ],

    'filter' => [
        'search'           => 'Cari',
        'search_placeholder' => 'Kode atau nama obat',
        'status'           => 'Status',
        'status_all'       => 'Semua status',
        'status_active'    => 'Hanya aktif',
        'status_inactive'  => 'Hanya nonaktif',
        'show'             => 'Tampilkan',
    ],

    'api' => [
        'not_found'      => 'Obat tidak ditemukan.',
        'created'        => 'Obat dibuat.',
        'updated'        => 'Obat diperbarui.',
        'failed'         => 'Gagal.',
        'store_failed'   => 'Gagal menyimpan obat: {0}',
        'update_failed'  => 'Gagal memperbarui obat: {0}',
        'forbidden'      => 'Anda tidak berhak mengubah master obat.',
        'invalid_status' => 'status harus salah satu dari all, active, inactive.',
    ],

    'validation' => [
        'code_required'      => 'code wajib diisi.',
        'code_too_long'      => 'code maksimal 50 karakter.',
        'code_taken'         => 'code {0} sudah dipakai obat lain.',
        'name_required'      => 'name wajib diisi.',
        'name_too_long'      => 'name maksimal 200 karakter.',
        'unit_required'      => 'unit wajib diisi.',
        'unit_too_long'      => 'unit maksimal 50 karakter.',
        'is_active_invalid'  => 'is_active harus bernilai true atau false.',
    ],

    'js' => [
        'load_failed'    => 'Gagal memuat data obat.',
        'contact_failed' => 'Tidak dapat menghubungi server.',
    ],
];
