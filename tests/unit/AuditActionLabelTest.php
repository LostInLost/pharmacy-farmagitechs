<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Label aksi audit: token kanonik di database/API (CREATE/UPDATE/DELETE),
 * terjemahan hanya untuk tampilan lewat Reception.log.action_*.
 *
 * @internal
 */
final class AuditActionLabelTest extends CIUnitTestCase
{
    public function testIndonesianLabels(): void
    {
        $this->assertSame('Menambah data penerimaan', lang('Reception.log.action_create', [], 'id'));
        $this->assertSame('Mengubah data penerimaan', lang('Reception.log.action_update', [], 'id'));
        $this->assertSame('Menghapus data penerimaan', lang('Reception.log.action_delete', [], 'id'));
    }

    public function testEnglishLabels(): void
    {
        $this->assertSame('Added reception data', lang('Reception.log.action_create', [], 'en'));
        $this->assertSame('Updated reception data', lang('Reception.log.action_update', [], 'en'));
        $this->assertSame('Deleted reception data', lang('Reception.log.action_delete', [], 'en'));
    }

    public function testUnknownKeyFallsBackToRawKey(): void
    {
        // Tidak ada terjemahan: lang() mengembalikan kunci aslinya, bukan kosong.
        $this->assertSame('Reception.log.action_archived', lang('Reception.log.action_archived'));
    }
}
