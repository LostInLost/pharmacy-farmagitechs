<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Menguji cabang render view yang sulit dijangkau lewat HTTP smoke test,
 * misalnya daftar kosong dan pembatasan hak ubah.
 *
 * @internal
 */
final class ReceptionListRenderTest extends CIUnitTestCase
{
    /**
     * @param list<array<string, mixed>> $receptions
     */
    private function render(array $receptions, array $actor): string
    {
        return view('receptions/index', [
            'title'       => lang('Reception.title.list'),
            'receptions'  => $receptions,
            'actor'       => $actor,
            'permissions' => new \Config\Permissions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reception(int $createdBy): array
    {
        return [
            'id'               => 7,
            'reference_no'     => 'PB-007',
            'supplier_name'    => 'PT Sehat Sentosa',
            'received_at'      => '2026-10-03 10:00:00',
            'created_by'       => $createdBy,
            'created_by_name'  => 'Dewi Petugas',
            'created_at'       => '2026-10-03 10:05:00',
            'updated_by'       => null,
            'updated_by_name'  => null,
            'updated_at'       => null,
        ];
    }

    public function testRendersEmptyState(): void
    {
        $html = $this->render([], ['id' => 1, 'role' => 'supervisor']);

        $this->assertStringContainsString('Belum ada penerimaan', $html);
    }

    public function testCreatorSeesEditButton(): void
    {
        $html = $this->render([$this->reception(2)], ['id' => 2, 'role' => 'reception']);

        $this->assertStringContainsString('Ubah', $html);
        $this->assertStringContainsString('btn-outline-primary', $html);
        $this->assertStringNotContainsString('tidak berhak', $html);
    }

    public function testOtherReceptionOfficerSeesNotAllowedBadge(): void
    {
        $html = $this->render([$this->reception(99)], ['id' => 2, 'role' => 'reception']);

        $this->assertStringContainsString('tidak berhak', $html);
        $this->assertStringContainsString('text-bg-secondary', $html);
    }

    public function testSupervisorSeesEditButtonForSomeoneElsesReception(): void
    {
        $html = $this->render([$this->reception(99)], ['id' => 1, 'role' => 'supervisor']);

        $this->assertStringContainsString('Ubah', $html);
        $this->assertStringNotContainsString('tidak berhak', $html);
    }

    public function testRendersNeverUpdatedMarker(): void
    {
        $html = $this->render([$this->reception(2)], ['id' => 2, 'role' => 'reception']);

        $this->assertStringContainsString('belum pernah diubah', $html);
    }

    public function testStockListRendersNoBatchState(): void
    {
        $html = view('stocks/index', [
            'title'     => lang('Stock.title'),
            'onDate'    => '2026-10-06',
            'medicines' => [[
                'code' => 'X-1', 'name' => 'Uji', 'unit' => 'tablet',
                'physical_quantity' => 0, 'available_quantity' => 0, 'expired_quantity' => 0,
                'available_batches' => [], 'expired_batches' => [],
            ]],
        ]);

        $this->assertStringContainsString('belum ada batch', $html);
    }

    public function testStockListRendersBatchBadges(): void
    {
        $html = view('stocks/index', [
            'title'     => lang('Stock.title'),
            'onDate'    => '2026-10-06',
            'medicines' => [[
                'code' => 'X-2', 'name' => 'Uji Dua', 'unit' => 'kapsul',
                'physical_quantity' => 7, 'available_quantity' => 5, 'expired_quantity' => 2,
                'available_batches' => [['batch_no' => 'B-AVAIL', 'expires_on' => '2027-01-01', 'quantity' => 5]],
                'expired_batches'   => [['batch_no' => 'B-EXP', 'expires_on' => '2020-01-01', 'quantity' => 2]],
            ]],
        ]);

        $this->assertStringContainsString('B-AVAIL', $html);
        $this->assertStringContainsString('B-EXP', $html);
        $this->assertStringContainsString('text-bg-success', $html);
        $this->assertStringContainsString('text-bg-danger', $html);
        $this->assertStringContainsString('2 batch', $html);
    }
}
