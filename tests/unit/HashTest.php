<?php

use App\Helpers\Hash;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HashTest extends CIUnitTestCase
{
    public function testMakeUsesConfiguredAlgorithm(): void
    {
        $expected = config('Config\Hash')->resolve()['algo'];
        $prefixes = [
            PASSWORD_BCRYPT     => '$2y$',
            PASSWORD_ARGON2I    => '$argon2i$',
            PASSWORD_ARGON2ID   => '$argon2id$',
        ];

        $this->assertStringStartsWith($prefixes[$expected] ?? '$', Hash::make('rahasia123'));
    }

    public function testMakeProducesDifferentHashForSamePassword(): void
    {
        $this->assertNotSame(Hash::make('rahasia123'), Hash::make('rahasia123'));
    }

    public function testMatchAcceptsCorrectPassword(): void
    {
        $this->assertTrue(Hash::match('rahasia123', Hash::make('rahasia123')));
    }

    public function testMatchRejectsWrongPassword(): void
    {
        $this->assertFalse(Hash::match('salah', Hash::make('rahasia123')));
    }

    public function testMatchAcceptsLegacyBcryptHash(): void
    {
        $legacy = password_hash('rahasia123', PASSWORD_BCRYPT);

        $this->assertTrue(Hash::match('rahasia123', $legacy));
    }
}
