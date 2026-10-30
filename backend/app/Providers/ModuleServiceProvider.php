<?php

namespace App\Providers;

use App\Modules\Catalog\Application\ProductService;
use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\CRM\Application\OpportunityService;
use App\Modules\CRM\Domain\Contracts\Opportunities;
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
        $this->app->bind(Opportunities::class, OpportunityService::class);
    }

    public function boot(): void
    {
        app(ModuleRegistry::class)->manifests();
    }
}
