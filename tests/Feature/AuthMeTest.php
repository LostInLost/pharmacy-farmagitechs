<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Permissions;

/**
 * Endpoint cek sesi untuk middleware SSR frontend Astro (GET /api/me):
 * 401 bila anonim, 200 + data user + permission role bila sudah login.
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

    public function testUserCarriesRolePermissions(): void
    {
        $result = $this->withSession($this->actor())->get('/api/me');

        $body = json_decode((string) $result->getJSON(), true);
        $permissions = $body['user']['permissions'];

        $this->assertSame((new Permissions())->forRole('reception'), $permissions);
        $this->assertContains(Permissions::RECEIPT_CREATE, $permissions);
        $this->assertContains(Permissions::RECEIPT_UPDATE_OWN, $permissions);
        $this->assertNotContains(Permissions::RECEIPT_UPDATE_ANY, $permissions);
        $this->assertNotContains(Permissions::MEDICINE_WRITE, $permissions);
        $this->assertContains(Permissions::SUPPLIER_VIEW, $permissions);
        $this->assertNotContains(Permissions::SUPPLIER_WRITE, $permissions);
    }

    public function testSupervisorPermissionsIncludeUpdateAnyAndMedicineWrite(): void
    {
        $result = $this->withSession([
            'user_id' => 2, 'user_name' => 'Rina Supervisor', 'username' => 'supervisor', 'role' => 'supervisor',
        ])->get('/api/me');

        $permissions = json_decode((string) $result->getJSON(), true)['user']['permissions'];

        $this->assertContains(Permissions::RECEIPT_UPDATE_ANY, $permissions);
        $this->assertContains(Permissions::MEDICINE_WRITE, $permissions);
        $this->assertContains(Permissions::SUPPLIER_WRITE, $permissions);
    }

    public function testUnknownRoleGetsEmptyPermissions(): void
    {
        // Fail-closed: role tak dikenal tidak mendapat aksi apa pun di UI.
        $result = $this->withSession(['user_id' => 3, 'user_name' => 'Entah', 'role' => 'auditor'])->get('/api/me');

        $result->assertStatus(200);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame([], $body['user']['permissions']);
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
