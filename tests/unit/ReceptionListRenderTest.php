<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * View daftar kini cangkang: data diisi jQuery dari /api/*. Test ini
 * mengunci struktur cangkang dan kontrak boot, bukan isi baris.
 *
 * @internal
 */
final class ReceptionListRenderTest extends CIUnitTestCase
{
    public function testListRendersShellWithBootObject(): void
    {
        $html = view('receptions/index', [
            'title' => lang('Reception.title.list'),
        ]);

        $this->assertStringContainsString('id="receptions-tbody"', $html);
        $this->assertStringContainsString('id="feedback"', $html);
        $this->assertStringContainsString('window.FARMASI_BOOT', $html);
        $this->assertStringContainsString('assets/js/pages/receptions.js', $html);
        $this->assertStringNotContainsString('PB-007', $html);
    }

    public function testFormRendersShellWithReceptionId(): void
    {
        $html = view('receptions/form', [
            'title'       => lang('Reception.title.edit'),
            'receptionId' => 7,
        ]);

        $this->assertStringContainsString('id="reception-form"', $html);
        $this->assertStringContainsString('id="item-rows"', $html);
        $this->assertStringContainsString('id="log-tbody"', $html);
        $this->assertStringContainsString('receptionId: 7', $html);
        $this->assertStringContainsString('assets/js/pages/reception-form.js', $html);
    }

    public function testFormBootsActionLabelsForLogRendering(): void
    {
        $html = view('receptions/form', [
            'title'       => lang('Reception.title.edit'),
            'receptionId' => 7,
        ]);

        // Label aksi dikirim via i18n boot; token mentah tetap dari API.
        $this->assertStringContainsString('logActionCreate', $html);
        $this->assertStringContainsString('logActionUpdate', $html);
        $this->assertStringContainsString('logActionDelete', $html);
        $this->assertStringContainsString(lang('Reception.log.action_create'), $html);
        $this->assertStringContainsString(lang('Reception.log.action_update'), $html);
        $this->assertStringContainsString(lang('Reception.log.action_delete'), $html);
    }

    public function testFormRendersShellWithoutReceptionId(): void
    {
        $html = view('receptions/form', [
            'title'       => lang('Reception.title.new'),
            'receptionId' => null,
        ]);

        $this->assertStringContainsString('receptionId: null', $html);
    }

    public function testStockRendersShellWithFilter(): void
    {
        $html = view('stocks/index', [
            'title' => lang('Stock.title'),
        ]);

        $this->assertStringContainsString('id="stocks-filter"', $html);
        $this->assertStringContainsString('id="stocks-tbody"', $html);
        $this->assertStringContainsString('id="status_filter"', $html);
        $this->assertStringContainsString('value="available"', $html);
        $this->assertStringContainsString('value="expired"', $html);
        $this->assertStringContainsString('window.FARMASI_BOOT', $html);
        $this->assertStringContainsString('assets/js/pages/stocks.js', $html);
        $this->assertStringContainsString('filterEmpty', $html);
    }
}
