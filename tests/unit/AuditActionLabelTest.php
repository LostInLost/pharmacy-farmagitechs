<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Label aksi audit: nilai `audit_logs.action` adalah kunci i18n itu sendiri
 * (`Audit.receptions.action.create`), dan labelnya tinggal diterjemahkan di
 * lapisan render. Berkas labelnya sengaja terpisah dari domain lain.
 *
 * @internal
 */
final class AuditActionLabelTest extends CIUnitTestCase
{
    public function testActionKeysExistInAuditFile(): void
    {
        // lang() tanpa titik mengembalikan string apa adanya, jadi grupnya
        // yang diminta — sekaligus membuktikan berkasnya benar-benar dimuat.
        $receptions = lang('Audit.receptions');

        $this->assertIsArray($receptions);
        $this->assertSame('Menambahkan data penerimaan', $receptions['action']['create']);
        $this->assertSame('Mengubah data penerimaan', $receptions['action']['update']);
        $this->assertSame('Menghapus data penerimaan', $receptions['action']['delete']);
    }

    public function testIndonesianLabels(): void
    {
        $this->assertSame('Menambahkan data penerimaan', lang('Audit.receptions.action.create', [], 'id'));
        $this->assertSame('Mengubah data penerimaan', lang('Audit.receptions.action.update', [], 'id'));
        $this->assertSame('Menghapus data penerimaan', lang('Audit.receptions.action.delete', [], 'id'));
    }

    public function testEnglishLabels(): void
    {
        $this->assertSame('Added reception data', lang('Audit.receptions.action.create', [], 'en'));
        $this->assertSame('Updated reception data', lang('Audit.receptions.action.update', [], 'en'));
        $this->assertSame('Deleted reception data', lang('Audit.receptions.action.delete', [], 'en'));
    }

    public function testUnknownKeyFallsBackToRawKey(): void
    {
        // Kunci tanpa terjemahan dikembalikan apa adanya, sehingga log lama
        // (mis. baris medicine yang masih memakai token) tidak hilang.
        $this->assertSame('Audit.receptions.action.archived', lang('Audit.receptions.action.archived'));
        $this->assertSame('CREATE', lang('CREATE'));
    }

    public function testMedicineLabels(): void
    {
        // Entitas baru cukup menambah grupnya di berkas yang sama; tidak ada
        // kolom ENUM yang perlu diubah saat master obat mulai diaudit.
        $medicines = lang('Audit.medicines');

        $this->assertIsArray($medicines);
        $this->assertSame('Menambahkan data obat', lang('Audit.medicines.action.create', [], 'id'));
        $this->assertSame('Mengubah data obat', lang('Audit.medicines.action.update', [], 'id'));
        $this->assertSame('Menghapus data obat', lang('Audit.medicines.action.delete', [], 'id'));

        $this->assertSame('Added medicine data', lang('Audit.medicines.action.create', [], 'en'));
        $this->assertSame('Updated medicine data', lang('Audit.medicines.action.update', [], 'en'));
        $this->assertSame('Deleted medicine data', lang('Audit.medicines.action.delete', [], 'en'));
    }

    public function testReceptionFileNoLongerCarriesActionLabels(): void
    {
        $log = lang('Reception.log');

        $this->assertIsArray($log);
        $this->assertArrayNotHasKey('action_create', $log);
        $this->assertArrayNotHasKey('action_update', $log);
        $this->assertArrayNotHasKey('action_delete', $log);
    }
}
