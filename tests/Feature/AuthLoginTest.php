<?php

use App\Helpers\Hash;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Permissions;

/**
 * `POST /api/login` mengembalikan user + permission role-nya, sehingga
 * frontend Astro bisa menggating UI langsung setelah masuk tanpa menunggu
 * panggilan `GET /api/me` berikutnya.
 *
 * @internal
 */
final class AuthLoginTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->table('users')->insert([
            'name'          => 'Dewi Petugas',
            'username'      => 'petugas',
            'email'         => 'petugas@test.local',
            'password_hash' => Hash::make('petugas123'),
            'role'          => 'reception',
            'is_active'     => 1,
        ]);

        $this->token = (string) service('security')->getHash();

        // Meniru cookie CSRF yang dikirim browser.
        service('superglobals')->setCookie('csrf_cookie_name', $this->token);
    }

    private function login(string $username, string $password)
    {
        return $this->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->post('/api/login', ['username' => $username, 'password' => $password]);
    }

    public function testSuccessfulLoginReturnsUserWithPermissions(): void
    {
        $result = $this->login('petugas', 'petugas123');

        $result->assertStatus(200);

        $body = json_decode((string) $result->getJSON(), true);
        $user = $body['user'];

        $this->assertSame('petugas', $user['username']);
        $this->assertSame('reception', $user['role']);
        $this->assertArrayNotHasKey('password_hash', $user);

        // Satu sumber kebenaran: daftar permission harus identik dengan config.
        $this->assertSame((new Permissions())->forRole('reception'), $user['permissions']);
        $this->assertContains(Permissions::RECEIPT_CREATE, $user['permissions']);
        $this->assertNotContains(Permissions::RECEIPT_UPDATE_ANY, $user['permissions']);
    }

    public function testFailedLoginDoesNotLeakPermissions(): void
    {
        $result = $this->login('petugas', 'salah');

        $result->assertStatus(401);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertArrayNotHasKey('user', $body);
    }
}
