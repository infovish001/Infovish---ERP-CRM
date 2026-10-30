<?php

namespace App\Providers;

use App\Modules\Catalog\Application\ProductService;
use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\Parties\Application\ContactService;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Platform\ModuleRegistry\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Contacts::class, ContactService::class);
        $this->app->bind(Products::class, ProductService::class);
    }

    public function boot(): void
    {
        app(ModuleRegistry::class)->manifests();
    }
}
