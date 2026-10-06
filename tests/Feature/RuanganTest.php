<?php

use App\Filament\Resources\Ruangan\Pages\BuatRuangan;
use App\Filament\Resources\Ruangan\Pages\UbahRuangan;
use App\Filament\Resources\Ruangan\RelationManagers\PicRelationManager;
use App\Models\Gedung;
use App\Models\KategoriRuangan;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function pengguna(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $user;
}

function buatRuangan(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

it('membuat ruangan lengkap dengan relasi, K3L, dan prodi', function () {
    $this->actingAs(pengguna('admin-bmn'));
    $gedung = Gedung::query()->create(['kode' => 'GD-A', 'nama' => 'A']);
    $kategori = KategoriRuangan::query()->create(['nama' => 'Laboratorium', 'adalah_laboratorium' => true]);
    $prodi = Prodi::query()->create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika']);

    Livewire::test(BuatRuangan::class)
        ->fillForm([
            'kode' => 'LAB-KOM-1', 'nama' => 'Lab Komputer 1', 'gedung_id' => $gedung->id, 'kategori_ruangan_id' => $kategori->id,
            'lantai' => '2', 'kapasitas' => 40, 'luas_m2' => 72.5, 'dapat_dipinjam' => true,
            'prodi' => [$prodi->id], 'k3l' => ['apar', 'p3k'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ruangan = Ruangan::query()->where('kode', 'LAB-KOM-1')->firstOrFail();
    expect($ruangan->dapat_dipinjam)->toBeTrue()
        ->and($ruangan->k3l)->toBe(['apar', 'p3k'])
        ->and($ruangan->prodi->pluck('kode')->all())->toBe(['PMAT'])
        ->and($ruangan->gedung->kode)->toBe('GD-A')
        ->and((string) $ruangan->luas_m2)->toBe('72.50');
});

it('menjaga kode ruangan unik di form dan di basis data', function () {
    $this->actingAs(pengguna('admin-bmn'));
    buatRuangan('R-1');

    Livewire::test(BuatRuangan::class)
        ->fillForm(['kode' => 'R-1', 'nama' => 'Ganda'])
        ->call('create')
        ->assertHasFormErrors(['kode']);

    expect(fn () => buatRuangan('R-1'))->toThrow(QueryException::class);
});

it('scope dikelolaOleh: admin semua, PIC hanya ruangannya, selain itu kosong', function () {
    $a = buatRuangan('R-A');
    $b = buatRuangan('R-B');
    buatRuangan('R-C');
    $pic = pengguna('pic-ruangan');
    $pic->ruanganDikelola()->attach([$a->id, $b->id]);

    expect(Ruangan::query()->dikelolaOleh(pengguna('admin-bmn'))->count())->toBe(3)
        ->and(Ruangan::query()->dikelolaOleh(pengguna('super-admin'))->count())->toBe(3)
        ->and(Ruangan::query()->dikelolaOleh($pic)->pluck('kode')->sort()->values()->all())->toBe(['R-A', 'R-B'])
        ->and(Ruangan::query()->dikelolaOleh(pengguna('pic-ruangan'))->count())->toBe(0)
        ->and(Ruangan::query()->dikelolaOleh(pengguna('civitas'))->count())->toBe(0)
        ->and($pic->ruanganDikelola->pluck('kode')->sort()->values()->all())->toBe(['R-A', 'R-B']);
});

it('PIC dapat melihat daftar ruangan tetapi tidak membuat atau mengubahnya', function () {
    $this->actingAs(pengguna('pic-ruangan'));
    $ruangan = buatRuangan('R-1');

    $this->get('/admin/ruangan')->assertOk();
    $this->get('/admin/ruangan/create')->assertForbidden();
    $this->get('/admin/ruangan/'.$ruangan->id.'/edit')->assertForbidden();
});

it('hanya pengguna ber-peran pic-ruangan yang dapat ditugaskan', function () {
    $this->actingAs(pengguna('admin-bmn'));
    $ruangan = buatRuangan('R-1');
    $pic = pengguna('pic-ruangan');
    $bukanPic = pengguna('pimpinan');

    $komponen = Livewire::test(PicRelationManager::class, ['ownerRecord' => $ruangan, 'pageClass' => UbahRuangan::class]);

    $komponen->callAction(TestAction::make('attach')->table(), ['recordId' => $pic->id])->assertHasNoFormErrors();
    expect($ruangan->pic()->pluck('users.id')->all())->toBe([$pic->id]);

    $komponen->callAction(TestAction::make('attach')->table(), ['recordId' => $bukanPic->id])->assertHasFormErrors();
    expect($ruangan->pic()->count())->toBe(1);
});

it('menjaga satu PIC utama per ruangan', function () {
    $ruangan = buatRuangan('R-1');
    [$p1, $p2] = [pengguna('pic-ruangan'), pengguna('pic-ruangan')];
    $ruangan->pic()->attach([$p1->id, $p2->id]);

    $ruangan->tetapkanPicUtama($p1);
    $ruangan->tetapkanPicUtama($p2);

    $utama = $ruangan->pic()->wherePivot('utama', true)->pluck('users.id')->all();
    expect($utama)->toBe([$p2->id]);
});

it('menjadikan PIC utama lewat aksi di relation manager', function () {
    $this->actingAs(pengguna('admin-bmn'));
    $ruangan = buatRuangan('R-1');
    $pic = pengguna('pic-ruangan');
    $ruangan->pic()->attach($pic->id);

    Livewire::test(PicRelationManager::class, ['ownerRecord' => $ruangan, 'pageClass' => UbahRuangan::class])
        ->callAction(TestAction::make('jadikanUtama')->table($pic));

    expect((bool) $ruangan->pic()->first()->pivot->utama)->toBeTrue();
});
