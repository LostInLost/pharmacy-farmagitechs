<?php

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * Memastikan proteksi CSRF berlaku untuk /api/* maupun web:
 * token wajib, token berotasi, dan kegagalan dijawab JSON 403.
 *
 * @internal
 */
final class ApiCsrfTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $security = service('security');
        $this->token = (string) $security->getHash();

        // Meniru cookie CSRF yang dikirim browser.
        service('superglobals')->setCookie('csrf_cookie_name', $this->token);
    }

    private function actor(): array
    {
        return ['user_id' => 1, 'user_name' => 'Dewi Petugas', 'role' => 'reception'];
    }

    public function testMutationWithoutTokenIsForbiddenWithJson(): void
    {
        $result = $this->withSession($this->actor())->post('/api/receipts', []);

        $result->assertStatus(403);

        $body = json_decode((string) $result->getJSON(), true);
        $this->assertSame('csrf', $body['error']);
        $this->assertNotSame('', $result->response()->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testMutationWithValidTokenRotatesToken(): void
    {
        $result = $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->post('/api/receipts', []);

        $result->assertStatus(422);

        $fresh = $result->response()->getHeaderLine('X-CSRF-TOKEN');

        $this->assertNotSame('', $fresh);
        $this->assertNotSame($this->token, $fresh);
        $this->assertSame(service('security')->getHash(), $fresh);
    }

    public function testRotatedTokenCannotBeReplayed(): void
    {
        $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->post('/api/receipts', []);

        $result = $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->post('/api/receipts', []);

        $result->assertStatus(403);
    }

    public function testFreshTokenFromResponseHeaderIsAccepted(): void
    {
        $first = $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->post('/api/receipts', []);

        $fresh = $first->response()->getHeaderLine('X-CSRF-TOKEN');

        $second = $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $fresh])
            ->post('/api/receipts', []);

        $second->assertStatus(422);
    }

    public function testValidTokenWithoutSessionIsUnauthorized(): void
    {
        $result = $this->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->post('/api/receipts', []);

        $result->assertStatus(401);
    }

    public function testWebLogoutRejectsTokenlessPost(): void
    {
        $result = $this->withSession($this->actor())->post('/logout');

        $result->assertRedirect();
    }

    public function testWebLogoutIsNotAvailableViaGet(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->withSession($this->actor())->get('/logout');
    }

    public function testValidTokenAllowsRealMutation(): void
    {
        $result = $this->withSession($this->actor())
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->post('/api/receipts', [
                'reference_no' => 'PB-CSRF-1',
                'supplier_id'  => 1,
                'received_at'  => '2026-10-03T10:00:00+07:00',
                'items'        => [[
                    'medicine_id' => 101,
                    'batch_no'    => 'PCT-2601',
                    'expires_on'  => '2027-12-31',
                    'quantity'    => 10,
                ]],
            ]);

        $result->assertStatus(201);

        $body = json_decode((string) $result->getJSON(), true);

        $this->assertSame('PB-CSRF-1', $body['data']['reference_no']);
        $this->assertNotSame($this->token, $result->response()->getHeaderLine('X-CSRF-TOKEN'));
    }
}
