<?php

it('menjawab health check bawaan /up', function () {
    $this->get('/up')->assertOk();
});

it('memakai locale dan zona waktu Indonesia', function () {
    expect(app()->getLocale())->toBe('id')
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});
