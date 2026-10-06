<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Memastikan filter guest menolak pengguna yang sudah login dengan error
 * eksplisit: 403 + view untuk web, 403 JSON untuk /api/*.
 *
 * @internal
 */
final class GuestFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = null;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $security = service('security');
        $this->token = (string) $security->getHash();

        // Meniru cookie CSRF yang dikirim browser.
        service('superglobals')->setCookie('csrf_cookie_name', $this->token);
    }

    private function actor(): array
    {
        return ['user_id' => 1, 'user_name' => 'Dewi Petugas', 'role' => 'reception'];
    }

    public function testGuestCanOpenLoginPage(): void
    {
        $result = $this->get('/login');

        $result->assertStatus(200);
    }

    public function testAuthenticatedUserSeesErrorViewOnLoginPage(): void
    {
        $result = $this->withSession($this->actor())->get('/login');

        $result->assertStatus(403);

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString(lang('Auth.already.title'), $body);
        $this->assertStringContainsString(lang('Auth.already.message'), $body);
        $this->assertFalse($result->response()->hasHeader('Location'));
    }

    public function testGuestCanReachApiLogin(): void
    {
        // Body objek (bukan `[]`): Security::removeTokenInRequest() menulis ulang
        // body JSON array menjadi form-encoded sehingga getJSON() gagal.
        $result = $this->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->post('/api/login', ['username' => '', 'password' => '']);

        $result->assertStatus(422);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame(lang('Auth.api.required'), $body['message']);
    }

    public function testAuthenticatedUserIsRejectedOnApiLogin(): void
    {
        $result = $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->post('/api/login', ['username' => 'dewi', 'password' => 'rahasia']);

        $result->assertStatus(403);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame(lang('Auth.already_authenticated'), $body['message']);

        // Kontrak proyek: setiap response API membawa token CSRF terbaru.
        $this->assertSame(
            service('security')->getHash(),
            $result->response()->getHeaderLine('X-CSRF-TOKEN'),
        );
    }

    public function testAuthFilterStillProtectsReceptions(): void
    {
        $this->get('/receptions')->assertRedirect();
    }
}
