<?php

use App\Actions\Api\BuatTokenKlien;
use App\Models\Aset;
use App\Models\Gedung;
use App\Models\PemakaianRuangan;
use App\Models\Ruangan;
use App\Models\TokenAkses;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
});

function apSuper(): User
{
    $u = User::factory()->create(['aktif' => true]);
    $u->assignRole('super-admin');

    return $u;
}

function apToken(array $abilities): string
{
    return app(BuatTokenKlien::class)->handle('Surat Uji', $abilities, apSuper());
}

function apRuangan(string $kode = 'AP-1', bool $bisa = true): Ruangan
{
    $g = Gedung::query()->firstOrCreate(['kode' => 'G1'], ['nama' => 'Gedung Utama']);

    return Ruangan::query()->create(['kode' => $kode, 'nama' => 'Aula '.$kode, 'gedung_id' => $g->id, 'kapasitas' => 100, 'dapat_dipinjam' => $bisa]);
}

function apH(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

test('tanpa token 401; ability salah 403', function () {
    apRuangan();

    $this->getJson('/api/v1/ruangan')->assertUnauthorized();
    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', [])->assertUnauthorized();

    $baca = apToken(['ruangan:baca']);
    $this->getJson('/api/v1/ruangan', apH($baca))->assertOk();
    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', [], apH($baca))->assertForbidden();
});

test('daftar ruangan hanya yang dapat dipinjam dan memuat fasilitas ringkas', function () {
    $r = apRuangan();
    apRuangan('AP-X', bisa: false);
    Aset::factory()->diRuangan($r)->count(2)->create(['nama' => 'Proyektor', 'kode_barang' => '3100102001']);

    $res = $this->getJson('/api/v1/ruangan', apH(apToken(['ruangan:baca'])))->assertOk();

    expect($res->json('data'))->toHaveCount(1)
        ->and($res->json('data.0'))->toMatchArray(['kode' => 'AP-1', 'gedung' => 'Gedung Utama', 'kapasitas' => 100])
        ->and($res->json('data.0.fasilitas.0'))->toBe(['nama' => 'Proyektor', 'jumlah' => 2]);
});

test('pakai: mencatat 201, ulang idempoten 200, bentrok 409, referensi sama beda waktu 409', function () {
    apRuangan();
    $h = apH(apToken(['ruangan:baca', 'ruangan:pakai']));
    $badan = ['mulai' => '2026-12-01T08:00:00+07:00', 'selesai' => '2026-12-01T12:00:00+07:00', 'kegiatan' => 'Seminar', 'referensi_eksternal' => 'SRT-001'];

    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', $badan, $h)->assertCreated()->assertJsonPath('dibuat', true);
    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', $badan, $h)->assertOk()->assertJsonPath('dibuat', false);
    expect(PemakaianRuangan::query()->count())->toBe(1);

    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', [...$badan, 'referensi_eksternal' => 'SRT-002', 'mulai' => '2026-12-01T11:00:00+07:00', 'selesai' => '2026-12-01T13:00:00+07:00'], $h)
        ->assertStatus(409)->assertJsonCount(1, 'bentrok');

    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', [...$badan, 'selesai' => '2026-12-01T14:00:00+07:00'], $h)->assertStatus(409);

    // tepi bersentuhan bukan bentrok
    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', [...$badan, 'referensi_eksternal' => 'SRT-003', 'mulai' => '2026-12-01T12:00:00+07:00', 'selesai' => '2026-12-01T14:00:00+07:00'], $h)->assertCreated();
    expect(PemakaianRuangan::query()->count())->toBe(2);
});

test('validasi 422, ruangan tidak ada atau tak dapat dipinjam 404', function () {
    apRuangan();
    apRuangan('AP-T', bisa: false);
    $h = apH(apToken(['ruangan:pakai']));

    $this->postJson('/api/v1/ruangan/AP-1/pemakaian', ['mulai' => '2026-12-01T10:00:00Z', 'selesai' => '2026-12-01T09:00:00Z'], $h)->assertUnprocessable();
    $this->postJson('/api/v1/ruangan/NOPE/pemakaian', ['mulai' => '2026-12-01T10:00:00Z', 'selesai' => '2026-12-01T11:00:00Z', 'kegiatan' => 'x', 'referensi_eksternal' => 'r'], $h)->assertNotFound();
    $this->postJson('/api/v1/ruangan/AP-T/pemakaian', ['mulai' => '2026-12-01T10:00:00Z', 'selesai' => '2026-12-01T11:00:00Z', 'kegiatan' => 'x', 'referensi_eksternal' => 'r'], $h)->assertNotFound();
});

test('jadwal memuat pemakaian terjadwal dalam rentang dan menolak rentang terlalu panjang', function () {
    $r = apRuangan();
    $h = apH(apToken(['ruangan:baca', 'ruangan:pakai']));
    foreach ([['2026-12-01 08:00', '2026-12-01 10:00', 'A'], ['2026-12-10 08:00', '2026-12-10 10:00', 'B']] as [$m, $s, $k]) {
        PemakaianRuangan::query()->create(['ruangan_id' => $r->id, 'mulai' => $m, 'selesai' => $s, 'kegiatan' => $k, 'sumber' => 'manual', 'status' => 'terjadwal']);
    }
    PemakaianRuangan::query()->create(['ruangan_id' => $r->id, 'mulai' => '2026-12-01 09:00', 'selesai' => '2026-12-01 11:00', 'kegiatan' => 'Batal', 'sumber' => 'manual', 'status' => 'dibatalkan']);

    $res = $this->getJson('/api/v1/ruangan/AP-1/jadwal?dari=2026-12-01&sampai=2026-12-05', $h)->assertOk();
    expect(collect($res->json('data'))->pluck('kegiatan')->all())->toBe(['A']);

    $this->getJson('/api/v1/ruangan/AP-1/jadwal?dari=2026-01-01&sampai=2026-12-31', $h)->assertUnprocessable();
});

test('hanya super-admin yang dapat membuat token; ability harus valid; akun klien tidak bisa masuk', function () {
    $admin = User::factory()->create(['aktif' => true]);
    $admin->assignRole('admin-bmn');

    expect(fn () => app(BuatTokenKlien::class)->handle('X', ['ruangan:baca'], $admin))->toThrow(AuthorizationException::class);
    expect(fn () => app(BuatTokenKlien::class)->handle('X', ['admin:*'], apSuper()))->toThrow(ValidationException::class);

    apToken(['ruangan:baca']);
    $klien = TokenAkses::query()->firstOrFail()->tokenable;
    expect($klien->aktif)->toBeFalse()->and($klien->roles)->toHaveCount(0);
});

test('batas 60 permintaan per menit per token', function () {
    $h = apH(apToken(['ruangan:baca']));

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/ruangan', $h)->assertOk();
    }

    $this->getJson('/api/v1/ruangan', $h)->assertStatus(429);
});
