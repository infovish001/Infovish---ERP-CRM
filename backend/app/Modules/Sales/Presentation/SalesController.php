<?php

namespace App\Modules\Sales\Presentation;

use App\Modules\Catalog\Domain\Contracts\Products;
use App\Modules\CRM\Domain\Contracts\Opportunities;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Sales\Application\SalesAccess;
use App\Modules\Sales\Application\SalesService;
use App\Modules\Sales\Application\TaxProfile;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\Organization\OrganizationService;
use App\Shared\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class SalesController
{
    public function page(Request $request, OrganizationService $organization, TenantContext $context): Response
    {
        return Inertia::render('Sales', ['tenant' => ['name' => $request->attributes->get('tenant')->name],
            'user' => ['name' => $request->user()->name], 'companies' => $organization->companies()->where('active', true)->orderBy('name')->get(),
            'initialCompany' => $request->session()->get('company_id'), 'available' => app(ModuleAccess::class)->allowed('Sales'), 'canManage' => $context->can('organization.manage')]);
    }

    public function setup(Request $request): JsonResponse
    {
        $company = $this->company($request);
        app(SalesAccess::class)->company($company);
        $data = $request->validate(['q' => 'nullable|string|max:100', 'customer_id' => 'nullable|uuid']);

        return response()->json(['profile' => app(TaxProfile::class)->get($company), 'customers' => app(Contacts::class)->customers($company, $data['q'] ?? ''),
            'products' => app(Products::class)->available($company, $data['q'] ?? ''), 'opportunities' => isset($data['customer_id']) ? app(Opportunities::class)->openForCustomer($company, $data['customer_id']) : [],
            'rule_version' => TaxProfile::RULE]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['id' => app(TaxProfile::class)->save($this->company($request), $request->all())]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(app(SalesService::class)->listing($this->company($request), $request->all(), $this->orders($request)));
    }

    public function detail(Request $request, string $id): JsonResponse
    {
        return response()->json(app(SalesService::class)->detail($this->company($request), $id, $this->orders($request)));
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        return response()->json(['id' => app(SalesService::class)->save($this->company($request), $request->all(), $id, $request->header('Idempotency-Key'))], $id ? 200 : 201);
    }

    public function transition(Request $request, string $id): JsonResponse
    {
        return response()->json(['id' => app(SalesService::class)->transition($this->company($request), $id, $request->all())]);
    }

    public function convert(Request $request, string $id): JsonResponse
    {
        return response()->json(['id' => app(SalesService::class)->convert($this->company($request), $id, $request->all(), $request->header('Idempotency-Key'))]);
    }

    public function pdf(Request $request, string $id): HttpResponse
    {
        $company = $this->company($request);
        $orders = $this->orders($request);
        $record = app(SalesService::class)->detail($company, $id, $orders)['record'];
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => base_path('vendor/dompdf'), 'defaultFont' => 'DejaVu Sans']);
        $options->setAllowedProtocols(['file://']);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('sales.document', ['record' => $record, 'order' => $orders])->render());
        $pdf->render();
        $pdf->getCanvas()->page_text(34, 815, $record->number.' - Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('helvetica'), 8, [0.35, 0.4, 0.5]);

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$record->number.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"]);
    }

    private function company(Request $request): string
    {
        return $request->validate(['company_id' => 'required|uuid'])['company_id'];
    }

    private function orders(Request $request): bool
    {
        return $request->route()->named('sales.order.*');
    }
}
