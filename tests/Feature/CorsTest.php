<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Memastikan CORS untuk frontend Astro (http://localhost:4321) bekerja:
 * preflight dijawab filter `cors`, respons API membawa header CORS, dan
 * penolakan CSRF tetap membawa header CORS sehingga frontend dapat membaca
 * token segar dari header untuk mengulang request.
 *
 * @internal
 */
final class CorsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $namespace = null;

    private const FRONTEND_ORIGIN = 'http://localhost:4321';

    public function testPreflightFromFrontendOriginIsAllowed(): void
    {
        $result = $this->withHeaders([
            'Origin'                         => self::FRONTEND_ORIGIN,
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'content-type, x-csrf-token',
        ])->options('/api/login');

        $result->assertStatus(204);

        $response = $result->response();

        $this->assertSame(self::FRONTEND_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertStringContainsString('X-CSRF-TOKEN', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertNotSame('', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function testPreflightDoesNotEchoForeignOrigin(): void
    {
        // Konfigurasi hanya punya satu origin; nilai Allow-Origin tidak pernah
        // diambil dari request, sehingga origin asing tidak mungkin ter-echo.
        $result = $this->withHeaders([
            'Origin'                        => 'http://evil.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/login');

        $this->assertNotSame(
            'http://evil.example',
            $result->response()->getHeaderLine('Access-Control-Allow-Origin'),
        );
    }

    public function testApiResponseCarriesCorsHeaders(): void
    {
        $security = service('security');
        $token    = (string) $security->getHash();

        // Meniru cookie CSRF yang dikirim browser.
        service('superglobals')->setCookie('csrf_cookie_name', $token);

        $result = $this->withHeaders([
            'Origin'       => self::FRONTEND_ORIGIN,
            'X-CSRF-TOKEN' => $token,
        ])->withBodyFormat('json')->post('/api/login', ['username' => '', 'password' => '']);

        $result->assertStatus(422);

        $response = $result->response();

        $this->assertSame(self::FRONTEND_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertStringContainsString('X-CSRF-TOKEN', $response->getHeaderLine('Access-Control-Expose-Headers'));
    }

    public function testCsrfRejectionStillCarriesCorsHeaders(): void
    {
        // Tanpa token, CsrfFilter menolak lebih dulu. Header CORS wajib ikut;
        // tanpa itu browser memblokir respons dan frontend tidak bisa membaca
        // token segar untuk mengulang request.
        $result = $this->withHeaders(['Origin' => self::FRONTEND_ORIGIN])
            ->post('/api/login', ['username' => 'a', 'password' => 'b']);

        $result->assertStatus(403);

        $body = json_decode((string) $result->getJSON(), true);

        $this->assertSame('csrf', $body['error']);

        $response = $result->response();

        $this->assertSame(self::FRONTEND_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotSame('', $response->getHeaderLine('X-CSRF-TOKEN'));
    }

    public function testCsrfBootstrapEndpointReturnsTokenHeader(): void
    {
        $result = $this->get('/api/csrf');

        $result->assertStatus(200);

        $response = $result->response();
        $token    = $response->getHeaderLine('X-CSRF-TOKEN');

        $this->assertNotSame('', $token);
        $this->assertSame($token, service('security')->getHash());
    }

    public function testPlainOptionsReturnsAllowHeader(): void
    {
        $result = $this->options('/api/login');

        $result->assertStatus(204);
        $this->assertStringContainsString('POST', $result->response()->getHeaderLine('Allow'));
    }
}
