# Skenario Pengujian (Feature)

Daftar skenario yang akan diotomatisasi pada tahap backend:

1. Membuat penerimaan dari payload contoh; stok obat 101 menjadi 144, obat 104 menjadi 8.
2. Mengubah penerimaan: kuantitas obat 101 menjadi 7, hapus obat 104, tambah obat 103 batch SAL-2601 kuantitas 3; stok obat 101 = 141, obat 103 = 18, obat 104 = 3.
3. Mengirim pembaruan identik dua kali tidak menggandakan stok.
4. Dibuat petugas, diubah supervisor: pembuat tetap petugas, pengubah terakhir supervisor, riwayat aksi memuat kedua kejadian.
5. Dibuat supervisor: petugas dapat melihat, tetapi update ditolak tanpa mengubah penerimaan, stok, dan log aksi. Request tanpa login juga ditolak.
