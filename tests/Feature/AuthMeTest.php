<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Endpoint cek sesi untuk middleware SSR frontend Astro (GET /api/me):
 * 401 bila anonim, 200 + data user bila sudah login.
 *
 * @internal
 */
final class AuthMeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = null;

    private function actor(): array
    {
        return ['user_id' => 1, 'user_name' => 'Dewi Petugas', 'username' => 'dewi', 'role' => 'reception'];
    }

    public function testAnonymousGets401(): void
    {
        $result = $this->withSession([])->get('/api/me');

        $result->assertStatus(401);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame(lang('Auth.unauthenticated'), $body['message']);
    }

    public function testAuthenticatedGetsUser(): void
    {
        $result = $this->withSession($this->actor())->get('/api/me');

        $result->assertStatus(200);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame(1, $body['user']['id']);
        $this->assertSame('Dewi Petugas', $body['user']['name']);
        $this->assertSame('dewi', $body['user']['username']);
        $this->assertSame('reception', $body['user']['role']);

        // Kontrak proyek: setiap response API membawa token CSRF terbaru.
        $this->assertNotSame('', $result->response()->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testSessionWithoutUsernameFallsBackToEmptyString(): void
    {
        // Sesi lama (dibuat sebelum username disimpan) tetap valid.
        $result = $this->withSession(['user_id' => 2, 'user_name' => 'Sesi Lama', 'role' => 'supervisor'])->get('/api/me');

        $result->assertStatus(200);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame('', $body['user']['username']);
    }
}
