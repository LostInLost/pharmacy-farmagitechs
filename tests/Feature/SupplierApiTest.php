<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * API master pemasok: hak akses per peran, validasi, filter, dan jejak audit.
 *
 * @internal
 */
final class SupplierApiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = null;

    private string $token;
    private int $petugasId;
    private int $supervisorId;

    protected function setUp(): void
    {
        parent::setUp();

        (new StockFixture($this->db))->seed();

        $this->db->table('users')->insert([
            'name'          => 'Rina Supervisor',
            'username'      => 'supervisor',
            'email'         => 'supervisor@test.local',
            'password_hash' => 'x',
            'role'          => 'supervisor',
            'is_active'     => 1,
        ]);

        $this->petugasId    = (int) $this->db->table('users')->where('username', 'petugas')->get()->getRowArray()['id'];
        $this->supervisorId = (int) $this->db->table('users')->where('username', 'supervisor')->get()->getRowArray()['id'];

        $security = service('security');
        $this->token = (string) $security->getHash();

        service('superglobals')->setCookie('csrf_cookie_name', $this->token);
    }

    private function petugas(): array
    {
        return ['user_id' => $this->petugasId, 'user_name' => 'Dewi Petugas', 'role' => 'reception'];
    }

    private function supervisor(): array
    {
        return ['user_id' => $this->supervisorId, 'user_name' => 'Rina Supervisor', 'role' => 'supervisor'];
    }

    private function body($result): array
    {
        return json_decode((string) $result->getJSON(), true);
    }

    private function createAs(array $actor, array $payload)
    {
        $result = $this->withSession($actor)
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->post('/api/suppliers', $payload);

        return $this->rememberToken($result);
    }

    private function updateAs(array $actor, int $id, array $payload)
    {
        $result = $this->withSession($actor)
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->put('/api/suppliers/' . $id, $payload);

        return $this->rememberToken($result);
    }

    /**
     * Token CSRF berotasi setiap mutasi berhasil, jadi nilai terbaru harus
     * dipakai pada permintaan berikutnya — sama seperti klien sungguhan.
     */
    private function rememberToken($result)
    {
        $fresh = $result->response()->getHeaderLine('X-CSRF-TOKEN');

        if ($fresh !== '') {
            $this->token = $fresh;
            service('superglobals')->setCookie('csrf_cookie_name', $fresh);
        }

        return $result;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name'      => 'Pemasok Baru Sejahtera',
            'is_active' => true,
        ];
    }

    public function testMasterRequiresLogin(): void
    {
        $this->withSession([])->get('/api/suppliers')->assertStatus(401);
        $this->withSession([])->get('/api/suppliers/1')->assertStatus(401);
    }

    public function testOfficerCanReadMasterList(): void
    {
        $result = $this->withSession($this->petugas())->get('/api/suppliers');

        $result->assertStatus(200);

        $body = $this->body($result);
        $data = $body['data'];

        // Termasuk pemasok nonaktif (3): master menampilkan seluruh katalog,
        // berbeda dari dropdown referensi yang hanya menampilkan yang aktif.
        $this->assertSame([1, 2, 3], array_column($data, 'id'));
        $this->assertSame(['id', 'name', 'is_active'], array_keys($data[0]));
        $this->assertFalse($body['can_write']);
    }

    public function testSupervisorListAndDetailMarkWritable(): void
    {
        $list = $this->body($this->withSession($this->supervisor())->get('/api/suppliers'));
        $this->assertTrue($list['can_write']);

        $detail = $this->body($this->withSession($this->supervisor())->get('/api/suppliers/1'));
        $this->assertTrue($detail['can_write']);
        $this->assertSame('Farma Nusantara', $detail['data']['name']);

        $officerDetail = $this->body($this->withSession($this->petugas())->get('/api/suppliers/1'));
        $this->assertFalse($officerDetail['can_write']);
    }

    public function testOfficerCannotWriteMaster(): void
    {
        $before = $this->db->table('suppliers')->countAllResults();
        $logs   = $this->db->table('audit_logs')->countAllResults();

        $this->createAs($this->petugas(), $this->payload())->assertStatus(403);
        $this->updateAs($this->petugas(), 1, $this->payload(['name' => 'Diubah petugas']))->assertStatus(403);

        $this->assertSame($before, $this->db->table('suppliers')->countAllResults());
        $this->assertSame($logs, $this->db->table('audit_logs')->countAllResults());

        $this->assertSame(
            'Farma Nusantara',
            $this->db->table('suppliers')->where('id', 1)->get()->getRowArray()['name'],
        );
    }

    public function testSupervisorCreatesSupplierAndWritesAudit(): void
    {
        $result = $this->createAs($this->supervisor(), $this->payload());

        $result->assertStatus(201);

        $body = $this->body($result);

        $this->assertSame('Pemasok Baru Sejahtera', $body['data']['name']);
        $this->assertTrue($body['data']['is_active']);

        $id = (int) $body['data']['id'];

        $logs = $this->db->table('audit_logs')
            ->where('entity_type', 'supplier')
            ->where('entity_id', $id)
            ->get()
            ->getResultArray();

        $this->assertCount(1, $logs);
        $this->assertSame('Audit.suppliers.action.create', $logs[0]['action']);
        $this->assertSame($this->supervisorId, (int) $logs[0]['actor_id']);
        $this->assertNull($logs[0]['data_before']);
        $this->assertStringContainsString('Pemasok Baru Sejahtera', (string) $logs[0]['data_after']);
    }

    public function testSupervisorUpdatesSupplierAndAuditKeepsBeforeAfter(): void
    {
        $created = $this->createAs($this->supervisor(), $this->payload());
        $id      = (int) $this->body($created)['data']['id'];

        $result = $this->updateAs($this->supervisor(), $id, $this->payload([
            'name'      => 'Pemasok Baru Mandiri',
            'is_active' => false,
        ]));

        $result->assertStatus(200);

        $body = $this->body($result);

        $this->assertSame('Pemasok Baru Mandiri', $body['data']['name']);
        $this->assertFalse($body['data']['is_active']);

        $logs = $this->db->table('audit_logs')
            ->where('entity_type', 'supplier')
            ->where('entity_id', $id)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(['Audit.suppliers.action.create', 'Audit.suppliers.action.update'], array_column($logs, 'action'));
        $this->assertStringContainsString('Pemasok Baru Sejahtera', (string) $logs[1]['data_before']);
        $this->assertStringContainsString('Pemasok Baru Mandiri', (string) $logs[1]['data_after']);
    }

    public function testDeactivatingSupplierHidesItFromReceptionDropdownButKeepsItInMaster(): void
    {
        $result = $this->updateAs($this->supervisor(), 2, [
            'name'      => 'Medika Sentosa',
            'is_active' => false,
        ]);

        $result->assertStatus(200);

        $references = $this->body($this->withSession($this->petugas())->get('/api/references/suppliers'))['data'];
        $this->assertNotContains(2, array_column($references, 'id'));

        $master = $this->body($this->withSession($this->petugas())->get('/api/suppliers'))['data'];
        $this->assertContains(2, array_column($master, 'id'));
    }

    public function testUpdateWithoutIsActiveKeepsStoredStatus(): void
    {
        $result = $this->updateAs($this->supervisor(), 2, [
            'name' => 'Medika Sentosa Jaya',
        ]);

        $result->assertStatus(200);

        $this->assertTrue($this->body($result)['data']['is_active']);
    }

    public function testValidationRejectsMissingAndDuplicateNames(): void
    {
        $missing = $this->createAs($this->supervisor(), ['name' => '  ']);

        $missing->assertStatus(422);

        $errors = $this->body($missing)['errors'];

        $this->assertContains('name wajib diisi.', $errors);

        // Nama yang sudah dipakai pemasok lain (case-insensitive mengikuti
        // collation unik MySQL) harus ditolak 422, bukan error 500.
        $duplicate = $this->createAs($this->supervisor(), $this->payload(['name' => 'farma nusantara']));

        $duplicate->assertStatus(422);
        $this->assertStringContainsString('farma nusantara', $this->body($duplicate)['errors'][0]);

        $tooLong = $this->createAs($this->supervisor(), $this->payload(['name' => str_repeat('a', 151)]));
        $tooLong->assertStatus(422);
        $this->assertContains('name maksimal 150 karakter.', $this->body($tooLong)['errors']);
    }

    public function testUpdateKeepsOwnNameAndRejectsOtherSupplierName(): void
    {
        $own = $this->updateAs($this->supervisor(), 1, [
            'name'      => 'Farma Nusantara',
            'is_active' => true,
        ]);

        $own->assertStatus(200);

        $taken = $this->updateAs($this->supervisor(), 1, [
            'name'      => 'Medika Sentosa',
            'is_active' => true,
        ]);

        $taken->assertStatus(422);
    }

    public function testUnknownSupplierIsNotFound(): void
    {
        $this->withSession($this->petugas())->get('/api/suppliers/999')->assertStatus(404);

        $this->updateAs($this->supervisor(), 999, $this->payload())->assertStatus(404);
    }

    public function testSearchAndStatusFilters(): void
    {
        $search = $this->body($this->withSession($this->petugas())->get('/api/suppliers?q=medika'))['data'];
        $this->assertSame([2], array_column($search, 'id'));

        $inactive = $this->body($this->withSession($this->petugas())->get('/api/suppliers?status=inactive'))['data'];
        $this->assertSame([3], array_column($inactive, 'id'));

        $active = $this->body($this->withSession($this->petugas())->get('/api/suppliers?status=active'))['data'];
        $this->assertNotContains(3, array_column($active, 'id'));

        $this->withSession($this->petugas())->get('/api/suppliers?status=bogus')->assertStatus(422);
    }

    public function testWildcardInSearchIsTreatedAsLiteral(): void
    {
        // '%' harus dicari sebagai karakter biasa; kalau bocor menjadi
        // wildcard, seluruh katalog akan ikut cocok.
        $result = $this->body($this->withSession($this->petugas())->get('/api/suppliers?q=%25'))['data'];

        $this->assertSame([], $result);
    }

    public function testCreateWithoutTokenIsForbiddenByCsrf(): void
    {
        $result = $this->withSession($this->supervisor())
            ->withBodyFormat('json')
            ->post('/api/suppliers', $this->payload());

        $result->assertStatus(403);
        $this->assertSame('csrf', $this->body($result)['error']);
    }

    public function testDetailIncludesChronologicalLogs(): void
    {
        $created = $this->createAs($this->supervisor(), $this->payload());
        $id      = (int) $this->body($created)['data']['id'];

        $this->updateAs($this->supervisor(), $id, $this->payload([
            'name'      => 'Pemasok Baru Mandiri',
            'is_active' => false,
        ]))->assertStatus(200);

        // Petugas punya supplier.view, jadi riwayatnya ikut terbaca.
        $result = $this->withSession($this->petugas())->get('/api/suppliers/' . $id);

        $result->assertStatus(200);

        $body = $this->body($result);

        $this->assertFalse($body['can_write']);
        $this->assertSame('Pemasok Baru Mandiri', $body['data']['name']);

        $logs = $body['data']['logs'];

        $this->assertCount(2, $logs);
        $this->assertSame(
            ['Audit.suppliers.action.create', 'Audit.suppliers.action.update'],
            array_column($logs, 'action'),
        );
        $this->assertSame($this->supervisorId, $logs[0]['actor_id']);
        $this->assertSame('Rina Supervisor', $logs[0]['actor_name']);
        $this->assertNull($logs[0]['data_before']);
        $this->assertSame('Pemasok Baru Sejahtera', $logs[1]['data_before']['name']);
        $this->assertSame('Pemasok Baru Mandiri', $logs[1]['data_after']['name']);
    }

    public function testListStaysLeanWithoutLogs(): void
    {
        $result = $this->withSession($this->petugas())->get('/api/suppliers');

        $result->assertStatus(200);
        $this->assertArrayNotHasKey('logs', $this->body($result)['data'][0]);
    }

    public function testRejectedWriteWritesNoAudit(): void
    {
        $logsBefore = $this->db->table('audit_logs')->countAllResults();

        // 403: petugas tidak punya supplier.write.
        $this->createAs($this->petugas(), $this->payload(['name' => 'Pemasok Ditolak']))->assertStatus(403);

        // 422: nama duplikat (case-insensitive mengikuti collation).
        $this->createAs($this->supervisor(), $this->payload(['name' => 'farma nusantara']))->assertStatus(422);

        // 422: payload tidak lengkap.
        $this->createAs($this->supervisor(), ['name' => ''])->assertStatus(422);

        // 404: id yang tidak ada.
        $this->updateAs($this->supervisor(), 999, $this->payload(['name' => 'Pemasok Hantu']))->assertStatus(404);

        $this->assertSame($logsBefore, $this->db->table('audit_logs')->countAllResults());
    }
}
