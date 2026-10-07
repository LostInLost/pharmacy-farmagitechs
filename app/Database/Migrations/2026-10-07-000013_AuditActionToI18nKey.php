<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nilai `audit_logs.action` berubah dari token kanonik
 * (`CREATE`/`UPDATE`/`DELETE`) menjadi kunci i18n
 * (`Audit.receptions.action.create`), sehingga label aksi tidak lagi
 * terikat bahasa saat ditulis dan berkas labelnya sendiri
 * (`app/Language/{id,en}/Audit.php`) tidak tercampur berkas domain lain.
 *
 * Kolom dilebarkan lebih dulu, baru nilainya ditulis: selama masih ENUM,
 * MySQL membuang nilai di luar daftar (atau gagal saat strict mode).
 * Urutan sebaliknya dipakai `down()` supaya token kembali lebih dulu
 * sebelum kolom menyempit.
 *
 * Hanya baris `reception` yang dipindahkan; entitas lain menyusul saat
 * berkas labelnya dibuat, dan sementara ini tetap memakai token lama.
 */
class AuditActionToI18nKey extends Migration
{
    private const ENTITY_RECEPTION = 'reception';

    /**
     * Token lama => verb kunci i18n. Sengaja tidak membaca AuditService:
     * migrasi harus tetap berjalan apa adanya walau kode aplikasi berubah.
     *
     * @var array<string, string>
     */
    private const ACTIONS = [
        'CREATE' => 'create',
        'UPDATE' => 'update',
        'DELETE' => 'delete',
    ];

    public function up()
    {
        $this->forge->modifyColumn('audit_logs', [
            'action' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
        ]);

        foreach (self::ACTIONS as $token => $verb) {
            $this->db->table('audit_logs')
                ->where('entity_type', self::ENTITY_RECEPTION)
                ->where('action', $token)
                ->update(['action' => 'Audit.receptions.action.' . $verb]);
        }
    }

    public function down()
    {
        // Pola `Audit.<grup>.action.<verb>`, bukan daftar entity_type: kode
        // baru menulis kunci untuk entitas apa pun, sedangkan kolom ENUM
        // hanya menerima tiga token — satu baris yang tertinggal membuat
        // penyempitan kolom gagal.
        foreach (self::ACTIONS as $token => $verb) {
            $this->db->table('audit_logs')
                ->like('action', 'Audit.', 'after')
                ->like('action', '.action.' . $verb, 'before')
                ->update(['action' => $token]);
        }

        $this->forge->modifyColumn('audit_logs', [
            'action' => ['type' => 'ENUM', 'constraint' => ['CREATE', 'UPDATE', 'DELETE'], 'null' => false],
        ]);
    }
}
