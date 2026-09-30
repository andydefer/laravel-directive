<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Fixtures\Providers;

use Illuminate\Support\ServiceProvider;

final class NoopServiceProvider extends ServiceProvider
{
    public function register(): void {}
}
