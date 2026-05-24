<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Inspiring quote');
})->purpose('Display an inspiring quote');

Artisan::command('test:console', function () {
    $this->info('Console command test OK');
})->purpose('Test console command');
