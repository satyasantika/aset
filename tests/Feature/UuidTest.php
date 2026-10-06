<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('membuat id user berupa UUIDv7', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->id[14])->toBe('7');
});

it('menyelesaikan route model binding dengan UUID', function () {
    $user = User::factory()->create();
    Route::get('/uji-user/{user}', fn (User $user) => $user->email)->whereUuid('user')->middleware('web');

    $this->get('/uji-user/'.$user->id)->assertOk()->assertSee($user->email);
});
