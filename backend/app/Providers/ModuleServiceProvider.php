<?php

namespace App\Providers;

use App\Modules\Catalog\Application\ProductService;
use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\CRM\Application\OpportunityService;
use App\Modules\CRM\Domain\Contracts\Opportunities;
use App\Modules\Finance\Application\FinanceReports;
use App\Modules\Finance\Domain\Contracts\Ledger;
use App\Modules\Parties\Application\ContactService;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Sales\Application\SalesService;
use App\Modules\Sales\Domain\Contracts\Orders;
use App\Platform\ModuleRegistry\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Ledger::class, FinanceReports::class);
        $this->app->bind(Orders::class, SalesService::class);
        $this->app->bind(Contacts::class, ContactService::class);
        $this->app->bind(Products::class, ProductService::class);
        $this->app->bind(Opportunities::class, OpportunityService::class);
    }

    public function boot(): void
    {
        app(ModuleRegistry::class)->manifests();
    }
}
