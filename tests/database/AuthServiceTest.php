<?php

use App\Helpers\Hash;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class AuthServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->table('users')->insertBatch([
            [
                'name'          => 'Dewi Petugas',
                'username'      => 'petugas',
                'email'         => 'petugas@test.local',
                'password_hash' => Hash::make('petugas123'),
                'role'          => 'reception',
                'is_active'     => 1,
            ],
            [
                'name'          => 'Rina Supervisor',
                'username'      => 'nonaktif',
                'email'         => 'nonaktif@test.local',
                'password_hash' => Hash::make('rahasia123'),
                'role'          => 'supervisor',
                'is_active'     => 0,
            ],
        ]);
    }

    public function testAttemptSucceedsWithValidCredentials(): void
    {
        $user = (new AuthService())->attempt('petugas', 'petugas123');

        $this->assertNotNull($user);
        $this->assertSame('petugas', $user['username']);
        $this->assertSame('reception', $user['role']);
        $this->assertArrayNotHasKey('password_hash', $user);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $this->assertNull((new AuthService())->attempt('petugas', 'salah'));
    }

    public function testAttemptFailsForUnknownUser(): void
    {
        $this->assertNull((new AuthService())->attempt('tidakada', 'apa saja'));
    }

    public function testAttemptFailsForInactiveUser(): void
    {
        $this->assertNull((new AuthService())->attempt('nonaktif', 'rahasia123'));
    }

    public function testAttemptNormalizesNumericTypes(): void
    {
        $user = (new AuthService())->attempt('petugas', 'petugas123');

        $this->assertIsInt($user['id']);
        $this->assertIsInt($user['is_active']);
    }
}
