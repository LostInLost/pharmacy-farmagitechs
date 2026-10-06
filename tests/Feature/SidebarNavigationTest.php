<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Menu utama pindah dari navbar ke sidebar (pola contoh resmi Bootstrap
 * "Sidebars": brand di atas, nav-pills di tengah, blok pengguna + keluar di
 * bawah). Test ini mengunci cangkang HTML-nya karena perilaku responsif
 * (drawer <992px, kolom statis >=992px) bergantung pada struktur tersebut.
 *
 * @internal
 */
final class SidebarNavigationTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private function actor(): array
    {
        return ['user_id' => 1, 'user_name' => 'Dewi Petugas', 'role' => 'reception'];
    }

    public function testSidebarRendersWithActiveReceptionsMenu(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        $result->assertStatus(200);
        $result->assertSeeElement('aside#appSidebar');

        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//a[@class="nav-link active"][@aria-current="page"][contains(@href, "/receptions")]'
        ));
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//a[contains(@href, "/stocks")][not(@aria-current)]'
        ));
    }

    public function testReceptionsMenuStaysActiveOnSubpages(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions/new');

        $result->assertStatus(200);
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//a[contains(@href, "/receptions")][@aria-current="page"]'
        ));
    }

    public function testStocksMenuIsActiveOnlyOnStocksPage(): void
    {
        $result = $this->withSession($this->actor())->get('/stocks');

        $result->assertStatus(200);
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//a[contains(@href, "/stocks")][@aria-current="page"]'
        ));
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//a[contains(@href, "/receptions")][not(@aria-current)]'
        ));
    }

    public function testMenuLinksAreNoLongerInsideNavbar(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        // Menu utama tidak lagi berada di navbar.
        $this->assertFalse($result->seeXPath(
            '//nav[contains(@class, "navbar")]//a[contains(@class, "nav-link")]'
        ));

        // Navbar mobile tetap membawa brand dan tombol pembuka sidebar.
        $this->assertTrue($result->seeXPath(
            '//nav[contains(@class, "navbar")]//a[contains(@class, "navbar-brand")]'
        ));
    }

    public function testSidebarCarriesIdentityAndLogoutFollowingExampleLayout(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        // Blok pengguna dan tombol keluar berada di sidebar, bukan navbar.
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//form[contains(@action, "/logout")]'
        ));
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//span[contains(@class, "app-avatar")]'
        ));
        $this->assertFalse($result->seeXPath(
            '//nav[contains(@class, "navbar")]//form[contains(@action, "/logout")]'
        ));
        $result->assertSee('Dewi Petugas');
        $result->assertSee('reception');
    }

    public function testFooterStaysInsideContentColumnNotUnderSidebar(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        // Footer ikut kolom konten (di samping sidebar)...
        $this->assertTrue($result->seeXPath(
            '//div[contains(@class, "app-main")]/footer'
        ));

        // ...bukan anak langsung shell, yang membuatnya membentang di bawah sidebar.
        $this->assertFalse($result->seeXPath(
            '//div[contains(@class, "app-shell")]/footer'
        ));
    }

    public function testProfileBlockIsPinnedToSidebarBottom(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        // Blok pengguna dibungkus mt-auto agar menempel di dasar sidebar,
        // bukan menggantung tepat di bawah daftar menu.
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//div[contains(@class, "mt-auto")]//span[contains(@class, "app-avatar")]'
        ));
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//div[contains(@class, "mt-auto")]//form[contains(@action, "/logout")]'
        ));
    }

    public function testHamburgerOpensSidebarDrawerOnSmallScreens(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        $this->assertTrue($result->seeXPath(
            '//nav[contains(@class, "navbar")]//button[@data-bs-target="#appSidebar"][@aria-controls="appSidebar"]'
        ));
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"][contains(@class, "offcanvas-lg")][contains(@class, "offcanvas-start")]'
        ));
        $this->assertTrue($result->seeXPath(
            '//aside[@id="appSidebar"]//button[contains(@class, "btn-close")]'
        ));
    }

    public function testGuestDoesNotSeeSidebarNorLogout(): void
    {
        $result = $this->withSession([])->get('/login');

        $result->assertStatus(200);
        $result->assertDontSeeElement('aside#appSidebar');
        $this->assertFalse($result->seeXPath('//button[@data-bs-target="#appSidebar"]'));
        $this->assertFalse($result->seeXPath('//form[contains(@action, "/logout")]'));
    }

    public function testSidebarLabelsFollowActiveLocale(): void
    {
        $result = $this->withSession($this->actor())->get('/receptions');

        $result->assertSee('Penerimaan');
        $result->assertSee('Stok');
    }
}
