<?php

use Tests\TestCase;

// Aset front-end (Vite) tidak dibangun saat uji; tampilan Blade memakai @vite.
pest()->extend(TestCase::class)->beforeEach(fn () => $this->withoutVite())->in('Feature', 'Arch');
