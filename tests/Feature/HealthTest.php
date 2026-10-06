<?php

use Illuminate\Support\Facades\Redis;

it('melaporkan 200 bila db dan redis sehat', function () {
    Redis::shouldReceive('connection->ping')->once()->andReturn(true);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['app' => config('app.name'), 'versi' => config('app.version'), 'db' => 'ok', 'redis' => 'ok']);
});

it('melaporkan 503 bila redis gagal', function () {
    Redis::shouldReceive('connection->ping')->once()->andThrow(new RuntimeException('down'));

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJson(['db' => 'ok', 'redis' => 'gagal']);
});

it('menampilkan versi aplikasi di footer panel login', function () {
    $this->get('/admin/login')->assertOk()->assertSee('v'.config('app.version'));
});
