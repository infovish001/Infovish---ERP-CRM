<?php

namespace App\Modules\Finance\Presentation;

use App\Modules\Finance\Application\FinanceDocuments;
use App\Modules\Finance\Application\FinanceLedger;
use App\Modules\Finance\Application\FinancePayments;
use App\Modules\Finance\Application\FinancePrints;
use App\Modules\Finance\Application\FinanceQueries;
use App\Modules\Finance\Application\FinanceReconciliation;
use App\Modules\Finance\Application\FinanceReports;
use App\Modules\Finance\Application\FinanceSetup;
use App\Modules\Parties\Domain\Contracts\Contacts;
use App\Modules\Sales\Domain\Contracts\Orders;
use App\Platform\ModuleRegistry\ModuleAccess;
use App\Platform\Organization\OrganizationService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class FinanceController
{
    public function page(Request $r, OrganizationService $organization, TenantContext $context): Response
    {
        abort_unless(in_array($context->role(), ['owner', 'administrator', 'auditor'], true), 403);

        return Inertia::render('Finance', ['tenant' => ['name' => $r->attributes->get('tenant')->name], 'user' => ['name' => $r->user()->name, 'role' => $context->role()],
            'companies' => $organization->companies()->where('active', true)->orderBy('name')->get(), 'initialCompany' => $r->session()->get('company_id'), 'available' => app(ModuleAccess::class)->allowed('Finance'), 'canManage' => $context->can('organization.manage')]);
    }

    public function setup(Request $r): JsonResponse
    {
        $c = $this->company($r);
        $d = $r->validate(['q' => 'nullable|string|max:100']);

        return response()->json([...app(FinanceSetup::class)->read($c), 'orders' => app(Orders::class)->available($c, $d['q'] ?? ''), 'suppliers' => app(Contacts::class)->suppliers($c, $d['q'] ?? '')]);
    }

    public function account(Request $r, ?string $id = null): JsonResponse
    {
        return $this->saved(app(FinanceSetup::class)->account($this->company($r), $r->all(), $id, $r->header('Idempotency-Key')), $id ? 200 : 201);
    }

    public function period(Request $r): JsonResponse
    {
        return $this->saved(app(FinanceSetup::class)->period($this->company($r), $r->all(), $r->header('Idempotency-Key')), 201);
    }

    public function lock(Request $r, string $id): JsonResponse
    {
        return $this->saved(app(FinanceSetup::class)->lock($this->company($r), $id, $r->all()));
    }

    public function configure(Request $r): JsonResponse
    {
        return $this->saved(app(FinanceSetup::class)->configure($this->company($r), $r->all()));
    }

    public function index(Request $r, string $kind): JsonResponse
    {
        $d = $r->validate(['q' => 'nullable|string|max:100', 'page' => 'sometimes|integer|min:1|max:100000']);

        return response()->json(app(FinanceQueries::class)->listing($this->company($r), $kind, $d['q'] ?? '', $d['page'] ?? 1));
    }

    public function detail(Request $r, string $kind, string $id): JsonResponse
    {
        return response()->json(app(FinanceQueries::class)->detail($this->company($r), $kind, $id));
    }

    public function draft(Request $r, ?string $id = null): JsonResponse
    {
        return $this->saved(app(FinanceLedger::class)->draft($this->company($r), $r->all(), $id, $r->header('Idempotency-Key')), $id ? 200 : 201);
    }

    public function postDraft(Request $r, string $id): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1']);

        return $this->saved(app(FinanceLedger::class)->postDraft($this->company($r), $id, $d['revision'], $r->header('Idempotency-Key')));
    }

    public function reverse(Request $r, string $id): JsonResponse
    {
        $d = $r->validate(['date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:500']);

        return $this->saved(app(FinanceLedger::class)->reverse($this->company($r), $id, $d['date'], $d['reason'], $r->header('Idempotency-Key')));
    }

    public function document(Request $r, string $type, ?string $id = null): JsonResponse
    {
        return $this->saved(app(FinanceDocuments::class)->save($this->company($r), $type, $r->all(), $id, $r->header('Idempotency-Key')), $id ? 200 : 201);
    }

    public function postDocument(Request $r, string $id): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1']);

        return $this->saved(app(FinanceDocuments::class)->post($this->company($r), $id, $d['revision'], $r->header('Idempotency-Key')));
    }

    public function cancel(Request $r, string $id): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1', 'reason' => 'required|string|max:500']);

        return $this->saved(app(FinanceDocuments::class)->cancel($this->company($r), $id, $d['revision'], $d['reason']));
    }

    public function payment(Request $r): JsonResponse
    {
        return $this->saved(app(FinancePayments::class)->record($this->company($r), $r->all(), $r->header('Idempotency-Key')), 201);
    }

    public function void(Request $r, string $id): JsonResponse
    {
        $d = $r->validate(['date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:500']);

        app(FinancePayments::class)->void($this->company($r), $id, $d['date'], $d['reason'], $r->header('Idempotency-Key'));

        return $this->saved($id);
    }

    public function statement(Request $r, ?string $id = null): JsonResponse
    {
        return $this->saved(app(FinanceReconciliation::class)->save($this->company($r), $r->all(), $id, $r->header('Idempotency-Key')), $id ? 200 : 201);
    }

    public function finalize(Request $r, string $id): JsonResponse
    {
        $d = $r->validate(['revision' => 'required|integer|min:1']);

        return $this->saved(app(FinanceReconciliation::class)->finalize($this->company($r), $id, $d['revision'], $r->header('Idempotency-Key')));
    }

    public function bankLines(Request $r): JsonResponse
    {
        $d = $r->validate(['account_id' => 'required|uuid', 'as_of' => 'required|date_format:Y-m-d']);

        return response()->json(['data' => app(FinanceReconciliation::class)->unmatched($this->company($r), $d['account_id'], $d['as_of']), 'limit' => 500]);
    }

    public function reports(Request $r): JsonResponse
    {
        $d = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d']);
        $c = $this->company($r);

        return response()->json(['trial' => app(FinanceReports::class)->trial($c, $d['from'], $d['to']), 'aging' => app(FinanceReports::class)->aging($c, $d['to'])]);
    }

    public function ledger(Request $r): JsonResponse
    {
        $d = $r->validate(['account_id' => 'required|uuid', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d', 'page' => 'sometimes|integer|min:1|max:100000']);

        return response()->json(app(FinanceReports::class)->accountLedger($this->company($r), $d['account_id'], $d['from'], $d['to'], $d['page'] ?? 1));
    }

    public function print(Request $r, string $id): HttpResponse
    {
        $d = $r->validate(['kind' => 'required|in:original,duplicate,copy', 'reason' => 'nullable|string|max:500']);
        $c = $this->company($r);
        $print = app(FinancePrints::class)->issue($c, $id, $d['kind'], $d['reason'] ?? null, $r->header('Idempotency-Key'));
        $pdf = app(FinancePrints::class)->render($c, $print);

        return response($pdf['bytes'], 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"]);
    }

    private function company(Request $r): string
    {
        return $r->validate(['company_id' => 'required|uuid'])['company_id'];
    }

    private function saved(string $id, int $status = 200): JsonResponse
    {
        return response()->json(['id' => $id], $status);
    }
}
