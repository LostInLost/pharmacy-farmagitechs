<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Fixtures\StockFixture;

/**
 * API master obat: hak akses per peran, validasi, filter, dan jejak audit.
 *
 * @internal
 */
final class MedicineApiTest extends CIUnitTestCase
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
            ->post('/api/medicines', $payload);

        return $this->rememberToken($result);
    }

    private function updateAs(array $actor, int $id, array $payload)
    {
        $result = $this->withSession($actor)
            ->withHeaders(['X-CSRF-TOKEN' => $this->token])
            ->withBodyFormat('json')
            ->put('/api/medicines/' . $id, $payload);

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
            'code'      => 'OBT-NEW',
            'name'      => 'Obat Baru 500 mg tablet',
            'unit'      => 'tablet',
            'is_active' => true,
        ];
    }

    public function testMasterRequiresLogin(): void
    {
        $this->withSession([])->get('/api/medicines')->assertStatus(401);
        $this->withSession([])->get('/api/medicines/101')->assertStatus(401);
    }

    public function testOfficerCanReadMasterList(): void
    {
        $result = $this->withSession($this->petugas())->get('/api/medicines');

        $result->assertStatus(200);

        $body = $this->body($result);
        $data = $body['data'];

        // Termasuk obat nonaktif (105): master menampilkan seluruh katalog,
        // berbeda dari dropdown referensi yang hanya menampilkan yang aktif.
        $this->assertSame([102, 106, 104, 107, 105, 101, 103], array_column($data, 'id'));
        $this->assertSame(['id', 'code', 'name', 'unit', 'is_active'], array_keys($data[0]));
        $this->assertFalse($body['can_write']);
    }

    public function testSupervisorListAndDetailMarkWritable(): void
    {
        $list = $this->body($this->withSession($this->supervisor())->get('/api/medicines'));
        $this->assertTrue($list['can_write']);

        $detail = $this->body($this->withSession($this->supervisor())->get('/api/medicines/101'));
        $this->assertTrue($detail['can_write']);
        $this->assertSame('OBT-001', $detail['data']['code']);

        $officerDetail = $this->body($this->withSession($this->petugas())->get('/api/medicines/101'));
        $this->assertFalse($officerDetail['can_write']);
    }

    public function testOfficerCannotWriteMaster(): void
    {
        $before = $this->db->table('medicines')->countAllResults();
        $logs   = $this->db->table('audit_logs')->countAllResults();

        $this->createAs($this->petugas(), $this->payload())->assertStatus(403);
        $this->updateAs($this->petugas(), 101, $this->payload(['code' => 'OBT-001', 'name' => 'Diubah petugas']))->assertStatus(403);

        $this->assertSame($before, $this->db->table('medicines')->countAllResults());
        $this->assertSame($logs, $this->db->table('audit_logs')->countAllResults());

        $this->assertSame(
            'Paracetamol 500 mg tablet',
            $this->db->table('medicines')->where('id', 101)->get()->getRowArray()['name'],
        );
    }

    public function testSupervisorCreatesMedicineAndWritesAudit(): void
    {
        $result = $this->createAs($this->supervisor(), $this->payload());

        $result->assertStatus(201);

        $body = $this->body($result);

        $this->assertSame('OBT-NEW', $body['data']['code']);
        $this->assertTrue($body['data']['is_active']);

        $id = (int) $body['data']['id'];

        $logs = $this->db->table('audit_logs')
            ->where('entity_type', 'medicine')
            ->where('entity_id', $id)
            ->get()
            ->getResultArray();

        $this->assertCount(1, $logs);
        // Nilai audit adalah kunci i18n untuk semua entitas (satu penulis,
        // satu format kolom); label obat menyusul saat tampilan auditnya ada.
        $this->assertSame('Audit.medicines.action.create', $logs[0]['action']);
        $this->assertSame($this->supervisorId, (int) $logs[0]['actor_id']);
        $this->assertNull($logs[0]['data_before']);
        $this->assertStringContainsString('OBT-NEW', (string) $logs[0]['data_after']);
    }

    public function testSupervisorUpdatesMedicineAndAuditKeepsBeforeAfter(): void
    {
        $created = $this->createAs($this->supervisor(), $this->payload());
        $id      = (int) $this->body($created)['data']['id'];

        $result = $this->updateAs($this->supervisor(), $id, $this->payload([
            'name'      => 'Obat Baru 250 mg tablet',
            'is_active' => false,
        ]));

        $result->assertStatus(200);

        $body = $this->body($result);

        $this->assertSame('Obat Baru 250 mg tablet', $body['data']['name']);
        $this->assertFalse($body['data']['is_active']);

        $logs = $this->db->table('audit_logs')
            ->where('entity_type', 'medicine')
            ->where('entity_id', $id)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(['Audit.medicines.action.create', 'Audit.medicines.action.update'], array_column($logs, 'action'));
        $this->assertStringContainsString('Obat Baru 500 mg tablet', (string) $logs[1]['data_before']);
        $this->assertStringContainsString('Obat Baru 250 mg tablet', (string) $logs[1]['data_after']);
    }

    public function testDeactivatingMedicineHidesItFromReceptionDropdownButKeepsItInMaster(): void
    {
        $result = $this->updateAs($this->supervisor(), 106, [
            'code'      => 'OBT-006',
            'name'      => 'Cetirizine 10 mg tablet',
            'unit'      => 'tablet',
            'is_active' => false,
        ]);

        $result->assertStatus(200);

        $references = $this->body($this->withSession($this->petugas())->get('/api/references/medicines'))['data'];
        $this->assertNotContains(106, array_column($references, 'id'));

        $master = $this->body($this->withSession($this->petugas())->get('/api/medicines'))['data'];
        $this->assertContains(106, array_column($master, 'id'));
    }

    public function testUpdateWithoutIsActiveKeepsStoredStatus(): void
    {
        $result = $this->updateAs($this->supervisor(), 106, [
            'code' => 'OBT-006',
            'name' => 'Cetirizine 10 mg tablet (nama baru)',
            'unit' => 'tablet',
        ]);

        $result->assertStatus(200);

        $this->assertTrue($this->body($result)['data']['is_active']);
    }

    public function testValidationRejectsMissingAndDuplicateFields(): void
    {
        $missing = $this->createAs($this->supervisor(), ['code' => ' ', 'name' => '', 'unit' => '']);

        $missing->assertStatus(422);

        $errors = $this->body($missing)['errors'];

        $this->assertContains('code wajib diisi.', $errors);
        $this->assertContains('name wajib diisi.', $errors);
        $this->assertContains('unit wajib diisi.', $errors);

        // Kode yang sudah dipakai obat lain (case-insensitive mengikuti
        // collation unik MySQL) harus ditolak 422, bukan error 500.
        $duplicate = $this->createAs($this->supervisor(), $this->payload(['code' => 'obt-001']));

        $duplicate->assertStatus(422);
        $this->assertStringContainsString('obt-001', $this->body($duplicate)['errors'][0]);
    }

    public function testUpdateKeepsOwnCodeAndRejectsOtherMedicineCode(): void
    {
        $own = $this->updateAs($this->supervisor(), 101, [
            'code'      => 'OBT-001',
            'name'      => 'Paracetamol 500 mg tablet',
            'unit'      => 'tablet',
            'is_active' => true,
        ]);

        $own->assertStatus(200);

        $taken = $this->updateAs($this->supervisor(), 101, [
            'code'      => 'OBT-002',
            'name'      => 'Paracetamol 500 mg tablet',
            'unit'      => 'tablet',
            'is_active' => true,
        ]);

        $taken->assertStatus(422);
    }

    public function testUnknownMedicineIsNotFound(): void
    {
        $this->withSession($this->petugas())->get('/api/medicines/999')->assertStatus(404);

        $this->updateAs($this->supervisor(), 999, $this->payload())->assertStatus(404);
    }

    public function testSearchAndStatusFilters(): void
    {
        $search = $this->body($this->withSession($this->petugas())->get('/api/medicines?q=paraceta'))['data'];
        $this->assertSame([101], array_column($search, 'id'));

        $byCode = $this->body($this->withSession($this->petugas())->get('/api/medicines?q=OBT-003'))['data'];
        $this->assertSame([103], array_column($byCode, 'id'));

        $inactive = $this->body($this->withSession($this->petugas())->get('/api/medicines?status=inactive'))['data'];
        $this->assertSame([105], array_column($inactive, 'id'));

        $active = $this->body($this->withSession($this->petugas())->get('/api/medicines?status=active'))['data'];
        $this->assertNotContains(105, array_column($active, 'id'));

        $this->withSession($this->petugas())->get('/api/medicines?status=bogus')->assertStatus(422);
    }

    public function testWildcardInSearchIsTreatedAsLiteral(): void
    {
        // '%' harus dicari sebagai karakter biasa; kalau bocor menjadi
        // wildcard, seluruh katalog akan ikut cocok.
        $result = $this->body($this->withSession($this->petugas())->get('/api/medicines?q=%25'))['data'];

        $this->assertSame([], $result);
    }

    public function testCreateWithoutTokenIsForbiddenByCsrf(): void
    {
        $result = $this->withSession($this->supervisor())
            ->withBodyFormat('json')
            ->post('/api/medicines', $this->payload());

        $result->assertStatus(403);
        $this->assertSame('csrf', $this->body($result)['error']);
    }
}
