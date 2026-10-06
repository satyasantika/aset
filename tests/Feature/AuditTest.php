<?php

use App\Filament\Resources\LogAktivitas\Pages\ListLogAktivitas;
use App\Models\Aktivitas;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function pelaku(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $user;
}

it('mencatat perubahan dengan pelaku dari autentikasi', function () {
    $admin = pelaku('admin-bmn');
    $sasaran = User::factory()->create(['name' => 'Lama']);

    $this->actingAs($admin);
    $sasaran->update(['name' => 'Baru']);

    $log = Aktivitas::query()->where('subject_id', $sasaran->id)->where('description', 'updated')->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($admin->id)
        ->and($log->log_name)->toBe('user')
        ->and($log->properties['attributes'])->toBe(['name' => 'Baru'])
        ->and($log->properties['old'])->toBe(['name' => 'Lama']);
});

it('mengabaikan field "pengguna" palsu dari input sebagai pelaku', function () {
    $admin = pelaku('admin-bmn');
    $korban = User::factory()->create();
    $sasaran = User::factory()->create();

    Route::middleware('web')->patch('/uji-ubah/{user}', function (User $user) {
        // Pola SIMAN-2 yang berbahaya: pelaku diambil dari payload. Di sini input tidak boleh berpengaruh.
        $user->update(['name' => 'Diubah lewat request']);

        return 'ok';
    });

    $this->actingAs($admin)->patch('/uji-ubah/'.$sasaran->id, ['pengguna' => $korban->id, 'causer_id' => $korban->id])->assertOk();

    $log = Aktivitas::query()->where('subject_id', $sasaran->id)->where('description', 'updated')->latest()->first();

    expect($log->causer_id)->toBe($admin->id)->not->toBe($korban->id);
});

it('tidak pernah menyimpan kata sandi atau rahasia MFA di log', function () {
    $admin = pelaku('admin-bmn');
    $this->actingAs($admin);
    $sasaran = User::factory()->create();
    $sasaran->update(['password' => 'sangat-rahasia']);
    $sasaran->saveAppAuthenticationSecret('RAHASIA');

    $json = Aktivitas::query()->get()->map(fn ($a) => json_encode($a->properties))->implode('');

    expect($json)->not->toContain('sangat-rahasia')->not->toContain('RAHASIA')->not->toContain('password');
});

it('hanya admin-bmn dan super-admin yang dapat membuka log aktivitas', function (string $peran, bool $boleh) {
    $this->actingAs(pelaku($peran));

    $respons = $this->get('/admin/log-aktivitas');
    $boleh ? $respons->assertOk() : $respons->assertForbidden();
})->with([
    ['super-admin', true],
    ['admin-bmn', true],
    ['pejabat-penatausahaan', false],
    ['pic-ruangan', false],
    ['pimpinan', false],
]);

it('menyaring log berdasarkan modul dan pelaku', function () {
    $admin = pelaku('admin-bmn');
    $lain = pelaku('super-admin');
    $this->actingAs($admin);
    User::factory()->create(['name' => 'X'])->update(['name' => 'Y']);

    Aktivitas::query()->create(['log_name' => 'mutasi', 'description' => 'created', 'causer_type' => User::class, 'causer_id' => $lain->id]);

    Livewire::test(ListLogAktivitas::class)
        ->filterTable('log_name', 'mutasi')
        ->assertCanSeeTableRecords(Aktivitas::query()->where('log_name', 'mutasi')->get())
        ->assertCanNotSeeTableRecords(Aktivitas::query()->where('log_name', 'user')->get());

    Livewire::test(ListLogAktivitas::class)
        ->filterTable('causer_id', $lain->id)
        ->assertCanSeeTableRecords(Aktivitas::query()->where('causer_id', $lain->id)->get())
        ->assertCanNotSeeTableRecords(Aktivitas::query()->where('log_name', 'user')->where('causer_id', $admin->id)->get());
});

it('tidak dapat mengubah atau menghapus log lewat policy', function () {
    $admin = pelaku('super-admin');
    $log = Aktivitas::query()->create(['log_name' => 'uji', 'description' => 'created']);

    // super-admin lolos Gate::before; peran lain ditolak oleh policy
    $biasa = pelaku('admin-bmn');
    expect($biasa->can('update', $log))->toBeFalse()
        ->and($biasa->can('delete', $log))->toBeFalse()
        ->and($biasa->can('viewAny', Aktivitas::class))->toBeTrue()
        ->and($admin->can('viewAny', Aktivitas::class))->toBeTrue();
});
